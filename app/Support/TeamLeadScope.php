<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menerjemahkan pembagian katalog yang dibuat Admin menjadi cakupan kerja
 * seorang Team Lead: Subkategori mana yang boleh ia baca, dan — dari situ —
 * petugas mana yang ia awasi.
 *
 * SEBELUM kelas ini ada, TeamLeadController::scopedAgentQuery() mengembalikan
 * SELURUH agent aktif satu desk tanpa saringan apa pun. Akibatnya setiap Team
 * Lead mengawasi seluruh tim sekaligus, dan reassign() bisa melempar tiket ke
 * agent mana pun yang id-nya ditebak — satu-satunya jalur di sistem ini yang
 * menembus aturan "tidak ada di katalog = tidak menerima tiket".
 *
 * DUA ATURAN:
 *
 * 1. TANPA PENUGASAN, TIDAK ADA AKSES. Subkategori yang kolom pemiliknya
 *    NULL tidak terbaca Team Lead manapun — bukan terbaca semuanya.
 *
 *    Ini kebalikan dari rancangan pertama, dan alasannya menentukan: default
 *    yang permisif membuat celah wewenang tetap terbuka di setiap baris yang
 *    Admin lupa isi, tanpa satu pun tanda bahwa ia terbuka. Kelalaian jadi
 *    berbentuk izin. Dengan aturan ini kelalaian berbentuk layar kosong —
 *    kelihatan, bisa ditanyakan, dan tidak memberi wewenang kepada siapa pun.
 *
 *    Harganya dibayar di muka: sampai Admin selesai membagi, setiap Team Lead
 *    BPO melihat dashboard kosong. TeamLeadController mengirim `scopeEmpty`
 *    supaya layarnya menjelaskan kenapa, bukan sekadar menampilkan angka nol.
 *
 *    Akibat yang sama berlaku untuk PETUGAS: agent yang tidak menjadi PIC di
 *    Subjek aktif manapun tidak masuk cakupan siapa pun. Justru itu yang
 *    menutup celah aslinya — Team Lead tidak bisa lagi memindahkan tiket ke
 *    orang yang tidak terdaftar di katalog.
 *
 * 2. Cakupan itu GABUNGAN, bukan kepemilikan eksklusif. Seorang PIC yang
 *    bekerja di dua Subkategori milik dua Team Lead muncul di kedua
 *    dashboard. Aturan "semua Subkategorinya harus milik saya" akan membuat
 *    orang seperti itu tidak terlihat siapa pun — dan di data nyata setiap
 *    PIC BPO memang tersebar di 2-4 Subkategori. Tumpang tindih diselesaikan
 *    Admin dengan memindahkan PIC Subjeknya, bukan oleh query ini.
 *
 * Subkategori milik Team Lead yang akunnya dimatikan otomatis ikut aturan 1:
 * pemiliknya tidak bisa masuk, dan tidak ada orang lain yang mewarisinya.
 * Admin harus menugaskannya ulang — terlihat di kolom "Belum ditugaskan" pada
 * layar Service Catalog.
 */
final class TeamLeadScope
{
    /**
     * Desk → kolom pemilik di `service_catalog_subcategories`.
     *
     * Desk yang TIDAK terdaftar di sini tidak disempitkan sama sekali —
     * itulah seam-nya. Menyusulkan desk IT nanti berarti menambah satu kolom
     * dan satu baris di peta ini; tidak ada tempat kedua yang harus diingat,
     * karena setiap penyempitan di TeamLeadController membaca jawabannya dari
     * kelas ini.
     */
    private const DESK_COLUMN = ['bpo' => 'team_lead_bpo_user_id'];

    /** Kolom PIC di `service_catalog_subjects` milik tiap desk. */
    private const DESK_PIC_COLUMN = ['bpo' => 'support_agent_id', 'it' => 'it_agent_id'];

    public static function columnFor(string $desk): ?string
    {
        return self::DESK_COLUMN[$desk] ?? null;
    }

    public static function isNarrowed(string $desk): bool
    {
        return self::columnFor($desk) !== null;
    }

    /**
     * Subkategori yang boleh dibaca lead ini — HANYA yang ditugaskan Admin
     * kepadanya. Yang belum bertuan tidak masuk cakupan siapa pun.
     *
     * @return Collection<int,int>
     */
    public static function visibleSubcategoryIds(User $lead, string $desk): Collection
    {
        $column = self::columnFor($desk);

        if ($column === null) {
            return ServiceCatalogSubcategory::pluck('id');
        }

        return ServiceCatalogSubcategory::where($column, $lead->id)->pluck('id');
    }

    /**
     * id baris `support_agents` yang boleh diawasi lead ini.
     *
     * NULL berarti "desk ini tidak disempitkan" — sengaja dibedakan dari []
     * yang berarti "disempitkan, dan hasilnya memang kosong". `whereIn('id',
     * [])` mengembalikan nol baris, jadi desk IT yang keliru menerima []
     * akan tampil sebagai dashboard kosong tanpa satu pun error.
     *
     * @return list<int>|null
     */
    public static function agentIds(User $lead, string $desk): ?array
    {
        if (! self::isNarrowed($desk)) {
            return null;
        }

        $pic = self::DESK_PIC_COLUMN[$desk];

        $terlihat = self::picRowIds($pic, self::visibleSubcategoryIds($lead, $desk));

        if ($terlihat->isEmpty()) {
            return [];
        }

        // Dicocokkan lewat ORANGNYA, bukan baris agent-nya: sebagian orang
        // punya lebih dari satu baris support_agents untuk satu akun, dan
        // Subjek bisa menunjuk baris yang berbeda dari baris yang dipakai
        // orang itu saat bekerja. Alasan yang sama sudah ditulis panjang di
        // SupportAgent::serviceIdsFor().
        $userTerlihat = self::userIdsOf($terlihat);

        return SupportAgent::where('type', $desk)
            ->where(fn ($q) => $q->whereIn('id', $terlihat)->orWhereIn('user_id', $userTerlihat))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Baris agent yang jadi PIC pada Subjek AKTIF — dibatasi Subkategori
     * tertentu, atau seluruhnya kalau $subcategoryIds null.
     *
     * @param  Collection<int,int>|null  $subcategoryIds
     * @return Collection<int,int>
     */
    private static function picRowIds(string $picColumn, ?Collection $subcategoryIds): Collection
    {
        return ServiceCatalogSubject::where('is_active', true)
            ->whereNotNull($picColumn)
            ->when($subcategoryIds !== null, fn ($q) => $q->whereIn('subcategory_id', $subcategoryIds))
            ->distinct()
            ->pluck($picColumn);
    }

    /**
     * @param  Collection<int,int>  $rowIds
     * @return Collection<int,int>
     */
    private static function userIdsOf(Collection $rowIds): Collection
    {
        return SupportAgent::whereIn('id', $rowIds)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
    }
}

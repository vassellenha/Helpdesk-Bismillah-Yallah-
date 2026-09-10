<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\User;
use App\Support\TeamLeadScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Mesin yang menerjemahkan "Subkategori ini milik Team Lead itu" menjadi
 * "orang-orang inilah yang ia awasi". Diuji terpisah sebelum disambungkan ke
 * layar mana pun, karena setiap kekeliruan di sini muncul di tempat lain
 * sebagai kehilangan yang senyap — tabel yang lebih pendek dari seharusnya,
 * tanpa error dan tanpa pesan.
 *
 * Dua aturan yang paling mudah salah dan paling mahal kalau salah:
 *
 * 1. Yang BELUM ditugaskan tidak jadi milik siapa pun. Kalau ini terbalik,
 *    kelalaian Admin berubah jadi izin: setiap baris yang lupa diisi memberi
 *    wewenang kepada SEMUA Team Lead, tanpa satu pun tanda bahwa ia terbuka.
 * 2. Cakupan itu gabungan, bukan kepemilikan eksklusif. Seorang PIC yang
 *    bekerja di dua Subkategori milik dua Team Lead harus muncul di kedua
 *    dashboard. Aturan "semua Subkategorinya harus milik saya" akan membuat
 *    orang seperti itu tidak terlihat oleh siapa pun.
 */
final class TeamLeadScopeTest extends TestCase
{
    use MakesSupportDesks, RefreshDatabase;

    public function test_subkategori_tanpa_team_lead_tidak_masuk_cakupan_siapa_pun(): void
    {
        $a = $this->lead('Rizky');
        $b = $this->lead('Denny');
        $sub = $this->subkategori('Akun Email');

        $this->assertFalse(
            TeamLeadScope::visibleSubcategoryIds($a, 'bpo')->contains($sub->id),
            'Subkategori yang belum ditugaskan tidak boleh memberi wewenang kepada siapa pun',
        );
        $this->assertFalse(TeamLeadScope::visibleSubcategoryIds($b, 'bpo')->contains($sub->id));
    }

    public function test_subkategori_bertuan_hanya_masuk_cakupan_pemiliknya(): void
    {
        $a = $this->lead('Rizky');
        $b = $this->lead('Denny');
        $sub = $this->subkategori('Akun Email', $a);

        $this->assertTrue(TeamLeadScope::visibleSubcategoryIds($a, 'bpo')->contains($sub->id));
        $this->assertFalse(
            TeamLeadScope::visibleSubcategoryIds($b, 'bpo')->contains($sub->id),
            'Subkategori yang sudah bertuan tidak boleh terbaca Team Lead lain',
        );
    }

    /**
     * Agent BPO aktif yang tidak jadi PIC di Subjek manapun — kasus nyata di
     * data produksi (mis. orang yang baru diberi role Support BPO tapi belum
     * dipasang di katalog). Ia tidak masuk cakupan siapa pun, dan justru itu
     * yang menutup celah aslinya: Team Lead tidak bisa memindahkan tiket ke
     * orang yang tidak terdaftar di katalog.
     */
    public function test_agent_tanpa_subjek_apa_pun_tidak_masuk_cakupan_siapa_pun(): void
    {
        $a = $this->lead('Rizky');
        $b = $this->lead('Denny');
        $yatim = $this->deskAgent('bpo', 'Marcell Laforteza');

        $pic = $this->deskAgent('bpo', 'Genta Pratama');
        $this->subjek($this->subkategori('Akses SAP', $a), $pic);

        $this->assertNotContains(
            $yatim->id,
            TeamLeadScope::agentIds($a, 'bpo'),
            'agent yang tidak terdaftar di katalog manapun tidak boleh bisa dijadikan tujuan pemindahan tiket',
        );
        $this->assertNotContains($yatim->id, TeamLeadScope::agentIds($b, 'bpo'));

        // Yang memang terdaftar tetap terlihat oleh pemilik Subkategorinya saja.
        $this->assertContains($pic->id, TeamLeadScope::agentIds($a, 'bpo'));
        $this->assertNotContains(
            $pic->id,
            TeamLeadScope::agentIds($b, 'bpo'),
            'PIC Subkategori milik Team Lead lain tidak boleh bocor',
        );
    }

    /**
     * Sebagian orang punya DUA baris support_agents untuk satu akun (dobel
     * peran BPO & IT, atau baris lama yang tak dibereskan). Subjek bisa
     * menunjuk baris yang berbeda dari baris yang dipakai orang itu saat
     * bekerja — persis alasan yang sudah ditulis di SupportAgent::
     * serviceIdsFor(). Kalau pencocokannya per baris, tiketnya "hilang":
     * terlihat di notifikasi, tidak ada di layar Team Lead.
     */
    public function test_agent_dicocokkan_lewat_orangnya_bukan_barisnya(): void
    {
        $lead = $this->lead('Rizky');
        $pic = $this->deskAgent('bpo', 'Genta Pratama');

        $barisKedua = SupportAgent::create([
            'name' => 'Genta Pratama',
            'type' => 'bpo',
            'is_active' => true,
            'user_id' => $pic->user_id,
        ]);

        // Katalog menunjuk baris PERTAMA; baris kedua harus ikut terbawa.
        $this->subjek($this->subkategori('Akses SAP', $lead), $pic);

        $ids = TeamLeadScope::agentIds($lead, 'bpo');

        $this->assertContains($pic->id, $ids);
        $this->assertContains(
            $barisKedua->id,
            $ids,
            'baris agent kedua milik orang yang sama harus ikut masuk cakupan, kalau tidak tiketnya hilang dari layar Team Lead',
        );
    }

    /**
     * Team Lead yang aksesnya dicabut tidak bisa membuka Helpdesk sama
     * sekali, dan Subkategorinya TIDAK diwariskan ke Team Lead lain — sama
     * seperti Subkategori yang belum pernah ditugaskan. Admin harus
     * menugaskannya ulang; kolom "Belum ditugaskan" di layar Service Catalog
     * yang membuatnya kelihatan.
     */
    public function test_subkategori_milik_lead_nonaktif_tidak_diwariskan_ke_lead_lain(): void
    {
        $a = $this->lead('Rizky');
        $b = $this->lead('Denny');
        $sub = $this->subkategori('Akun Email', $a);

        $a->update(['helpdesk_access' => 'disabled']);

        $this->assertFalse(
            TeamLeadScope::visibleSubcategoryIds($b, 'bpo')->contains($sub->id),
            'Subkategori milik Team Lead yang nonaktif tidak boleh berpindah wewenang diam-diam',
        );
    }

    /**
     * Subjek yang dinonaktifkan tidak lagi menerima tiket, jadi PIC-nya tidak
     * lagi menjadi tanggung jawab siapa pun lewat Subjek itu.
     */
    public function test_agent_yang_hanya_pic_di_subjek_nonaktif_tidak_masuk_cakupan(): void
    {
        $a = $this->lead('Rizky');
        $b = $this->lead('Denny');
        $pic = $this->deskAgent('bpo', 'Rio Saputra');

        $this->subjek($this->subkategori('Akses SAP', $a), $pic, aktif: false);

        $this->assertNotContains($pic->id, TeamLeadScope::agentIds($a, 'bpo'));
        $this->assertNotContains(
            $pic->id,
            TeamLeadScope::agentIds($b, 'bpo'),
            'Subjek nonaktif tidak boleh memberi wewenang kepada Team Lead manapun',
        );
    }

    /**
     * Desk IT belum dibagi-bagi. Ia harus mengembalikan null — "tanpa
     * penyempitan" — bukan daftar kosong, yang akan mengosongkan seluruh
     * dashboard Tim IT tanpa satu pun error.
     */
    public function test_desk_it_tidak_disempitkan(): void
    {
        $lead = $this->lead('Rizky');

        $this->assertNull(TeamLeadScope::agentIds($lead, 'it'));
        $this->assertFalse(TeamLeadScope::isNarrowed('it'));
        $this->assertTrue(TeamLeadScope::isNarrowed('bpo'));
    }

    private function lead(string $nama): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'Team Lead BPO'],
            ['type' => 'system', 'status' => 'active'],
        );

        $user = User::factory()->create([
            'name' => $nama,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function subkategori(string $nama, ?User $lead = null): ServiceCatalogSubcategory
    {
        $service = ServiceCatalogService::firstOrCreate(['name' => 'SAP']);

        return ServiceCatalogSubcategory::create([
            'service_id' => $service->id,
            'name' => $nama,
            'team_lead_bpo_user_id' => $lead?->id,
        ]);
    }

    private function subjek(ServiceCatalogSubcategory $sub, SupportAgent $pic, bool $aktif = true): ServiceCatalogSubject
    {
        return ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $sub->service_id,
            'subcategory_id' => $sub->id,
            'name' => 'Subjek '.$pic->name.' '.random_int(1000, 9999),
            'requires_approval' => false,
            'support_agent_id' => $pic->id,
            'support_level' => 1,
            'is_active' => $aktif,
        ]);
    }
}

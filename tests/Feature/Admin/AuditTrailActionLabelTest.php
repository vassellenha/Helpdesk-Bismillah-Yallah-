<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Setiap aksi yang dicatat aplikasi harus punya label berbahasa manusia di
 * Audit Trail Viewer. Yang tidak terpetakan jatuh ke nilai mentahnya, sehingga
 * layar menampilkan "delete" dan "claim" berdampingan dengan "Tambah", "Edit",
 * dan "Sinkronisasi" — tanpa satu pun tanda bahwa ada yang terlewat.
 *
 * Ditemukan saat UAT test case 20.
 */
final class AuditTrailActionLabelTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_aksi_hapus_punya_label_berbahasa_indonesia(): void
    {
        $this->actingAsRole('admin');
        $this->catat('service_catalog', 'delete');

        $label = $this->labelAksiTerbaru();

        $this->assertSame('Hapus', $label);
    }

    public function test_aksi_klaim_tiket_punya_label_berbahasa_indonesia(): void
    {
        $this->actingAsRole('admin');
        $this->catat('ticket_support', 'claim');

        $this->assertSame('Klaim Tiket', $this->labelAksiTerbaru());
    }

    /**
     * Jaring pengaman: setiap nilai `action` yang benar-benar ditulis oleh kode
     * aplikasi harus punya terjemahan. Dipindai dari sumber, bukan didaftar
     * ulang di sini, supaya aksi baru yang lupa dipetakan langsung ketahuan.
     */
    public function test_setiap_aksi_yang_dicatat_aplikasi_punya_terjemahan(): void
    {
        $this->actingAsRole('admin');

        $mentah = [];

        foreach ($this->aksiYangDitulisAplikasi() as $action) {
            AuditTrail::query()->delete();
            $this->catat('service_catalog', $action);

            if ($this->labelAksiTerbaru() === $action) {
                $mentah[] = $action;
            }
        }

        $this->assertSame([], $mentah, 'aksi tanpa terjemahan: '.implode(', ', $mentah));
    }

    /**
     * Pilihan filter "Semua Aktivitas" dibangun dari daftarnya sendiri di sisi
     * React. Aksi yang ada di database tapi tidak ada di daftar itu tidak bisa
     * disaring sama sekali — daftar penghapusan jadi mustahil ditarik, tanpa
     * pesan apa pun yang menjelaskan kenapa.
     */
    public function test_filter_aktivitas_menawarkan_setiap_aksi_yang_dicatat_aplikasi(): void
    {
        $sumber = (string) file_get_contents(
            resource_path('js/components/admin/AuditTrailConsole.jsx')
        );

        preg_match('/const ACTION_KEYS = \{(.*?)\};/s', $sumber, $blok);
        $this->assertNotEmpty($blok, 'daftar ACTION_KEYS tidak ditemukan di AuditTrailConsole.jsx');

        preg_match_all('/^\s*([a-z_]+):/m', $blok[1], $cocok);
        $ditawarkan = $cocok[1];

        $hilang = array_values(array_diff($this->aksiYangDitulisAplikasi(), $ditawarkan));

        $this->assertSame([], $hilang, 'aksi tanpa pilihan filter: '.implode(', ', $hilang));
    }

    /** @return list<string> */
    private function aksiYangDitulisAplikasi(): array
    {
        $ditemukan = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($berkas as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/'action'\s*=>\s*'([a-z_]+)'/",
                (string) file_get_contents($file->getPathname()),
                $cocok
            );

            $ditemukan = [...$ditemukan, ...$cocok[1]];
        }

        $ditemukan = array_values(array_unique($ditemukan));
        sort($ditemukan);

        $this->assertNotEmpty($ditemukan, 'tidak ada aksi audit yang terbaca dari kode aplikasi');

        return $ditemukan;
    }

    private function catat(string $module, string $action): void
    {
        AuditTrail::create([
            'actor_id' => User::query()->value('id'),
            'module' => $module,
            'action' => $action,
            'target_type' => 'subject',
            'target_id' => 1,
            'target_name' => 'Subjek Uji',
            'description' => 'Aktivitas uji.',
        ]);
    }

    private function labelAksiTerbaru(): string
    {
        $response = $this->getJson('/admin/audit-trail/list')->assertOk();

        return (string) $response->json('logs.0.action_label');
    }
}

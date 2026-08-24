<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\SlaPolicy;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prioritas dan warnanya lahir dari SLA Policy, bukan dari daftar nama tetap.
 *
 * Yang diuji di sini adalah keluhan yang dilaporkan langsung dari layar:
 * mengganti nama "Critical" jadi "Kritikal" membuat prioritas paling genting
 * berubah abu-abu, dan prioritas baru buatan Admin tidak muncul sama sekali.
 * Dua-duanya berakar pada satu hal yang sama — nama dipakai sebagai kunci.
 */
final class PriorityRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SlaPolicy::query()->delete();
        PriorityRegistry::flush();
    }

    private function policy(string $priority, int $resolutionMinutes, string $status = 'active'): void
    {
        SlaPolicy::create([
            'policy_name' => $priority.' Policy',
            'priority' => $priority,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => $resolutionMinutes,
            'warning_threshold_percent' => 80,
            'status' => $status,
        ]);
        PriorityRegistry::flush();
    }

    public function test_empat_prioritas_bawaan_mempertahankan_warna_lamanya(): void
    {
        // Kompatibilitas mundur: layar yang sudah ada tidak boleh berubah rupa
        // hanya karena cara menghitung warnanya diganti.
        $this->policy('Critical', 240);
        $this->policy('High', 480);
        $this->policy('Medium', 2880);
        $this->policy('Low', 7200);

        $this->assertSame([
            'Critical' => '#dc2626',
            'High' => '#d97706',
            'Medium' => '#2563eb',
            'Low' => '#9ca3af',
        ], PriorityRegistry::colors());
    }

    public function test_mengganti_nama_prioritas_tidak_membuatnya_abu_abu(): void
    {
        // Keluhan aslinya: "Critical" diubah jadi "Kritikal", lalu warnanya
        // berubah dari merah ke abu-abu. Namanya berganti; kegentingannya tidak.
        $this->policy('Kritikal', 240);
        $this->policy('High', 480);
        $this->policy('Medium', 2880);
        $this->policy('Low', 7200);

        $this->assertSame('#dc2626', PriorityRegistry::colorFor('Kritikal'));
    }

    public function test_prioritas_baru_ikut_terdaftar_dan_terurut_menurut_ketatnya_sla(): void
    {
        $this->policy('Critical', 240);
        $this->policy('Low', 7200);
        // Dibuat Admin, lebih ketat dari Critical.
        $this->policy('Impossible', 150);

        $this->assertSame(['Impossible', 'Critical', 'Low'], PriorityRegistry::all());
        $this->assertSame('#dc2626', PriorityRegistry::colorFor('Impossible'));
    }

    public function test_lima_prioritas_tidak_ada_yang_berbagi_warna_sama(): void
    {
        $this->policy('Impossible', 150);
        $this->policy('Critical', 240);
        $this->policy('High', 480);
        $this->policy('Medium', 2880);
        $this->policy('Low', 7200);

        $colors = array_values(PriorityRegistry::colors());

        $this->assertCount(5, array_unique($colors));
    }

    public function test_policy_nonaktif_tidak_dianggap_prioritas_yang_bisa_dipakai(): void
    {
        $this->policy('Critical', 240);
        $this->policy('Arsip', 999, 'inactive');

        $this->assertSame(['Critical'], PriorityRegistry::all());
    }

    public function test_prioritas_tak_dikenal_dapat_warna_netral_bukan_error(): void
    {
        $this->policy('Critical', 240);

        // Tiket lama yang prioritasnya sudah tidak punya policy aktif.
        $this->assertSame('#64748b', PriorityRegistry::colorFor('SudahDihapus'));
    }

    public function test_tanpa_policy_aktif_memakai_daftar_cadangan(): void
    {
        $this->assertSame(PriorityRegistry::FALLBACK, PriorityRegistry::all());
    }
}

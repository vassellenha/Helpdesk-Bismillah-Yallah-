<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\SlaPolicy;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Panel "Rekomendasi Eskalasi" menyarankan sekelompok tiket naik satu tingkat
 * prioritas. Tingkat tujuannya dijepit agar tidak melewati prioritas
 * tertinggi — dan penjepit itulah yang keliru: kelompok yang SUDAH berada di
 * puncak tetap disarankan, dengan tujuan sama dengan asalnya.
 *
 * Di layar bunyinya "naik dari Critical ke Critical", lengkap dengan tombol
 * yang mengundang ditekan. Server tidak rusak — ia melewati tiket yang
 * prioritasnya sudah sama — tapi Team Lead diberi pekerjaan yang tidak
 * mengubah apa pun, pada panel yang justru dibaca saat sedang panik.
 */
final class EscalationRecsSkipTopPriorityTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_kelompok_yang_sudah_di_prioritas_tertinggi_tidak_disarankan_naik(): void
    {
        $this->policy('Critical', 60);
        $this->policy('High', 480);

        $it = $this->deskAgent('it', 'Agung Wijayanto');

        // Sudah di puncak — tidak ada lagi yang bisa dinaikkan.
        $this->breached($it, 'SAP', 'TRANSAKSI FICO', 'Critical');
        $this->breached($it, 'SAP', 'TRANSAKSI FICO', 'Critical');

        // Masih punya tingkat di atasnya — harus tetap disarankan.
        $this->breached($it, 'ELISA', 'VENDOR MANAGEMENT', 'High');

        $this->actingAsRole('team-lead');
        $recs = collect($this->getJson(route('team-lead.data-feed'))->assertOk()->json('escalationRecs'));

        $this->assertContains(
            'ELISA · VENDOR MANAGEMENT',
            $recs->pluck('name')->all(),
            'Kelompok yang masih punya tingkat di atasnya harus tetap disarankan.'
        );
        $this->assertNotContains(
            'SAP · TRANSAKSI FICO',
            $recs->pluck('name')->all(),
            'Kelompok yang sudah di prioritas tertinggi tidak boleh disarankan naik.'
        );

        foreach ($recs as $row) {
            $this->assertNotSame(
                $row['from'],
                $row['to'],
                "Rekomendasi \"{$row['name']}\" menyarankan naik ke prioritas yang sama dengan asalnya."
            );
        }
    }

    private function policy(string $priority, int $resolutionMinutes): SlaPolicy
    {
        PriorityRegistry::flush();

        return SlaPolicy::create([
            'policy_name' => 'Uji Rekomendasi '.$priority,
            'priority' => $priority,
            'service_type' => 'Incident',
            'response_time_minutes' => (int) round($resolutionMinutes / 4),
            'resolution_time_minutes' => $resolutionMinutes,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
    }

    /** Tiket aktif yang sudah melewati batas SLA, pada kelompok layanan tertentu. */
    private function breached($agent, string $service, string $subcategory, string $priority): void
    {
        $this->deskTicket($agent, [
            'service_name' => $service,
            'subcategory_name' => $subcategory,
            'priority' => $priority,
            'resolution_due_at' => now()->subHours(3),
            'warning_at' => now()->subHours(5),
            'response_due_at' => now()->subHours(6),
        ]);
    }
}

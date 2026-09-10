<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Layar Team Lead dirakit lewat satu pulau React yang props-nya disebut SATU
 * PER SATU di Blade. Kunci yang ditambahkan di controller tapi lupa
 * didaftarkan di sana tidak error di mana pun: React menerima `undefined`,
 * dan bagian yang bergantung padanya hilang begitu saja dari layar.
 *
 * Ditemukan persis begitu — `scopeEmpty` sudah dikirim dashboardData(), tapi
 * banner "belum ada Sub Kategori yang ditugaskan" tidak pernah muncul, dan
 * dashboard yang kosong tampak seperti tim yang sedang tidak punya pekerjaan.
 */
final class TeamLeadWorkspacePropsTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_props_halaman_memuat_setiap_kunci_yang_dikirim_controller(): void
    {
        $this->actingAsRole('team-lead-bpo');

        $html = $this->get(route('dashboard.team-lead-bpo'))->assertOk()->getContent();

        preg_match('/data-props="([^"]*)"/', (string) $html, $cocok);
        $this->assertNotEmpty($cocok, 'pulau TeamLeadWorkspace tidak ditemukan di halaman');

        $props = json_decode(html_entity_decode($cocok[1], ENT_QUOTES), true);

        foreach (['scopeEmpty', 'metrics', 'workload', 'agentOptions', 'picRows', 'escalations'] as $kunci) {
            $this->assertArrayHasKey(
                $kunci,
                $props,
                "kunci \"{$kunci}\" tidak diteruskan Blade ke React — bagian layar yang memakainya akan hilang tanpa error",
            );
        }
    }

    public function test_lead_tanpa_jatah_subkategori_ditandai_cakupan_kosong(): void
    {
        $this->actingAsRole('team-lead-bpo');

        $html = (string) $this->get(route('dashboard.team-lead-bpo'))->assertOk()->getContent();

        preg_match('/data-props="([^"]*)"/', $html, $cocok);
        $props = json_decode(html_entity_decode($cocok[1], ENT_QUOTES), true);

        $this->assertTrue(
            $props['scopeEmpty'],
            'Team Lead yang belum kebagian Subkategori harus ditandai, bukan dibiarkan membaca dashboard nol tanpa keterangan',
        );
    }
}

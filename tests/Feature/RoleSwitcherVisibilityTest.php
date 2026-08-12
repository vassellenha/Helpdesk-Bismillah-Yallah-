<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Tombol switch role mengikuti role yang BENAR-BENAR dipegang.
 *
 * Aturannya dua baris: satu role → tombolnya tidak muncul sama sekali; lebih
 * dari satu → muncul, berisi persis role orang itu dan bukan yang lain.
 *
 * Dulu tombol ini menampilkan ketujuh role kepada siapa pun, karena memang
 * belum ada yang bisa ditanyai "kamu siapa". Enam dari tujuh kartunya hanya
 * berujung penolakan begitu login dipasang.
 */
final class RoleSwitcherVisibilityTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_pemegang_satu_role_tidak_melihat_tombolnya(): void
    {
        $this->actingAsRole('requester');

        $this->get(route('dashboard.requester'))
            ->assertOk()
            ->assertDontSee('data-react="RoleSwitcher"', escape: false);
    }

    public function test_pemegang_dua_role_melihat_tombolnya(): void
    {
        $this->actingAsRole('requester', 'approver');

        $this->get(route('dashboard.requester'))
            ->assertOk()
            ->assertSee('data-react="RoleSwitcher"', escape: false);
    }

    /**
     * Isinya persis role yang dipegang — tidak kurang, dan yang penting: tidak
     * lebih. Kartu ke layar yang bukan haknya hanya berujung 403.
     */
    public function test_daftarnya_hanya_berisi_role_yang_dipegang(): void
    {
        $user = $this->actingAsRole('requester', 'team-lead');

        $entries = RoleRegistry::switcherEntriesFor($user->fresh());

        $this->assertSame(['requester', 'team-lead'], $entries->pluck('key')->all());
    }

    /** Urutannya mengikuti config, bukan urutan penyematan di basis data. */
    public function test_urutannya_stabil_terlepas_dari_urutan_penyematan(): void
    {
        $user = $this->actingAsRole('admin', 'requester', 'approver');

        $this->assertSame(
            ['requester', 'approver', 'admin'],
            RoleRegistry::switcherEntriesFor($user->fresh())->pluck('key')->all(),
        );
    }

    public function test_portal_hanya_menawarkan_kartu_role_yang_dipegang(): void
    {
        $this->actingAsRole('requester', 'approver');

        $this->get(route('portal.index'))
            ->assertOk()
            ->assertSee('Requester')
            ->assertSee('Approver')
            // Konsol admin tidak boleh ditawarkan kepada yang bukan Admin.
            ->assertDontSee('Admin Console');
    }

    public function test_setiap_entri_menunjuk_layar_pertama_rolenya(): void
    {
        $user = $this->actingAsRole('requester', 'admin');

        $urls = RoleRegistry::switcherEntriesFor($user->fresh())->pluck('url');

        $this->assertContains(route('dashboard.requester'), $urls);
        $this->assertContains(route('admin.dashboard'), $urls);
    }
}

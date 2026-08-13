<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Identitas pemakai di bilah atas konsol EVA — sejajar dengan role lain.
 *
 * Datanya datang dari komposer `layouts.eva`, bukan dari controller
 * masing-masing. Itu yang membuat tes ini memeriksa BEBERAPA layar sekaligus:
 * konsol ini 13 layar dengan 13 controller, dan prop yang ditempel satu per
 * satu pasti ada yang terlewat — layar yang terlewat kehilangan menu profilnya
 * tanpa memunculkan error apa pun.
 */
final class EvaUserMenuTest extends TestCase
{
    use ActsAsEvaAdmin, RefreshDatabase;

    public function test_menu_profil_muncul_di_seluruh_layar_konsol(): void
    {
        $admin = $this->actingAsEvaAdmin();

        foreach (['eva.coverage', 'eva.unanswered', 'eva.preview', 'eva.faq'] as $layar) {
            $response = $this->get(route($layar))->assertOk();

            $response->assertSee('data-react="UserMenu"', false);
            $response->assertSee($admin->name, false);

            // URL-nya duduk di dalam data-props, jadi garis miringnya ter-escape
            // JSON — dicocokkan dalam bentuk itu, bukan bentuk aslinya.
            $response->assertSee(str_replace('/', '\\/', route('eva.profile')), false);
        }
    }

    public function test_inisial_dihitung_dari_nama(): void
    {
        $admin = $this->actingAsEvaAdmin();
        $admin->update(['name' => 'Nina Amelia']);

        $this->get(route('eva.coverage'))->assertOk()->assertSee('NA', false);
    }
}

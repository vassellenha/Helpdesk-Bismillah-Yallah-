<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Tombol "⇄ Switch Role" di konsol EVA.
 *
 * Yang dikunci di sini bukan tampilannya, melainkan satu hal yang membuat
 * tombol itu ada gunanya: ia harus tersedia di SETIAP layar konsol, bukan
 * cuma di satu halaman. Konsol EVA punya 13 menu dan bukan SPA — kalau
 * tombolnya cuma menempel di satu view, admin yang sedang di Article Library
 * harus mundur dulu ke layar lain untuk bisa berpindah peran, dan justru itu
 * yang membuat orang berhenti memakainya untuk mengecek tampilan karyawan.
 *
 * Karena itu tesnya menyapu beberapa layar sekaligus: itu cara membuktikan
 * tombolnya ikut LAYOUT, bukan ikut satu halaman.
 */
final class RoleSwitcherTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    /** Layar-layar yang mewakili tiap kelompok menu di sidebar EVA. */
    private const CONSOLE_ROUTES = [
        'eva.articles',
        'eva.faq',
        'eva.documents',
        'eva.preview',
        'eva.taxonomy',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsEvaAdmin();
    }

    public function test_switcher_muncul_di_setiap_layar_konsol(): void
    {
        foreach (self::CONSOLE_ROUTES as $name) {
            $response = $this->get(route($name));

            $response->assertOk();
            $response->assertSee('data-react="RoleSwitcher"', escape: false);
        }
    }

    public function test_switcher_menandai_eva_sebagai_peran_aktif(): void
    {
        $response = $this->get(route('eva.articles'));

        // Props-nya JSON di dalam atribut HTML, jadi tanda kutipnya sudah
        // di-escape Blade — dicocokkan apa adanya, bukan versi mentahnya.
        $response->assertSee('&quot;current&quot;:&quot;eva&quot;', escape: false);
    }

    public function test_switcher_membawa_tujuan_peran_lain(): void
    {
        $response = $this->get(route('eva.articles'));

        // json_encode meng-escape garis miring, jadi "/dashboard/requester"
        // muncul sebagai "\/dashboard\/requester" di dalam atribut.
        $requesterUrl = str_replace('/', '\/', route('dashboard.requester'));

        $response->assertSee($requesterUrl, escape: false);
        $response->assertSee(str_replace('/', '\/', route('portal.index')), escape: false);
    }
}

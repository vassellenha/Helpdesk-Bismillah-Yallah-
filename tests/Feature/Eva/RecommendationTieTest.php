<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Layar Ticket Recommendation harus menjanjikan hal yang benar-benar terjadi.
 *
 * Katalog ini penuh subject yang hanya berbeda satu kata terakhir — "Maintain
 * Reconcilliation Account for Vendor" dan "… for Customer". Kalimat tanpa kata
 * pembeda itu cocok sama kuat untuk keduanya, dan terbaik() memang MENOLAK
 * memilih; menebak salah satunya berarti tiket mendarat di tim yang salah tanpa
 * ada yang memeriksa.
 *
 * Yang salah sebelumnya bukan penolakannya, melainkan layarnya: kedua calon
 * diberi lencana hijau "akan terisi otomatis" — dua janji untuk sesuatu yang
 * tidak pernah terjadi, tanpa satu pun keterangan kenapa.
 */
final class RecommendationTieTest extends TestCase
{
    use ActsAsEvaAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();
        $this->seedSubjects(['Maintain Reconcilliation Account for Vendor', 'Maintain Reconcilliation Account for Customer']);
    }

    public function test_calon_yang_seri_tidak_dijanjikan_terisi_otomatis(): void
    {
        $this->actingAsEvaAdmin();

        $calon = $this->periksa('Maintain Reconcilliation Account for');

        $this->assertGreaterThanOrEqual(2, count($calon));
        $this->assertSame([false, false], [$calon[0]['is_auto_fill'], $calon[1]['is_auto_fill']]);
        $this->assertSame([true, true], [$calon[0]['is_tied'], $calon[1]['is_tied']]);

        // Dan memang tidak ada yang terisi — layarnya sekarang sejalan dengan
        // apa yang dilakukan terbaik().
        $this->assertNull(app(SubjectSearch::class)->terbaik('Maintain Reconcilliation Account for'));
    }

    public function test_pemenang_telak_tetap_dijanjikan_terisi_otomatis(): void
    {
        $this->actingAsEvaAdmin();

        $calon = $this->periksa('Maintain Reconcilliation Account for Vendor');

        $this->assertTrue($calon[0]['is_auto_fill'], 'Calon yang menang telak harus tetap mengisi otomatis.');
        $this->assertFalse($calon[0]['is_tied']);
        $this->assertSame('Maintain Reconcilliation Account for Vendor', $calon[0]['subject']);
    }

    public function test_calon_kedua_tidak_pernah_dijanjikan_mengisi(): void
    {
        $this->actingAsEvaAdmin();

        $calon = $this->periksa('Maintain Reconcilliation Account for Vendor');

        // Hanya yang teratas yang bisa mengisi kolom. Menandai calon kedua
        // hijau berarti menjanjikan sesuatu yang tidak pernah dilakukan.
        foreach (array_slice($calon, 1) as $lain) {
            $this->assertFalse($lain['is_auto_fill'], "\"{$lain['subject']}\" tidak boleh ditandai terisi otomatis.");
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function periksa(string $pertanyaan): array
    {
        return $this->postJson(route('eva.recommendation.test'), ['question' => $pertanyaan])
            ->assertOk()
            ->json('candidates');
    }

    /** @param string[] $names */
    private function seedSubjects(array $names): void
    {
        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $category = IssueCategory::create(['name' => 'Service Request']);
        $sub = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Finance']);

        foreach ($names as $name) {
            ServiceCatalogSubject::create([
                'issue_category_id' => $category->id,
                'service_id' => $service->id,
                'subcategory_id' => $sub->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}

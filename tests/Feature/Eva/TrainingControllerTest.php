<?php

namespace Tests\Feature\Eva;

use App\Services\Knowledge\AnswerSourceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Tes HTTP pertama untuk konsol EVA — menegakkan pola: hantam route sungguhan,
 * periksa kontrak JSON, lalu pastikan efek sampingnya nyata di layanan.
 *
 * Fokus TrainingController: endpoint toggle harus benar-benar mengubah
 * AnswerSourceSettings, bukan sekadar membalas 200.
 */
final class TrainingControllerTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsEvaAdmin();
    }

    public function test_halaman_training_tampil(): void
    {
        $this->get('/eva/training')->assertOk()->assertSee('Training Overview');
    }

    public function test_toggle_mematikan_sumber_secara_nyata(): void
    {
        $response = $this->postJson('/eva/api/training/toggle', [
            'source' => 'faqs',
            'enabled' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('source', 'faqs')
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('sources.faqs', false)
            ->assertJsonPath('sources.articles', true);

        // Kontrak JSON saja tidak cukup — buktikan layanannya benar-benar berubah.
        $this->assertFalse(app(AnswerSourceSettings::class)->faqsEnabled());
    }

    public function test_toggle_menolak_sumber_tak_dikenal(): void
    {
        $this->postJson('/eva/api/training/toggle', [
            'source' => 'tiket',
            'enabled' => true,
        ])->assertStatus(422);
    }

    public function test_toggle_mewajibkan_enabled_boolean(): void
    {
        $this->postJson('/eva/api/training/toggle', [
            'source' => 'articles',
        ])->assertStatus(422);
    }
}

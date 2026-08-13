<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Models\User;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ajakan menilai tidak boleh muncul lagi untuk MATERI yang sudah dinilai orang
 * itu.
 *
 * Penilaian tersimpan per baris kb_answer_logs, sementara tiap pertanyaan
 * melahirkan baris baru. Akibatnya karyawan yang kemarin sudah memberi bintang
 * dan menulis ulasan untuk "SOP Reset Password SAP" disodori bintang lagi hari
 * ini begitu ia menanyakan hal serupa — EVA terasa tidak mengingat apa pun, dan
 * pekerjaan yang sudah dikerjakan ditagih ulang.
 */
final class RatingSekaliPerMateriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();
        Http::preventStrayRequests();
    }

    public function test_materi_yang_sudah_dinilai_tidak_diminta_dinilai_lagi(): void
    {
        $penanya = User::factory()->create();
        $artikel = $this->artikel();
        $this->searchReturns($artikel);

        // Hari pertama: bertanya, lalu memberi bintang.
        $kemarin = $this->responder()->jawab('cara reset password sap', null, $penanya);
        $this->assertNull($kemarin->previousStars, 'Pertama kali ditanya, bintang harus ditawarkan.');

        AnswerRating::create([
            'answer_log_id' => $kemarin->answerLogId,
            'rated_by' => $penanya->id,
            'stars' => 4,
        ]);

        // Hari kedua: pertanyaan berbeda, artikel yang sama.
        $hariIni = $this->responder()->jawab('lupa kata sandi sap bagaimana', null, $penanya);

        $this->assertSame(4, $hariIni->previousStars);
        $this->assertNotSame($kemarin->answerLogId, $hariIni->answerLogId, 'Barisnya memang baru — itulah sebabnya dulu terlewat.');
    }

    public function test_materi_lain_tetap_diminta_dinilai(): void
    {
        $penanya = User::factory()->create();
        $artikel = $this->artikel();
        $this->searchReturns($artikel);

        $pertama = $this->responder()->jawab('cara reset password sap', null, $penanya);
        AnswerRating::create(['answer_log_id' => $pertama->answerLogId, 'rated_by' => $penanya->id, 'stars' => 5]);

        // Artikel berbeda: penilaiannya belum ada, jadi bintang tetap ditawarkan.
        $lain = Article::create(['title' => 'Panduan VPN', 'body' => 'Isi panduan VPN untuk bekerja dari rumah.', 'status' => 'published']);
        $this->searchReturns($lain);

        $this->assertNull($this->responder()->jawab('cara pakai vpn', null, $penanya)->previousStars);
    }

    public function test_penilaian_orang_lain_tidak_ikut_membungkam_bintang(): void
    {
        $artikel = $this->artikel();
        $this->searchReturns($artikel);

        $andi = User::factory()->create();
        $jawabAndi = $this->responder()->jawab('cara reset password sap', null, $andi);
        AnswerRating::create(['answer_log_id' => $jawabAndi->answerLogId, 'rated_by' => $andi->id, 'stars' => 5]);

        // Budi belum pernah menilai materi ini — dia tetap harus ditanya.
        $budi = User::factory()->create();

        $this->assertNull($this->responder()->jawab('cara reset password sap', null, $budi)->previousStars);
    }

    public function test_penilaian_ikut_terbawa_ke_payload_layar(): void
    {
        $penanya = User::factory()->create();
        $this->searchReturns($this->artikel());

        $jawab = $this->responder()->jawab('cara reset password sap', null, $penanya);
        AnswerRating::create(['answer_log_id' => $jawab->answerLogId, 'rated_by' => $penanya->id, 'stars' => 3]);

        $payload = $this->responder()->jawab('reset sandi sap', null, $penanya)->toArray();

        $this->assertSame(3, $payload['previous_stars']);
    }

    private function artikel(): Article
    {
        return Article::create([
            'title' => 'SOP Reset Password SAP',
            'body' => 'Buka Portal SSO, pilih Akun Saya, lalu Ubah Password SAP.',
            'status' => 'published',
        ]);
    }

    private function responder(): EvaResponder
    {
        return $this->app->make(EvaResponder::class);
    }

    private function searchReturns(Article|Faq $materi): void
    {
        $search = new class implements KnowledgeSearch
        {
            public array $hits = [];

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return $this->hits;
            }
        };

        $search->hits = [new SearchHit(
            sourceType: $materi::class,
            sourceId: $materi->id,
            title: $materi->title,
            answer: 'Buka Portal SSO, pilih Akun Saya, lalu Ubah Password SAP.',
            confidence: 90,
            catalogSubjectId: null,
        )];

        $this->app->instance(KnowledgeSearch::class, $search);
    }
}

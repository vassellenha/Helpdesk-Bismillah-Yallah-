<?php

namespace Database\Seeders;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use App\Models\Knowledge\TestCase;
use App\Models\ServiceCatalogSubject;
use App\Models\User;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\DocumentIndexer;
use App\Services\Knowledge\EvaReply;
use App\Services\Knowledge\EvaResponder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengisi Knowledge Base EVA.
 *
 * SENGAJA TIDAK dipanggil dari DatabaseSeeder: seeder tim mengisi katalog &
 * tiket, dan EVA tidak boleh memaksa siapa pun menjalankan ulang keduanya.
 * Jalankan sendiri:
 *
 *     php artisan db:seed --class=KnowledgeBaseSeeder
 *
 * Yang membedakan seeder ini dari data contoh mockup: artikel di sini benar-
 * benar DILAHIRKAN dari dokumen lewat DocumentIndexer, dan log jawaban benar-
 * benar dihasilkan dengan menjalankan pertanyaan lewat EvaResponder. Kalau
 * salah satu jalur itu rusak, seeder ini gagal — bukan diam-diam menghasilkan
 * data yang tampak benar.
 */
class KnowledgeBaseSeeder extends Seeder
{
    public function __construct(
        private readonly DocumentIndexer $indexer,
        private readonly EvaResponder $responder,
        private readonly CoverageCalculator $coverage,
    ) {}

    public function run(): void
    {
        $data = require database_path('seeders/data/knowledge-base.php');

        $this->reset();

        $author = User::where('email', 'marcell.laforteza@adhi.co.id')->firstOrFail();
        $askers = User::whereIn('email', [
            'andi.pratama@adhi.co.id',
            'karina.putri@adhi.co.id',
            'rizky.hidayat@adhi.co.id',
        ])->orderBy('id')->get();

        $this->seedDocuments($data['documents'], $author);
        $this->seedFaqs($data['faqs'], $author);
        $this->replayQuestions($data['replayed_questions'], $askers);

        // Riwayat coverage SENGAJA tidak diisi di sini. Grafik tren hanya boleh
        // memuat angka yang pernah benar-benar terjadi — barisnya lahir dari
        // `php artisan eva:snapshot-coverage`, bukan dari data contoh.

        $summary = $this->coverage->summary();
        $this->command?->info(sprintf(
            'KB terisi: %d dokumen, %d artikel, %d FAQ, %d log jawaban. Coverage %d%% (%d/%d subject).',
            Document::count(),
            Article::count(),
            Faq::count(),
            AnswerLog::count(),
            $summary['percent'],
            $summary['covered_subjects'],
            $summary['total_subjects'],
        ));
    }

    /**
     * Hanya tabel kb_*. Katalog & tiket milik tim tidak pernah disentuh.
     *
     * kb_coverage_snapshots sengaja TIDAK ikut dikosongkan: isinya angka yang
     * pernah benar-benar terjadi dan tidak bisa dihitung ulang dari apa pun.
     * Semua tabel lain di bawah ini bisa dibangun ulang oleh seeder ini.
     */
    private function reset(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['kb_answer_ratings', 'kb_answer_logs', 'kb_conversation_turns', 'kb_conversations',
            'kb_test_cases', 'kb_chunks', 'kb_article_subject', 'kb_articles', 'kb_faqs',
            'kb_documents'] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedDocuments(array $documents, User $author): void
    {
        foreach ($documents as $row) {
            $document = Document::create([
                'name' => $row['name'],
                'original_filename' => str($row['name'])->slug().'.'.strtolower($row['extension']),
                'extension' => $row['extension'],
                'size_bytes' => mb_strlen($row['text']),
                'extracted_text' => $row['text'],
                'catalog_subject_id' => $this->subjectId($row['subject']),
                'status' => Document::STATUS_PROCESSING,
                'tags' => $row['tags'],
                'uploaded_by' => $author->id,
            ]);

            $indexed = $this->indexer->index($document);

            if (! $indexed->isIndexed()) {
                throw new RuntimeException("Dokumen \"{$row['name']}\" gagal diindeks — jalur dokumen → artikel putus.");
            }

            // Artikel lahir sebagai draf. Di sini langsung ditayangkan supaya
            // KB punya isi; di aplikasi nyata ini keputusan admin di editor.
            $article = $indexed->article;

            if ($article === null) {
                throw new RuntimeException("Dokumen \"{$row['name']}\" terindeks tapi tidak melahirkan artikel.");
            }

            $article->update(['status' => Article::STATUS_PUBLISHED]);
        }
    }

    private function seedFaqs(array $faqs, User $author): void
    {
        foreach ($faqs as $row) {
            $faq = Faq::create([
                'question' => $row['question'],
                'answer' => $row['answer'],
                'catalog_subject_id' => $this->subjectId($row['subject']),
                'is_eva_visible' => $row['is_eva_visible'],
                'tags' => $row['tags'],
                'author_id' => $author->id,
            ]);

            foreach ($row['tests'] as $question) {
                TestCase::create([
                    'testable_type' => Faq::class,
                    'testable_id' => $faq->id,
                    'question' => $question,
                ]);
            }
        }
    }

    /**
     * Menjalankan pertanyaan lewat EvaResponder yang sesungguhnya.
     *
     * Setiap pengulangan dicatat sebagai percakapan tersendiri, persis seperti
     * kalau ditanyakan karyawan berbeda — sehingga Top Questions dan Unanswered
     * Questions terbentuk dari perilaku, bukan dari angka yang diketik.
     */
    private function replayQuestions(array $questions, $askers): void
    {
        foreach ($questions as $index => $row) {
            for ($i = 0; $i < $row['repeat']; $i++) {
                $asker = $askers[($index + $i) % $askers->count()];
                $askedAt = now()->subDays(($index + $i) % 30);

                $conversation = Conversation::create([
                    'user_id' => $asker->id,
                    'requester_name' => $asker->name,
                    'department' => null,
                    'outcome' => Conversation::OUTCOME_OPEN,
                    'started_at' => $askedAt,
                ]);

                $reply = $this->responder->jawab($row['q'], $conversation, $asker);

                // Log jawaban ikut dimundurkan ke waktu percakapannya.
                //
                // Tanpa ini, `started_at` mundur sampai 30 hari sementara
                // `created_at` log tetap "sekarang" — Log Percakapan menulis
                // "2 hari lalu" sedangkan Analytics menaruh peristiwa yang
                // SAMA di hari ini. Dua layar yang berbeda pendapat soal kapan
                // sesuatu terjadi lebih buruk daripada tidak punya grafik.
                AnswerLog::whereKey($reply->answerLogId)
                    ->update(['created_at' => $askedAt, 'updated_at' => $askedAt]);

                // Hasil percakapan tidak di-set di sini: EvaResponder yang
                // menstempelnya, supaya seeder dan EVA Preview tidak pernah
                // punya pendapat berbeda.
                $this->recordTurns($conversation, $row['q'], $reply);

                if ($row['stars'] !== null && $reply->type === EvaReply::TYPE_ANSWER) {
                    AnswerRating::create([
                        'answer_log_id' => $reply->answerLogId,
                        'rated_by' => $asker->id,
                        'stars' => $row['stars'],
                        'created_at' => $askedAt,
                        'updated_at' => $askedAt,
                    ]);
                }
            }
        }
    }

    private function recordTurns(Conversation $conversation, string $question, EvaReply $reply): void
    {
        ConversationTurn::create([
            'conversation_id' => $conversation->id,
            'ordinal' => 0,
            'role' => ConversationTurn::ROLE_USER,
            'message' => $question,
        ]);

        ConversationTurn::create([
            'conversation_id' => $conversation->id,
            'ordinal' => 1,
            'role' => ConversationTurn::ROLE_EVA,
            'message' => $reply->text,
            'source_type' => $reply->hit?->sourceType,
            'source_id' => $reply->hit?->sourceId,
            'confidence' => $reply->hit?->confidence,
            'is_clarifying' => $reply->type === EvaReply::TYPE_CLARIFY,
        ]);
    }



    /**
     * Subject katalog dicari berdasarkan nama. Gagal keras kalau tidak ketemu:
     * tautan yang diam-diam null membuat materi tidak terhitung di coverage,
     * dan itu hanya ketahuan berbulan-bulan kemudian sebagai angka yang aneh.
     */
    private function subjectId(string $name): int
    {
        $subject = ServiceCatalogSubject::where('name', $name)->orderBy('id')->first();

        if ($subject === null) {
            throw new RuntimeException("Subject katalog \"{$name}\" tidak ada di service_catalog_subjects. Jalankan ServiceCatalogSeeder milik tim lebih dulu.");
        }

        return $subject->id;
    }
}

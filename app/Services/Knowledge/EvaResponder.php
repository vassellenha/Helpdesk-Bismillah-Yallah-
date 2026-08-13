<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Conversation;
use App\Models\User;

/**
 * Satu giliran percakapan EVA, dari pertanyaan sampai baris kb_answer_logs.
 *
 * Semua jalur — termasuk yang tidak menemukan jawaban — WAJIB melewati kelas
 * ini, karena pencatatan log-nya di sini. Pertanyaan tak terjawab yang tidak
 * tercatat berarti Unanswered Questions dan Coverage Dashboard bohong.
 */
final class EvaResponder
{
    private const NO_ANSWER_TEXT = 'Maaf, saya belum menemukan jawaban yang sesuai di Knowledge Base. Saya bisa siapkan draf tiketnya agar Anda tinggal memeriksa dan mengirim.';

    private const CLARIFY_TEXT = 'Supaya saya tidak salah memberi panduan, layanan mana yang sedang bermasalah?';

    /**
     * Kandidat yang dibaca sekaligus saat merangkum.
     *
     * Bukan "seluruh KB": hari ini isinya 9 artikel dan muat semua, tapi target
     * coverage-nya 140 subject. Mengirim semuanya tiap pertanyaan berarti biaya
     * dan waktu tunggu yang naik terus sampai akhirnya melewati batas panjang
     * prompt — kegagalan yang datangnya belakangan dan menimpa semua orang
     * sekaligus. Delapan kandidat teratas adalah "seluruh yang relevan".
     */
    private const SYNTHESIS_CANDIDATES = 8;

    /**
     * Keyakinan seadanya masih boleh ikut dibaca — justru di situ gunanya
     * merangkum: potongan yang sendirian tidak meyakinkan bisa jadi keping yang
     * melengkapi. Di bawah ambang ini isinya sudah tidak nyambung, dan
     * menyodorkannya hanya memancing jawaban karangan.
     */
    private const SYNTHESIS_FLOOR = 20;

    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly VagueQuestionDetector $vagueDetector,
        private readonly SubjectSearch $subjects,
        private readonly AnswerParaphraser $paraphraser,
        private readonly KnowledgeSynthesizer $synthesizer,
        private readonly SmallTalkDetector $smallTalk,
    ) {}

    public function jawab(string $question, ?Conversation $conversation = null, ?User $asker = null): EvaReply
    {
        // Sapaan diperiksa paling awal: "Halo" bukan pertanyaan yang gagal
        // dijawab, jadi ia tidak boleh menempuh pencarian, tidak boleh
        // menghasilkan tawaran tiket, dan tidak boleh masuk Unanswered
        // Questions sebagai celah materi yang mustahil ditutup.
        if ($balasan = $this->smallTalk->balasan($question)) {
            return $this->smallTalkReply($balasan, $question, $conversation, $asker);
        }

        if ($this->vagueDetector->isVague($question)) {
            return $this->clarify($question, $conversation, $asker);
        }

        $hits = $this->search->cari($question, self::SYNTHESIS_CANDIDATES);
        $best = $hits[0] ?? null;

        // Merangkum lebih dulu, sebelum ambang keyakinan diperiksa. Jawaban
        // yang tersebar di beberapa dokumen tidak pernah membuat satu pun di
        // antaranya terlihat meyakinkan sendirian — persis kasus yang dulu
        // berakhir "belum menemukan jawaban" padahal materinya ada.
        $rangkuman = $this->synthesizer->rangkum($question, $this->passages($hits));

        if ($rangkuman !== null && $best !== null) {
            return $this->answer($best, $question, $conversation, $asker, $rangkuman);
        }

        if ($best === null || $best->confidence < KnowledgeSearch::MIN_CONFIDENCE) {
            // Sebelum menyerah: apakah pertanyaannya jelas menunjuk satu masalah
            // tapi ambigu antar layanan (reset password → SAP atau SILO)? Kalau
            // ya, bertanya balik lebih berguna daripada langsung menawarkan draf.
            $tied = $this->subjects->calonSeri($question);

            if ($tied !== []) {
                return $this->clarifySubject($question, $tied, $conversation, $asker);
            }

            return $this->noAnswer($question, $conversation, $asker);
        }

        return $this->answer($best, $question, $conversation, $asker);
    }

    /**
     * Potongan yang layak dibaca mesin rangkuman.
     *
     * @param  SearchHit[]  $hits
     * @return list<array{title:string,text:string}>
     */
    private function passages(array $hits): array
    {
        return array_values(array_map(
            fn (SearchHit $h) => ['title' => $h->title, 'text' => $h->answer],
            array_filter($hits, fn (SearchHit $h) => $h->confidence >= self::SYNTHESIS_FLOOR),
        ));
    }

    /**
     * Basa-basi tetap dicatat — invarian "setiap jalur meninggalkan satu baris"
     * berlaku di sini juga, dan Log Percakapan yang melompati sapaan akan
     * terbaca seperti percakapan yang terpotong. Yang dijaga adalah jenisnya:
     * outcome-nya sendiri, supaya tidak terhitung sebagai pertanyaan tak
     * terjawab maupun sebagai keberhasilan menjawab.
     */
    private function smallTalkReply(string $text, string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_SMALL_TALK, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_SMALL_TALK,
            text: $text,
            hit: null,
            answerLogId: $log->id,
        );
    }

    private function answer(SearchHit $hit, string $question, ?Conversation $conversation, ?User $asker, ?string $synthesized = null): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_ANSWERED, $conversation, $asker, [
            'source_type' => $hit->sourceType,
            'source_id' => $hit->sourceId,
            'catalog_subject_id' => $hit->catalogSubjectId,
            'confidence' => $hit->confidence,
        ]);

        // Hanya jawaban KB yang diparafrase. Teks clarify dan no-answer di
        // kelas ini kalimat tetap yang sudah dipilih kata per kata — menulis
        // ulangnya tidak memperbaiki apa pun dan hanya menambah biaya.
        //
        // Yang dicatat ke kb_answer_logs tetap $hit->answer aslinya (lewat
        // source_id di log): Analytics dan Rating menilai materi KB-nya, bukan
        // hasil rias kalimatnya.
        // Rangkuman sudah berupa kalimat yang disusun sendiri oleh model —
        // memparafrasenya lagi hanya menambah satu panggilan berbayar dan satu
        // kesempatan lagi bagi fakta untuk bergeser.
        return new EvaReply(
            type: EvaReply::TYPE_ANSWER,
            text: $synthesized ?? $this->paraphraser->parafrase($hit->answer),
            hit: $hit,
            answerLogId: $log->id,
            isHedged: $hit->confidence < KnowledgeSearch::HEDGE_CONFIDENCE,
            previousStars: $asker ? AnswerRating::starsGivenBy($asker, $hit->sourceType, $hit->sourceId) : null,
        );
    }

    private function clarify(string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_CLARIFY, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_CLARIFY,
            text: self::CLARIFY_TEXT,
            hit: null,
            answerLogId: $log->id,
            clarifyOptions: $this->vagueDetector->clarifyOptions(),
        );
    }

    /**
     * Bertanya balik saat dua subject seri sama kuat.
     *
     * Bedanya dengan clarify() biasa: itu untuk keluhan generik tanpa nama
     * layanan ("tidak bisa login" → tawarkan semua layanan). Ini untuk
     * pertanyaan yang JELAS soalnya tapi ambigu cabangnya ("reset password" →
     * SAP atau SILO). Pilihannya bukan daftar layanan umum, melainkan pembeda
     * nyata antar calon seri.
     */
    private function clarifySubject(string $question, array $tied, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_CLARIFY, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_CLARIFY,
            text: 'Ini soal "'.$tied[0]->subject.'" — untuk layanan yang mana?',
            hit: null,
            answerLogId: $log->id,
            clarifyOptions: $this->differentiators($tied),
        );
    }

    /**
     * Kata pembeda antar calon seri, untuk jadi tombol pilihan.
     *
     * Yang dikembalikan harus kata yang — bila ditambahkan ke pertanyaan asal
     * lalu ditanya ulang — benar-benar memecah serinya. Kalau layanan semua
     * calon sama, pembedanya ada di sub category (kasus SAP vs SILO di bawah
     * layanan AKUN APLIKASI yang sama); sebaliknya pembedanya layanan.
     *
     * @param  SubjectMatch[]  $tied
     * @return string[]
     */
    private function differentiators(array $tied): array
    {
        $services = array_unique(array_map(fn (SubjectMatch $m) => $m->service, $tied));

        $labels = count($services) === 1
            ? array_map(fn (SubjectMatch $m) => $m->subcategory, $tied)
            : array_map(fn (SubjectMatch $m) => $m->service, $tied);

        return array_values(array_unique($labels));
    }

    private function noAnswer(string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_NO_ANSWER, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_NO_ANSWER,
            text: self::NO_ANSWER_TEXT,
            hit: null,
            answerLogId: $log->id,
        );
    }

    private function log(string $question, string $outcome, ?Conversation $conversation, ?User $asker, array $extra = []): AnswerLog
    {
        $log = AnswerLog::create([
            'conversation_id' => $conversation?->id,
            'question' => mb_substr(trim($question), 0, 500),
            'outcome' => $outcome,
            'asked_by' => $asker?->id,
            'confidence' => 0,
            ...$extra,
        ]);

        $this->stampConversation($conversation, $outcome);

        return $log;
    }

    /**
     * Hasil percakapan ikut diperbarui di sini, bukan di pemanggil.
     *
     * Sebelumnya hanya seeder yang melakukannya, sementara EVA Preview tidak —
     * akibatnya setiap percakapan sungguhan tetap berstatus "Berjalan" selamanya
     * di Log Percakapan, walau EVA jelas sudah menjawab. Menaruh keputusan ini
     * di satu tempat menutup celah itu untuk semua pemanggil sekaligus.
     */
    private function stampConversation(?Conversation $conversation, string $outcome): void
    {
        if ($conversation === null) {
            return;
        }

        $conversation->update([
            'outcome' => match ($outcome) {
                AnswerLog::OUTCOME_ANSWERED => Conversation::OUTCOME_ANSWERED,
                AnswerLog::OUTCOME_NO_ANSWER, AnswerLog::OUTCOME_TICKET_DRAFT => Conversation::OUTCOME_TICKET,
                // Bertanya balik bukan akhir percakapan — hasilnya baru
                // ditentukan giliran berikutnya.
                default => $conversation->outcome,
            },
        ]);
    }
}

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
     * Batas potongan yang benar-benar dikirim, setelah tiap kandidat boleh
     * menyumbang lebih dari satu.
     *
     * Delapan kandidat × tiga potongan = 24, dan itu melipatgandakan prompt
     * untuk keuntungan yang menipis: potongan ketiga dari kandidat kedelapan
     * hampir tidak pernah yang menjawab. Dua belas menampung seluruh potongan
     * dari empat kandidat teratas — di situlah jawaban hampir selalu berada —
     * tanpa membuat setiap pertanyaan membayar untuk ekor daftar.
     */
    private const SYNTHESIS_PASSAGES = 12;

    /*
     | Lantai rangkuman dulu ditulis di sini juga. Sekarang tinggal di
     | AnswerReach, karena angka itulah yang membedakan "EVA tidak menjawab"
     | dari "EVA mungkin menjawab" pada alat ukur di layar — dan selama ia
     | disalin di dua tempat, keduanya akan berpisah pada perubahan pertama.
     */

    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly VagueQuestionDetector $vagueDetector,
        private readonly SubjectSearch $subjects,
        private readonly AnswerParaphraser $paraphraser,
        private readonly KnowledgeSynthesizer $synthesizer,
        private readonly SmallTalkDetector $smallTalk,
        private readonly ConversationEngine $conversationEngine,
    ) {}

    public function jawab(string $question, ?Conversation $conversation = null, ?User $asker = null): EvaReply
    {
        // Sapaan diperiksa paling awal: "Halo" bukan pertanyaan yang gagal
        // dijawab, jadi ia tidak boleh menempuh pencarian, tidak boleh
        // menghasilkan tawaran tiket, dan tidak boleh masuk Unanswered
        // Questions sebagai celah materi yang mustahil ditutup.
        $memory = ConversationMemory::recall($conversation);

        if ($balasan = $this->smallTalk->balasan($question)) {
            return $this->smallTalkReply(
                $this->conversationEngine->chat($question, $memory, $balasan),
                $question,
                $conversation,
                $asker,
            );
        }

        /*
         | Pertanyaan lanjutan diurai jadi pertanyaan utuh SEBELUM apa pun yang
         | lain menyentuhnya.
         |
         | Urutannya menentukan. "kalau masih gagal gimana?" adalah pertanyaan
         | kabur bila dibaca sendirian — VagueQuestionDetector benar menandainya,
         | dan EVA akan bertanya balik hal yang barusan dijawabnya sendiri. Yang
         | membuatnya tidak kabur bukan kata-katanya, melainkan giliran
         | sebelumnya. Jadi konteks dipulihkan dulu, baru kekaburannya dinilai.
         |
         | Basa-basi sengaja diperiksa lebih dulu: "makasih ya" tidak perlu
         | diurai jadi pertanyaan, dan mengurainya justru mengubahnya menjadi
         | pertanyaan yang tidak pernah ditanyakan siapa pun.
        */
        $question = $this->conversationEngine->standalone($question, $memory);

        if ($this->vagueDetector->isVague($question)) {
            return $this->clarify($question, $conversation, $asker);
        }

        $hits = $this->search->cari($question, self::SYNTHESIS_CANDIDATES);
        $best = $hits[0] ?? null;

        /*
         | Kutipan harus dibuktikan, bukan diandaikan.
         |
         | Pencarian teks memulangkan materi yang bertumpang KATA, belum tentu
         | bertumpang MAKSUD. "apakah EVA bisa mengarahkan saya untuk pembuatan
         | tiket" mengandung kata "tiket"; SOP akun SAP juga menyebut "formulir
         | tiket". Cukup untuk lolos ambang, sama sekali tidak cukup untuk
         | menjawab — dan yang lahir dari situ adalah jawaban salah topik yang
         | JUSTRU tampak resmi karena membawa kutipan.
         |
         | Hanya diperiksa di pita keyakinan SEDANG. Di atas HEDGE_CONFIDENCE
         | pencarian sudah cukup yakin dan pemeriksaan ini hanya menambah satu
         | panggilan ke jalur yang paling sering dilewati; di bawah
         | MIN_CONFIDENCE tidak ada yang perlu diperiksa karena tidak akan
         | dikutip juga. Pita sedang inilah satu-satunya tempat kesalahan itu
         | bisa hidup.
        */
        if ($best !== null
            && $best->confidence < KnowledgeSearch::HEDGE_CONFIDENCE
            && ! $this->conversationEngine->materialAnswers($question, $best->title, $best->answer)
        ) {
            $best = null;
            $hits = [];
        }

        // Merangkum lebih dulu, sebelum ambang keyakinan diperiksa. Jawaban
        // yang tersebar di beberapa dokumen tidak pernah membuat satu pun di
        // antaranya terlihat meyakinkan sendirian — persis kasus yang dulu
        // berakhir "belum menemukan jawaban" padahal materinya ada.
        $relevant = $this->relevantHits($hits);
        [$passages, $owners] = $this->passagesWithOwners($relevant);
        $rangkuman = $this->synthesizer->rangkum($question, $passages);

        if ($rangkuman !== null && $best !== null) {
            return $this->answer(
                $best,
                $question,
                $conversation,
                $asker,
                $rangkuman->text,
                $this->sourcesUsed($owners, $rangkuman->usedPassages, $best),
            );
        }

        if ($best === null || $best->confidence < KnowledgeSearch::MIN_CONFIDENCE) {
            // Sebelum menyerah: apakah pertanyaannya jelas menunjuk satu masalah
            // tapi ambigu antar layanan (reset password → SAP atau SILO)? Kalau
            // ya, bertanya balik lebih berguna daripada langsung menawarkan draf.
            $tied = $this->subjects->calonSeri($question);

            if ($tied !== []) {
                return $this->clarifySubject($question, $tied, $conversation, $asker);
            }

            /*
             | Jaring terakhir: mungkin ini memang bukan pertanyaan layanan.
             |
             | SmallTalkDetector di awal hanya mengenali sembilan pola tetap —
             | sapaan, terima kasih, pamit, dan sejenisnya. Kalimat pembuka
             | seperti "saya memiliki pertanyaan lagi" lolos dari daftar itu,
             | lalu menempuh seluruh jalur pencarian dan berakhir disodori draf
             | tiket, kepada orang yang bahkan belum sempat bertanya.
             |
             | Diperiksa DI SINI, bukan di awal, dan itu yang menjaganya tetap
             | murah sekaligus aman: pertanyaan sungguhan selalu mendapat
             | kesempatan dijawab Knowledge Base lebih dulu, dan panggilan ini
             | hanya terjadi pada pertanyaan yang memang sudah gagal dijawab.
            */
            if ($obrolan = $this->conversationEngine->converse($question, $memory)) {
                return $this->smallTalkReply($obrolan, $question, $conversation, $asker);
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
        return $this->passagesWithOwners($hits)[0];
    }

    /**
     * Potongan yang dikirim ke perangkum, BESERTA sumber tiap potongan.
     *
     * Keduanya dikembalikan sekaligus dan itu bukan kenyamanan: satu kandidat
     * kini boleh menyumbang beberapa potongan, jadi "potongan ke-3" tidak lagi
     * berarti "kandidat ke-3". Perangkum menjawab dengan nomor POTONGAN, dan
     * tanpa daftar pemilik yang sejajar, rujukan yang ditampilkan akan
     * menunjuk dokumen yang salah — persis bentuk kesalahan yang paling sulit
     * terlihat, karena jawabannya tetap benar.
     *
     * @param  SearchHit[]  $hits
     * @return array{0: list<array{title:string,text:string}>, 1: list<SearchHit>}
     */
    private function passagesWithOwners(array $hits): array
    {
        $passages = [];
        $owners = [];

        foreach ($hits as $hit) {
            foreach ($hit->passageList() as $text) {
                if (count($passages) >= self::SYNTHESIS_PASSAGES) {
                    break 2;
                }

                $passages[] = ['title' => $hit->title, 'text' => $text];
                $owners[] = $hit;
            }
        }

        return [$passages, $owners];
    }

    /**
     * Kandidat yang cukup kuat untuk ikut dirangkum.
     *
     * Dipisah dari passages() supaya urutannya SATU: indeks yang dikembalikan
     * perangkum menunjuk ke array ini juga, dan pemetaan balik "potongan 3" →
     * dokumen aslinya tidak bergantung pada dua penyaringan yang kebetulan
     * masih sejalan.
     *
     * @param  SearchHit[]  $hits
     * @return list<SearchHit>
     */
    private function relevantHits(array $hits): array
    {
        return array_values(array_filter(
            $hits,
            fn (SearchHit $h) => $h->confidence >= AnswerReach::SYNTHESIS_FLOOR,
        ));
    }

    /**
     * Materi yang benar-benar dipakai rangkuman, dipetakan dari indeks
     * potongan.
     *
     * Daftar kosong BUKAN kegagalan: perangkum boleh saja tidak memberi
     * keterangan, dan model sesekali melupakannya. Jatuhnya ke kandidat teratas
     * — perilaku lama — karena jawaban tanpa rujukan sama sekali lebih buruk
     * daripada rujukan yang tidak lengkap.
     *
     * @param  list<SearchHit>  $relevant
     * @param  list<int>  $usedPassages
     * @return list<SearchHit>
     */
    private function sourcesUsed(array $owners, array $usedPassages, SearchHit $best): array
    {
        $used = [];

        foreach ($usedPassages as $i) {
            $hit = $owners[$i] ?? null;

            if ($hit === null) {
                continue;
            }

            // Satu dokumen bisa menyumbang dua potongan yang dua-duanya
            // dipakai; ia tetap SATU rujukan di layar. Menyebutnya dua kali
            // membuat pembaca mengira ada dua sumber yang saling menguatkan.
            $used[$hit->type().':'.$hit->sourceId] = $hit;
        }

        return $used === [] ? [$best] : array_values($used);
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

    /** @param SearchHit[] $sources materi yang benar-benar dipakai; kosong = hanya $hit */
    private function answer(SearchHit $hit, string $question, ?Conversation $conversation, ?User $asker, ?string $synthesized = null, array $sources = []): EvaReply
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
            // Jawaban satu sumber tetap menyebut satu sumber — tidak ada yang
            // berubah untuk jalur non-rangkuman.
            sources: $sources === [] ? [$hit] : $sources,
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

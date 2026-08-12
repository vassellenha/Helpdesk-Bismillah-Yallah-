<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Memilih POTONGAN dokumen yang benar-benar menjawab pertanyaan.
 *
 * MASALAH YANG DISELESAIKAN: sebelum ini jawaban EVA untuk artikel selalu
 * `summary`-nya, dan `summary` dibuat otomatis dari paragraf PERTAMA dokumen
 * (lihat DocumentIndexer::summarize()). Untuk SOP yang berkop surat, paragraf
 * pertama itu adalah kop suratnya. Hasilnya: pertanyaan apa pun tentang dokumen
 * itu dijawab "PT ADHI KARYA (PERSERO) TBK…" — artikelnya benar, jawabannya
 * tidak menjawab apa-apa.
 *
 * Bahannya sudah lama ada dan belum pernah dipakai: DocumentIndexer memotong
 * setiap dokumen jadi chunk ~900 karakter DI BATAS PARAGRAF, justru supaya satu
 * langkah prosedur tidak terbelah. Chunk itu tersimpan rapi di kb_chunks dan
 * tidak pernah ikut dicari sama sekali. Kelas ini yang menyambungkannya.
 *
 * BATASAN YANG DISENGAJA: hanya berlaku untuk artikel yang berasal dari
 * dokumen. Artikel yang ditulis tangan tetap dijawab dengan `summary`-nya,
 * karena di sana ringkasan itu memang ditulis manusia sebagai jawaban — bukan
 * potongan pertama yang kebetulan terambil.
 */
final class PassagePicker
{
    /**
     * Panjang jawaban yang dikembalikan. Chunk utuh (~900) terlalu panjang
     * untuk gelembung obrolan; dipotong di batas kalimat agar tidak terputus
     * di tengah kata.
     */
    private const ANSWER_LENGTH = 600;

    public function __construct(private readonly SynonymExpander $synonyms) {}

    /**
     * Jawaban terbaik untuk setiap artikel, dikunci id artikel.
     *
     * SATU query untuk seluruh kandidat, bukan satu per artikel. Chunk yang
     * tidak memuat satu pun kata dari pertanyaan tidak ikut ditarik sama
     * sekali — itu yang menjaga jumlah barisnya tetap kecil meski sebuah
     * dokumen boleh punya sampai 500 chunk.
     *
     * @param  Collection<int, Article>  $articles
     * @param  string[]  $tokens
     * @return array<int, string> [article_id => potongan jawaban]
     */
    public function forArticles(Collection $articles, array $tokens): array
    {
        // Diperluas sinonimnya, sama seperti tahap recall: kalau pengguna
        // menulis "sandi" sedangkan dokumennya menulis "password", potongan
        // yang benar harus tetap terpilih.
        $tokens = array_values(array_filter($this->synonyms->expandAll($tokens)));

        if ($tokens === []) {
            return [];
        }

        $documentIds = $articles->pluck('source_document_id')->filter()->unique()->values();

        if ($documentIds->isEmpty()) {
            return [];
        }

        $chunks = Chunk::query()
            ->whereIn('document_id', $documentIds)
            ->where(function (Builder $inner) use ($tokens) {
                foreach ($tokens as $token) {
                    $inner->orWhere('content', 'like', '%'.$token.'%');
                }
            })
            ->orderBy('ordinal')
            ->get(['document_id', 'ordinal', 'content'])
            ->groupBy('document_id');

        $hasil = [];

        foreach ($articles as $article) {
            $milik = $chunks->get($article->source_document_id);

            if ($milik === null || $milik->isEmpty()) {
                continue;
            }

            /*
            | Kata pembeda dihargai lebih tinggi daripada kata yang muncul di
            | mana-mana.
            |
            | Versi pertama hanya MENGHITUNG berapa kata yang cocok, dan itu
            | salah pilih pada dokumen yang seluruh isinya membahas satu topik:
            | untuk "akun SAP saya terkunci", potongan tentang Password Expired
            | menang hanya karena memuat "sap", "akun", dan "password" —
            | sedangkan potongan yang benar-benar membahas penguncian cuma
            | memuat "terkunci". Bobot di bawah membalik urutan itu.
            */
            $bobot = $this->bobotToken($milik, $tokens);

            $terbaik = $milik
                ->sortByDesc(fn (Chunk $c) => $this->skor($c->content, $tokens, $bobot))
                ->first();

            if ($this->skor($terbaik->content, $tokens, $bobot) <= 0.0) {
                continue;
            }

            $hasil[$article->id] = $this->rapikan($terbaik->content, $tokens, $bobot);
        }

        return $hasil;
    }

    /**
     * Bobot tiap kata: makin sedikit potongan yang memuatnya, makin berharga.
     *
     * Ini IDF sederhana yang dihitung di dalam satu dokumen. "sap" yang muncul
     * di kedelapan potongan hampir tidak menambah apa-apa; "saplogon" yang cuma
     * muncul di satu potongan hampir menentukan sendirian.
     *
     * @param  Collection<int, Chunk>  $potongan
     * @param  string[]  $tokens
     * @return array<string, float>
     */
    private function bobotToken(Collection $potongan, array $tokens): array
    {
        $total = max(1, $potongan->count());
        $bobot = [];

        foreach ($tokens as $token) {
            $muncul = $potongan->filter(
                fn (Chunk $c) => str_contains(mb_strtolower($c->content), mb_strtolower($token))
            )->count();

            // Kata yang tidak muncul sama sekali tidak perlu diberi bobot.
            $bobot[$token] = $muncul === 0 ? 0.0 : $total / $muncul;
        }

        return $bobot;
    }

    /**
     * Skor sebuah potongan: jumlah bobot kata BERBEDA yang muncul di dalamnya.
     *
     * Kata berbeda, bukan jumlah kemunculan — kalau tidak, potongan panjang
     * yang mengulang satu kata akan selalu menang atas potongan pendek yang
     * menjawab tepat sasaran.
     *
     * @param  string[]  $tokens
     * @param  array<string, float>  $bobot
     */
    private function skor(string $teks, array $tokens, array $bobot): float
    {
        $teks = mb_strtolower($teks);
        $skor = 0.0;

        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($teks, mb_strtolower($token))) {
                $skor += $bobot[$token] ?? 1.0;
            }
        }

        return $skor;
    }

    /**
     * Ambil jendela teks DI SEKITAR kalimat yang cocok, bukan selalu dari awal
     * potongan.
     *
     * Chunk dipotong per ~900 karakter dan kerap melintasi batas bagian: ekor
     * "Password Expired" bisa satu potongan dengan kepala "User Locked". Versi
     * pertama selalu menampilkan 600 karakter pertama, jadi untuk pertanyaan
     * soal penguncian yang tampil justru ekor bagian sebelumnya — potongannya
     * benar, bagian yang ditampilkan salah.
     *
     * @param  string[]  $tokens
     * @param  array<string, float>  $bobot
     */
    private function rapikan(string $teks, array $tokens, array $bobot): string
    {
        $teks = trim(preg_replace('/\s+/u', ' ', $teks) ?? $teks);

        if (mb_strlen($teks) <= self::ANSWER_LENGTH) {
            return $teks;
        }

        $mulai = $this->awalJendela($teks, $tokens, $bobot);
        $jendela = mb_substr($teks, $mulai, self::ANSWER_LENGTH);

        // Potong ekornya di batas kalimat supaya tidak berhenti di tengah kata.
        $akhirKalimat = max(
            mb_strrpos($jendela, '. ') ?: 0,
            mb_strrpos($jendela, '? ') ?: 0,
            mb_strrpos($jendela, '! ') ?: 0,
        );

        if ($akhirKalimat > self::ANSWER_LENGTH / 2) {
            $jendela = mb_substr($jendela, 0, $akhirKalimat + 1);
        } elseif (mb_strlen($teks) > $mulai + self::ANSWER_LENGTH) {
            $akhirKata = mb_strrpos($jendela, ' ');
            $jendela = ($akhirKata ? mb_substr($jendela, 0, $akhirKata) : $jendela).'…';
        }

        // Elipsis di depan hanya bila jendelanya memang tidak dari awal, supaya
        // pembaca tahu ada teks sebelum ini.
        return ($mulai > 0 ? '…' : '').trim($jendela);
    }

    /**
     * Titik mulai jendela: awal kalimat yang memuat kata paling pembeda.
     *
     * @param  string[]  $tokens
     * @param  array<string, float>  $bobot
     */
    private function awalJendela(string $teks, array $tokens, array $bobot): int
    {
        $rendah = mb_strtolower($teks);

        // Kata dengan bobot tertinggi yang benar-benar ada di potongan ini —
        // itulah yang paling mungkin menandai bagian yang menjawab.
        $terpilih = null;
        $tertinggi = 0.0;

        foreach ($tokens as $token) {
            if ($token === '' || ! str_contains($rendah, mb_strtolower($token))) {
                continue;
            }

            if (($bobot[$token] ?? 1.0) > $tertinggi) {
                $tertinggi = $bobot[$token] ?? 1.0;
                $terpilih = $token;
            }
        }

        if ($terpilih === null) {
            return 0;
        }

        $posisi = mb_strpos($rendah, mb_strtolower($terpilih));

        if ($posisi === false || $posisi < self::ANSWER_LENGTH / 3) {
            // Sudah dekat awal — tidak ada gunanya menggeser jendela.
            return 0;
        }

        // Mundur ke awal kalimat terdekat supaya jendela tidak mulai di tengah,
        // LALU mundur satu kalimat lagi sebagai ancang-ancang.
        //
        // Kalimat pembuka itu sering justru yang memuat jawabannya, sementara
        // kata yang cocok ada di kalimat sesudahnya. Contoh nyata: untuk
        // "berapa lama masa berlaku password", kata "lama" cocok di "Akun lama
        // tidak digunakan…", padahal jawabannya ("…melewati masa berlaku 90
        // hari…") ada di kalimat tepat sebelumnya. Tanpa ancang-ancang, jawaban
        // itu terpotong dari tampilan.
        $awal = $this->awalKalimatSebelum($rendah, $posisi);
        $lebihAwal = $this->awalKalimatSebelum($rendah, max(0, $awal - 2));

        // Ancang-ancang diambil hanya bila kalimat itu MEMANG relevan, bukan
        // sekadar kalimat yang kebetulan ada sebelumnya.
        //
        // Tanpa syarat ini, pertanyaan yang cocok tepat di awal sebuah bagian
        // justru dibuka dengan ekor bagian sebelumnya ("Yang perlu disiapkan
        // pemohon: …") — satu baris derau di tempat yang paling dilihat orang.
        // Ambangnya di atas 1.0: kata yang muncul di semua potongan berbobot
        // 1.0, jadi kalimat yang cuma memuat kata umum tidak lolos.
        $kalimatSebelum = mb_substr($teks, $lebihAwal, $awal - $lebihAwal);
        $masihMuat = $awal - $lebihAwal <= (int) (self::ANSWER_LENGTH / 3);

        if ($masihMuat && $this->skor($kalimatSebelum, $tokens, $bobot) > 1.0) {
            $awal = $lebihAwal;
        }

        return $awal;
    }

    /** Posisi awal kalimat yang berakhir tepat sebelum $posisi. */
    private function awalKalimatSebelum(string $teks, int $posisi): int
    {
        $sebelum = mb_substr($teks, 0, $posisi);

        $batas = max(
            mb_strrpos($sebelum, '. ') ?: 0,
            mb_strrpos($sebelum, '? ') ?: 0,
            mb_strrpos($sebelum, '! ') ?: 0,
        );

        return $batas > 0 ? $batas + 2 : 0;
    }
}

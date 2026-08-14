<?php

namespace App\Services\Knowledge;

use App\Models\ServiceCatalogSubject;
use Illuminate\Support\Facades\Cache;

/**
 * Pencarian B — mencari NAMA MASALAH di service_catalog_subjects.
 *
 * Sengaja dipisah tegas dari KnowledgeSearch (Pencarian A). Keduanya menerima
 * kalimat yang sama tetapi menjawab pertanyaan yang berbeda:
 *
 *   Pencarian A → "apa jawabannya?"      sumber: kb_articles, kb_faqs
 *   Pencarian B → "ini masalah apa?"     sumber: service_catalog_subjects
 *
 * Menggabungkannya akan membuat EVA mengutip nama subject katalog seolah-olah
 * itu solusi — padahal "Reset Password" adalah label tiket, bukan panduan.
 *
 * Seluruh katalog DIBACA SAJA (aturan #5). Tidak ada indeks FULLTEXT yang
 * ditambahkan ke tabel tim: 140 subject aktif muat di memori, dan menambah
 * indeks ke tabel milik role lain adalah perubahan skema yang tidak berhak
 * dilakukan EVA.
 */
final class SubjectMatcher implements SubjectSearch
{
    /**
     * Nilai satu kata yang cocok TIDAK PERSIS, hanya mirip lewat jarak edit.
     *
     * Sedikit di bawah 1.0 supaya kecocokan persis selalu menang atas
     * kecocokan typo: "password" yang tepat harus mengalahkan "pasword" yang
     * dikoreksi, saat keduanya bersaing untuk subject yang sama.
     */
    private const FUZZY_CREDIT = 0.8;

    /**
     * Selisih nilai minimal antara calon teratas dan calon kedua sebelum
     * isi-otomatis berani memilih.
     *
     * Kalau dua calon nyaris seimbang, keunggulan yang pertama cuma kebetulan
     * urutan — dan katalog ini penuh subject kembar di layanan berbeda ("Reset
     * Password" di SAP maupun SILO). Menebak salah satunya berarti tiket
     * mendarat di tim yang salah tanpa ada yang memeriksa. Di ambang ini
     * terbaik() menahan diri dan mengosongkan kolom; daftar calon di layar
     * tetap menampilkan keduanya.
     *
     * Angkanya hidup di SubjectSearch::TIE_MARGIN supaya layar Ticket
     * Recommendation menilai dengan aturan yang persis sama.
     */

    /**
     * Bobot tiap bagian jalur katalog.
     *
     * Nama subject memikul hampir seluruh keputusan. Nama layanan dan sub
     * category hanya BONUS PEMBEDA — bukan bagian besar dari penyebut.
     *
     * Ini pelajaran dari versi pertama: nama layanan di katalog ini adalah kode
     * internal (MAILIA, PERANGKAT, SILO APPS) yang tidak pernah diketik
     * karyawan. Saat bobotnya 30, pertanyaan wajar seperti "email tidak masuk"
     * kehilangan sepertiga nilainya hanya karena penanya tidak tahu bahwa
     * layanan surel di perusahaan ini bernama MAILIA — hukuman untuk sesuatu
     * yang bukan kesalahannya.
     */
    private const SUBJECT_WEIGHT = 70;

    private const SERVICE_WEIGHT = 20;

    private const SUBCATEGORY_WEIGHT = 10;

    private const CACHE_KEY = 'eva.catalog-subject-index';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly QuestionTokenizer $tokenizer,
        private readonly SynonymExpander $synonyms,
    ) {}

    /**
     * Subject yang paling mungkin dimaksud, terurut dari yang paling yakin.
     *
     * @return SubjectMatch[] boleh kosong
     */
    public function cocokkan(string $pertanyaan, int $limit = 5): array
    {
        $asked = $this->tokenizer->significant($pertanyaan);

        if ($asked === []) {
            return [];
        }

        $matches = [];

        foreach ($this->index() as $row) {
            $match = $this->score($row, $asked);

            if ($match !== null) {
                $matches[] = $match;
            }
        }

        usort($matches, fn (SubjectMatch $a, SubjectMatch $b) => $b->confidence <=> $a->confidence);

        return array_slice($matches, 0, $limit);
    }

    /**
     * Subject tunggal yang cukup meyakinkan untuk diisikan otomatis.
     *
     * Memakai ambang yang lebih ketat daripada cocokkan() — lihat MIN_CONFIDENCE.
     * Mengembalikan null adalah hasil yang sah dan sering benar.
     */
    public function terbaik(string $pertanyaan): ?SubjectMatch
    {
        // Dua diambil, bukan satu — calon kedua dibutuhkan untuk menilai apakah
        // keunggulan yang teratas sungguhan atau cuma seri yang menang urutan.
        $top = $this->cocokkan($pertanyaan, 2);
        $best = $top[0] ?? null;

        if ($best === null || $best->confidence < self::MIN_CONFIDENCE) {
            return null;
        }

        $runnerUp = $top[1] ?? null;

        if ($runnerUp !== null && $best->confidence - $runnerUp->confidence <= SubjectSearch::TIE_MARGIN) {
            return null;
        }

        return $best;
    }

    public function calonSeri(string $pertanyaan): array
    {
        $top = $this->cocokkan($pertanyaan, 5);
        $best = $top[0] ?? null;

        // Calon teratas lemah → bukan ambiguitas, cuma tak paham. Jangan
        // bertanya soal yang EVA sendiri tak yakin.
        if ($best === null || $best->confidence < self::MIN_CONFIDENCE) {
            return [];
        }

        // Hanya seri yang BENAR-BENAR soal sama: nama subject identik, cabang
        // beda (Reset Password @ SAP vs @ SILO). Seri antar subject berbeda
        // bukan pertanyaan "layanan mana?" yang bisa dijawab satu kata — untuk
        // itu draf tiket lebih jujur daripada pertanyaan yang membingungkan.
        $tied = array_values(array_filter(
            $top,
            fn (SubjectMatch $m) => $best->confidence - $m->confidence <= SubjectSearch::TIE_MARGIN
                && mb_strtolower($m->subject) === mb_strtolower($best->subject),
        ));

        // Butuh minimal dua cabang untuk ada yang ditanyakan. Satu calon unggul
        // sendirian bukan seri — itu ranah terbaik(), bukan pertanyaan balik.
        return count($tied) >= 2 ? $tied : [];
    }

    /** Katalog berubah dari layar Admin — buang cache agar tidak basi. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Nilai kecocokan satu subject terhadap pertanyaan.
     *
     * Yang diukur adalah "berapa banyak kata SUBJECT yang disebut penanya",
     * bukan sebaliknya. Alasannya: pertanyaan karyawan panjang dan penuh
     * konteks ("sudah dari kemarin saya coba"), sementara nama subject cuma dua
     * tiga kata. Mengukur dari sisi pertanyaan akan menghukum subject yang
     * justru tepat hanya karena kalimatnya bertele-tele.
     *
     * @param  array<string,mixed>  $row
     * @param  string[]  $asked
     */
    private function score(array $row, array $asked): ?SubjectMatch
    {
        $expanded = $this->synonyms->expandAll($asked);

        $subject = $this->subjectOverlap($row, $expanded);
        $service = $this->overlap($row['service_stems'], $expanded);
        $subcategory = $this->overlap($row['subcategory_stems'], $expanded);

        // Tanpa satu pun kata subject yang cocok, kecocokan layanan saja tidak
        // berarti apa-apa — kalau tidak, menyebut "SAP" akan memunculkan
        // seluruh 43 subject SAP dengan nilai yang sama.
        if ($subject['ratio'] <= 0.0) {
            return null;
        }

        $confidence = (int) round(
            self::SUBJECT_WEIGHT * $subject['ratio']
            + self::SERVICE_WEIGHT * $service['ratio']
            + self::SUBCATEGORY_WEIGHT * $subcategory['ratio']
        );

        if ($confidence < self::SUGGEST_FLOOR) {
            return null;
        }

        return new SubjectMatch(
            subjectId: $row['id'],
            subject: $row['subject'],
            service: $row['service'],
            subcategory: $row['subcategory'],
            issueCategory: $row['issue_category'],
            confidence: $confidence,
            requiresApproval: $row['requires_approval'],
            supportLevel: $row['support_level'],
            matchedTerms: array_values(array_unique([
                ...$subject['terms'], ...$service['terms'], ...$subcategory['terms'],
            ])),
        );
    }

    /**
     * Kecocokan nama subject: PEMBILANG dari kata pembeda, PENYEBUT dari kata
     * lengkapnya.
     *
     * Pemisahan ini menutup lubang yang dibuka oleh distinctive() sendiri.
     * "SAP Lambat" menyusut jadi satu kata pembeda ("lambat"), sehingga satu
     * kecocokan saja bernilai 100% — dan pertanyaan "laptop saya lemot"
     * (lemot = lambat lewat sinonim) melejit ke 70, cukup untuk mengisi draf
     * tiket secara otomatis dengan subject SAP yang sama sekali tidak
     * disinggung penanya.
     *
     * Dengan penyebut kata lengkap, subject yang separuh namanya adalah nama
     * layanan tidak bisa lagi mencapai nilai penuh tanpa layanannya ikut
     * disebut. Itu memang seharusnya.
     *
     * @param  array<string,mixed>  $row
     * @param  string[]  $expanded
     * @return array{ratio:float,terms:string[]}
     */
    private function subjectOverlap(array $row, array $expanded): array
    {
        // Pembilang dari kata pembeda, penyebut dari kata lengkap. Lihat
        // matchTerms() untuk cara kata dicocokkan (persis maupun typo).
        return $this->matchTerms($row['distinctive_stems'], count($row['subject_stems']), $expanded);
    }

    /**
     * Kata yang benar-benar MEMBEDAKAN sebuah subject dari saudaranya.
     *
     * Nama layanan sering ikut tertulis di nama subject-nya ("SAP Lambat" di
     * bawah layanan SAP). Kalau tidak dibuang, kata "SAP" dihitung dua kali —
     * sekali sebagai subject, sekali sebagai layanan — dan pertanyaan "lupa
     * password SAP" membuat "SAP Lambat" ikut bernilai tinggi hanya karena
     * menyebut nama layanannya. Itu persis yang terjadi pada versi pertama.
     *
     * Dikembalikan utuh bila penyaringan menyisakan kosong: subject bernama
     * persis seperti layanannya tetap harus bisa dicocokkan.
     *
     * @param  string[]  $subject
     * @param  string[]  $service
     * @return string[]
     */
    private function distinctive(array $subject, array $service): array
    {
        $distinctive = array_values(array_diff($subject, $service));

        return $distinctive === [] ? $subject : $distinctive;
    }

    /**
     * Bagian kata sasaran yang tersebut di pertanyaan, beserta kata mana saja.
     *
     * @param  string[]  $target
     * @param  string[]  $expanded
     * @return array{ratio:float,terms:string[]}
     */
    private function overlap(array $target, array $expanded): array
    {
        return $this->matchTerms($target, count($target), $expanded);
    }

    /**
     * Menilai berapa banyak kata sasaran yang tersebut di pertanyaan.
     *
     * Kecocokan persis bernilai penuh; kecocokan mirip (typo) bernilai
     * FUZZY_CREDIT. Karena kecocokan persis = 1.0, perilaku untuk kalimat tanpa
     * typo IDENTIK dengan versi sebelumnya — jarak edit hanya MENAMBAH nilai di
     * tempat yang tadinya nol, tidak pernah menggeser yang sudah cocok.
     *
     * @param  string[]  $targetStems  kata yang dinilai (pembilang)
     * @param  int  $denominator  jumlah kata penuh (penyebut)
     * @param  string[]  $expanded  kata pertanyaan yang sudah diperluas sinonim
     * @return array{ratio:float,terms:string[]}
     */
    private function matchTerms(array $targetStems, int $denominator, array $expanded): array
    {
        if ($denominator === 0) {
            return ['ratio' => 0.0, 'terms' => []];
        }

        $score = 0.0;
        $terms = [];

        foreach ($targetStems as $stem) {
            $credit = $this->bestCredit($stem, $expanded);

            if ($credit > 0.0) {
                $score += $credit;
                // Yang dicatat kata BENAR-nya (nama subject), bukan ejaan typo
                // penanya — supaya "cocok: password" tetap terbaca walau yang
                // diketik "pasword".
                $terms[] = $stem;
            }
        }

        return ['ratio' => $score / $denominator, 'terms' => array_values(array_unique($terms))];
    }

    /**
     * Nilai kecocokan terbaik satu kata sasaran terhadap seluruh kata
     * pertanyaan: 1.0 bila persis, FUZZY_CREDIT bila cukup mirip, 0 bila tidak.
     *
     * @param  string[]  $expanded
     */
    private function bestCredit(string $stem, array $expanded): float
    {
        if (in_array($stem, $expanded, true)) {
            return 1.0;
        }

        $allowed = $this->allowedDistance($stem);

        if ($allowed === 0) {
            return 0.0;
        }

        foreach ($expanded as $token) {
            // Selisih panjang saja sudah melampaui ambang → lewati tanpa
            // menghitung jarak edit yang lebih mahal.
            if (abs(strlen($token) - strlen($stem)) > $allowed) {
                continue;
            }

            if (! $this->sharesPrefix($stem, $token)) {
                continue;
            }

            if (levenshtein($stem, $token) <= $allowed) {
                return self::FUZZY_CREDIT;
            }
        }

        return 0.0;
    }

    /**
     * Berapa banyak huruf boleh berbeda sebelum dua kata dianggap kembar typo.
     *
     * Diskalakan menurut panjang, dan kata pendek WAJIB persis. Tanpa aturan
     * ini, "sap" akan cocok dengan "sup" dan "vpn" dengan "apn" — satu huruf
     * pada kata tiga huruf mengubah maknanya sepenuhnya, bukan sekadar salah
     * ketik. Toleransi baru terbuka pada kata yang cukup panjang untuk
     * membedakan typo dari kata lain.
     *
     * Kata panjang pun kini hanya diberi SATU huruf kelonggaran. Dua huruf
     * pada kata tujuh huruf berarti hampir sepertiga katanya boleh berbeda —
     * itu bukan lagi toleransi salah ketik, itu tebakan.
     */
    private function allowedDistance(string $stem): int
    {
        return match (true) {
            strlen($stem) >= 5 => 1,
            default => 0,
        };
    }

    /**
     * Dua huruf pertama wajib sama persis.
     *
     * Jarak edit tidak tahu beda antara salah ketik dan kata lain: "printer"
     * dan "pointer" hanya berjarak 1, padahal yang satu mesin cetak dan yang
     * lain penunjuk. Yang membedakan keduanya bukan jumlah hurufnya melainkan
     * LETAKNYA — jari salah menekan di tengah dan akhir kata, jarang di
     * awalannya.
     *
     * Harganya jujur: typo yang justru mengenai dua huruf pertama ("ativasi"
     * untuk "aktivasi") tidak lagi dikenali, dan EVA akan bertanya balik
     * alih-alih menebak. Itu arah kegagalan yang dipilih sistem ini di
     * mana-mana — bertanya lebih murah daripada menjawab subject yang salah.
     */
    private function sharesPrefix(string $stem, string $token): bool
    {
        return substr($stem, 0, 2) === substr($token, 0, 2);
    }

    /**
     * Seluruh subject aktif, sudah dipecah menjadi kata dasar.
     *
     * Pemecahannya dilakukan sekali lalu di-cache, bukan tiap pertanyaan.
     * Menjalankan stemmer atas 140 subject × 3 kolom pada setiap penekanan
     * tombol adalah pemborosan yang tidak terlihat sampai layar Analytics
     * memutar ratusan pertanyaan sekaligus.
     *
     * @return array<int,array<string,mixed>>
     */
    private function index(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return ServiceCatalogSubject::query()
                ->where('service_catalog_subjects.is_active', true)
                ->join('service_catalog_services as svc', 'svc.id', '=', 'service_catalog_subjects.service_id')
                ->join('service_catalog_subcategories as sub', 'sub.id', '=', 'service_catalog_subjects.subcategory_id')
                ->join('issue_categories as ic', 'ic.id', '=', 'service_catalog_subjects.issue_category_id')
                ->orderBy('service_catalog_subjects.id')
                ->get([
                    'service_catalog_subjects.id',
                    'service_catalog_subjects.name as subject',
                    'service_catalog_subjects.requires_approval',
                    'service_catalog_subjects.support_level',
                    'svc.name as service',
                    'sub.name as subcategory',
                    'ic.name as issue_category',
                ])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'subject' => $row->subject,
                    'service' => $row->service,
                    'subcategory' => $row->subcategory,
                    'issue_category' => $row->issue_category,
                    'requires_approval' => (bool) $row->requires_approval,
                    'support_level' => $row->support_level === null ? null : (int) $row->support_level,
                    'subject_stems' => $this->tokenizer->significant($row->subject),
                    'distinctive_stems' => $this->distinctive(
                        $this->tokenizer->significant($row->subject),
                        $this->tokenizer->significant($row->service),
                    ),
                    'service_stems' => $this->tokenizer->significant($row->service),
                    'subcategory_stems' => $this->tokenizer->significant($row->subcategory),
                ])
                ->all();
        });
    }
}

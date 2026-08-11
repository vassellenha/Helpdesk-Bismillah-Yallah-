<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use App\Models\User;
use App\Services\Knowledge\ConfidenceScorer;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\QuestionTokenizer;
use App\Services\Knowledge\ScoredText;
use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use App\Services\Knowledge\SynonymExpander;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;
use ZipArchive;

/**
 * AUDIT ADVERSARIAL — bukan tes fitur.
 *
 * Berkas ini TIDAK menjaga perilaku yang diinginkan. Ia MEREKAM perilaku yang
 * benar-benar terjadi hari ini, termasuk yang salah, supaya setiap celah punya
 * bukti yang bisa dijalankan ulang alih-alih jadi kalimat di dokumen.
 *
 * Tiap tes diberi label:
 *   [AMAN]   perilakunya sudah benar — tes ini yang menahannya tetap benar.
 *   [CELAH]  perilakunya SALAH dan tes ini mengunci kesalahannya sebagai bukti.
 *            Saat celahnya ditutup, tes ini HARUS gagal — itu tandanya, bukan
 *            regresi. Balik assertion-nya lalu pindahkan labelnya ke [AMAN].
 *
 * Yang sengaja TIDAK ada di sini: Pencarian A (FULLTEXT + fallback LIKE). Ia
 * butuh MySQL sungguhan — lihat FulltextKnowledgeSearchTest dan bagian §2 di
 * docs/eva-qa-adversarial.md untuk prosedur manualnya.
 */
final class AdversarialAuditTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();
        SynonymExpander::forget();

        $admin = User::factory()->create([
            'name' => 'Marcell',
            'email' => 'marcell.laforteza@adhi.co.id',
            'nip' => '19870114001',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        User::factory()->create(['name' => 'Andi', 'email' => 'andi.pratama@adhi.co.id', 'nip' => '19950418102']);

        // Konsol EVA dijaga `auth` + `role:eva`. Mayoritas tes di berkas ini
        // menguji jalur ADMIN TERAUTENTIKASI, jadi identitasnya dipasang di
        // sini; dua tes penjaga di §3 melepasnya lagi lewat sebagaiTamu().
        //
        // TIGA role, bukan satu: gerbang rutenya menuntut 'eva', tapi controller
        // di dalamnya memanggil CurrentActor::admin() (atribusi audit trail) dan
        // CurrentActor::requester() (draf tiket), dan sejak persona tetap
        // dicabut kedua panggilan itu menolak siapa pun yang tidak memegangnya.
        $this->actingAsUserWithRoles($admin, 'eva', 'admin', 'requester');

        $this->seedCatalog();
        $this->stubPencarianA();
    }

    /**
     * Melepas identitas yang dipasang setUp — dipakai tes yang memang harus
     * datang sebagai tamu.
     */
    private function sebagaiTamu(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Pencarian A dipalsukan supaya tes ini jalan di SQLite.
     *
     * Ini sendiri sebuah temuan: `whereFullText()` tidak ada di SQLite, jadi
     * SETIAP request ke /eva/api/preview/ask akan 500 di lingkungan mana pun
     * yang bukan MySQL/MariaDB — termasuk saat DB_CONNECTION salah setel di
     * server baru. Tidak ada penjaga yang mengubahnya jadi pesan yang terbaca.
     */
    private function stubPencarianA(): void
    {
        $this->app->bind(KnowledgeSearch::class, fn () => new class implements KnowledgeSearch
        {
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return [];
            }
        });
    }

    /*
    |---------------------------------------------------------------------------
    | §1 — ENAM ATURAN MUTLAK
    |---------------------------------------------------------------------------
    */

    /** [AMAN] Aturan #1 — tidak ada satu pun jalan HTTP membuat artikel manual. */
    public function test_aturan_1_tidak_ada_endpoint_pembuat_artikel(): void
    {
        // POST /eva/api/articles tidak terdaftar sama sekali, dan route fallback
        // `{key}` hanya satu segmen + hanya GET — jadi tidak ada yang menangkapnya.
        $this->postJson('/eva/api/articles', ['title' => 'Artikel Selundupan'])
            ->assertNotFound();

        $this->assertSame(0, Article::count());
    }

    /**
     * [AMAN] Menimpa isi artikel tetap DIIZINKAN — itu memang gunanya Article
     * Library, dan aturan #1 bicara soal asal-usul artikel, bukan larangan
     * menyunting. Yang dulu jadi celah: perubahannya tak berjejak, sehingga
     * body yang memelintir jawaban EVA tidak bisa ditelusuri ke siapa pun.
     *
     * Sekarang penyuntingnya tercatat di `updated_by` (dan `author_id` tetap
     * utuh sebagai asal materi — dua pertanyaan berbeda, dua kolom berbeda).
     */
    public function test_aman_penyuntingan_isi_artikel_meninggalkan_jejak(): void
    {
        $article = $this->articleFromDocument('SOP Reset Password SAP', 'Hubungi service desk ext 100.');
        $penyunting = User::where('email', 'marcell.laforteza@adhi.co.id')->sole();

        $this->assertNull($article->updated_by, 'Artikel yang lahir dari dokumen belum punya penyunting.');

        $this->putJson("/eva/api/articles/{$article->id}", [
            'title' => 'SOP Reset Password SAP',
            'summary' => 'Kirim NIK dan password lama ke wa.me/628123456789.',
            'body' => 'Kirim NIK dan password lama ke wa.me/628123456789.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ])->assertOk();

        $tersimpan = $article->fresh();

        $this->assertStringContainsString('wa.me', $tersimpan->body);
        $this->assertSame($penyunting->id, $tersimpan->updated_by, 'Perubahan isi harus berjejak.');
        $this->assertSame($article->author_id, $tersimpan->author_id, 'Asal materi tidak ikut berubah.');
    }

    /** [AMAN] Aturan #2 — FAQ terbit seketika, tidak ada kolom status sama sekali. */
    public function test_aturan_2_faq_terbit_langsung(): void
    {
        $this->postJson('/eva/api/faqs', [
            'question' => 'Bagaimana reset password SAP?',
            'answer' => 'Buka menu Lupa Password di halaman login.',
            'is_eva_visible' => true,
        ])->assertCreated();

        $faq = Faq::firstOrFail();

        $this->assertFalse(
            in_array('status', array_keys($faq->getAttributes()), true),
            'kb_faqs tidak boleh punya kolom status — itu pintu masuk alur draf/review.',
        );
        $this->assertSame(1, Faq::query()->answerable()->count(), 'FAQ baru harus langsung bisa menjawab.');
    }

    /** [AMAN] Aturan #3 — jalur jawaban tidak pernah menyentuh tabel tickets. */
    public function test_aturan_3_jalur_jawaban_tidak_membaca_tabel_tickets(): void
    {
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        // Pencarian B dipakai jalur draf tiket dan layar Recommendation.
        app(SubjectSearch::class)->cocokkan('reset password sap');

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString(
                '"tickets"',
                $sql,
                'Pencarian tidak boleh menyentuh tabel tickets: '.$sql,
            );
        }
    }

    /** [AMAN] Aturan #4 — draf tiket tidak menulis satu baris pun ke tabel tickets. */
    public function test_aturan_4_draf_tiket_tidak_menulis_ke_tabel_tickets(): void
    {
        $log = $this->answerLog('printer tidak bisa cetak');
        $before = DB::table('tickets')->count();

        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log->id,
            'question' => 'printer tidak bisa cetak',
        ])->assertOk()->assertJsonPath('draft.note', fn ($n) => str_contains($n, 'Draf tiket'));

        $this->assertSame($before, DB::table('tickets')->count());
    }

    /** [AMAN] Aturan #5 — seluruh siklus EVA tidak mengubah katalog layanan. */
    public function test_aturan_5_katalog_layanan_tetap_read_only(): void
    {
        $snapshot = fn () => [
            DB::table('service_catalog_subjects')->get()->toJson(),
            DB::table('service_catalog_services')->get()->toJson(),
            DB::table('service_catalog_subcategories')->get()->toJson(),
        ];

        $before = $snapshot();

        $log = $this->answerLog('reset password sap');
        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log->id, 'question' => 'reset password sap',
        ])->assertOk();
        $this->postJson('/eva/api/faqs', [
            'question' => 'Reset password SAP?', 'answer' => 'Lihat SOP.', 'is_eva_visible' => true,
            'catalog_subject_id' => 1,
        ])->assertCreated();
        $this->postJson('/eva/api/recommendation/test', ['question' => 'reset password sap'])->assertOk();

        $this->assertSame($before, $snapshot(), 'EVA mengubah katalog — aturan #5 bocor.');
    }

    /**
     * [AMAN] Aturan #6 — BPO & Approval di luar jangkauan EVA.
     *
     * Dijaga secara STATIS, bukan lewat request: tabel approval belum ada di
     * repo ini, jadi tes perilaku akan hijau karena alasan yang salah dan tetap
     * hijau di hari tabelnya lahir. Yang dikunci di sini adalah tidak ada satu
     * baris kode EVA pun yang menyebut approval selain MEMBACA flag katalog.
     */
    public function test_aturan_6_kode_eva_tidak_menulis_approval(): void
    {
        $terlarang = [];

        foreach ([
            app_path('Services/Knowledge'),
            app_path('Http/Controllers/Eva'),
            app_path('Jobs/Knowledge'),
        ] as $folder) {
            foreach (glob($folder.'/*.php') as $file) {
                foreach (preg_split('/\R/', (string) file_get_contents($file)) as $no => $line) {
                    if (preg_match('/Approval|approval/', $line) !== 1) {
                        continue;
                    }

                    // Yang sah hanya MEMBACA `requires_approval` milik katalog.
                    if (preg_match('/requires_approval|requiresApproval/', $line) === 1) {
                        continue;
                    }

                    $terlarang[] = basename($file).':'.($no + 1).' '.trim($line);
                }
            }
        }

        $this->assertSame([], $terlarang, 'Kode EVA menyentuh approval di luar pembacaan flag katalog.');
    }

    /*
    |---------------------------------------------------------------------------
    | §2 — AMBANG & TIE-GUARD PENCARIAN B
    |---------------------------------------------------------------------------
    */

    /** [AMAN] Tie-guard: subject kembar nama, beda layanan → auto-fill DITAHAN. */
    public function test_tie_guard_menahan_autofill_saat_nama_subject_kembar(): void
    {
        // DUA subject bernama persis sama di dua cabang berbeda — inilah bentuk
        // seri yang dijaga TIE_MARGIN. Katalog nyata penuh pasangan seperti ini.
        $this->addSubject(id: 10, service: 'SILO APPS', subcategory: 'LOGIN SILO', subject: 'Reset Password');
        $this->addSubject(id: 20, service: 'MAILIA', subcategory: 'AKUN SUREL', subject: 'Reset Password');
        SubjectMatcher::forget();
        Cache::flush();

        $matcher = app(SubjectSearch::class);

        $this->assertNull(
            $matcher->terbaik('reset password'),
            'Dua cabang seri harus membatalkan isi-otomatis.',
        );

        $tied = $matcher->calonSeri('reset password');
        $this->assertCount(2, $tied, 'Serinya harus jadi bahan pertanyaan balik.');
    }

    /**
     * [CELAH] Seri antar subject BERBEDA NAMA: auto-fill ditahan (benar) TAPI
     * calonSeri() juga kosong, jadi EVA tidak bertanya balik — ia langsung
     * menyerah ke draf tiket dengan subject KOSONG.
     *
     * Ini keputusan sadar di kode (lihat SubjectMatcher::calonSeri), tapi
     * konsekuensinya tidak terlihat di layar mana pun: dua kandidat sama-sama
     * kuat dibuang diam-diam, dan admin tidak pernah tahu pertanyaan itu
     * sebenarnya nyaris terpecahkan.
     */
    public function test_celah_seri_beda_nama_tidak_bertanya_balik(): void
    {
        $this->addSubject(id: 11, service: 'SAP', subcategory: 'AKUN SAP', subject: 'Password Terkunci');
        SubjectMatcher::forget();
        Cache::flush();

        $matcher = app(SubjectSearch::class);
        $hits = $matcher->cocokkan('password');

        $this->assertGreaterThanOrEqual(2, count($hits));
        $this->assertLessThanOrEqual(
            5,
            $hits[0]->confidence - $hits[1]->confidence,
            'Prasyarat tes: keduanya harus seri di dalam TIE_MARGIN.',
        );

        $this->assertNull($matcher->terbaik('password'));
        $this->assertSame([], $matcher->calonSeri('password'), 'Tidak ada pertanyaan balik — langsung menyerah.');
    }

    /** [AMAN] Ambang ganda 50/30 dipatuhi persis di titik batasnya. */
    public function test_ambang_ganda_50_dan_30(): void
    {
        $matcher = app(SubjectSearch::class);

        // "Password Expired" = 2 kata pembeda. Menyebut satu → 70 × 0.5 = 35:
        // masuk daftar calon (>= 30) tapi TIDAK boleh mengisi otomatis (< 50).
        $satuKata = $matcher->cocokkan('expired');
        $this->assertNotEmpty($satuKata);
        $this->assertGreaterThanOrEqual(SubjectSearch::SUGGEST_FLOOR, $satuKata[0]->confidence);
        $this->assertLessThan(SubjectSearch::MIN_CONFIDENCE, $satuKata[0]->confidence);
        $this->assertNull($matcher->terbaik('expired'), 'Skor 30-49 tidak boleh terisi otomatis.');

        // Di bawah 30 dibuang sepenuhnya — bukan dikembalikan dengan skor kecil.
        $this->assertSame([], $matcher->cocokkan('kursi meja gudang'));
    }

    /**
     * [AMAN] Toleransi typo dulu bekerja terbalik pada kata teknis panjang:
     * dua huruf boleh berbeda untuk kata >= 7 huruf, cukup untuk mencocokkan
     * DUA KATA BERBEDA. Kini toleransinya satu huruf DAN dua huruf pertama
     * wajib sama — yang membedakan typo dari kata lain adalah letak bedanya.
     */
    public function test_aman_jarak_edit_tidak_mencocokkan_kata_yang_berbeda_makna(): void
    {
        $this->addSubject(id: 12, service: 'PERANGKAT', subcategory: 'PRINTER', subject: 'Printer Macet');
        SubjectMatcher::forget();
        Cache::flush();

        $matcher = app(SubjectSearch::class);

        // "pointer" bukan salah ketik "printer" — beda benda, walau jarak
        // editnya cuma 1. Yang menahannya kini LETAK bedanya: huruf kedua.
        $this->assertSame(1, levenshtein('printer', 'pointer'));

        // Subject-nya masih muncul, tapi HANYA karena "macet" — dan satu kata
        // dari dua tidak cukup untuk mengisi otomatis. Yang penting: "pointer"
        // tidak lagi diakui sebagai kata yang cocok.
        $calon = $matcher->cocokkan('pointer macet');
        $this->assertSame(['macet'], $calon[0]->matchedTerms);
        $this->assertLessThan(SubjectSearch::MIN_CONFIDENCE, $calon[0]->confidence);
        $this->assertNull($matcher->terbaik('pointer macet'), 'Kata beda makna tidak boleh memicu auto-fill.');

        // Penjaga kata pendek tetap bekerja: "sap" tidak jadi "sup".
        $this->assertSame([], $matcher->cocokkan('sup lambat'));

        // Dan typo sungguhan di tengah kata tetap dikenali — kalau ini ikut
        // mati, penjaganya kelewat galak dan pemakai yang menanggung.
        $this->assertNotEmpty($matcher->cocokkan('prnter macet'));
    }

    /**
     * [AMAN] Skor keyakinan Pencarian A dulu ikut membaca kolom `tags`,
     * sehingga tag bisa membajak jawaban tanpa menyentuh isi artikel. Kini
     * teks yang dinilai ditentukan satu tempat — ScoredText — dan tag tidak
     * termasuk di dalamnya.
     */
    public function test_aman_tag_tidak_lagi_membajak_skor_keyakinan(): void
    {
        $scorer = app(ConfidenceScorer::class);
        $tokens = app(QuestionTokenizer::class)->significant('cara klaim tunjangan kesehatan');

        $artikel = new Article([
            'title' => 'SOP Ganti Toner Printer',
            'summary' => 'Buka tutup printer lalu tarik kartrid.',
            'body' => 'Buka tutup printer lalu tarik kartrid.',
            'tags' => 'klaim tunjangan kesehatan',
        ]);

        $faq = new Faq([
            'question' => 'Bagaimana mengganti toner printer?',
            'answer' => 'Buka tutup printer lalu tarik kartrid.',
            'tags' => 'klaim tunjangan kesehatan',
        ]);

        $this->assertSame(0, $scorer->score($tokens, $artikel->title, ScoredText::forArticle($artikel)));
        $this->assertSame(0, $scorer->score($tokens, $faq->question, ScoredText::forFaq($faq)));

        // Isi yang benar-benar relevan tetap dinilai — yang dibuang hanya tag.
        $this->assertGreaterThan(0, $scorer->score(
            $tokens,
            'SOP Klaim Tunjangan Kesehatan',
            ScoredText::forArticle(new Article([
                'summary' => 'Langkah klaim tunjangan kesehatan lewat portal SDM.',
                'body' => 'Langkah klaim tunjangan kesehatan lewat portal SDM.',
            ])),
        ));
    }

    /** [AMAN] Sinonim tidak bisa menyelundupkan wildcard LIKE ke query fallback. */
    public function test_sinonim_tidak_bisa_menyelundupkan_wildcard(): void
    {
        $this->postJson('/eva/api/synonyms', [
            'terms' => '%, _, password', 'is_active' => true,
        ])->assertCreated();

        SynonymExpander::forget();

        foreach (app(SynonymExpander::class)->expandAll(['password']) as $variant) {
            $this->assertDoesNotMatchRegularExpression('/[%_]/', $variant);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | §3 — AUTENTIKASI, IDOR, RATE LIMIT
    |---------------------------------------------------------------------------
    */

    /**
     * [AMAN] Seluruh layar konsol menolak tamu — kini dengan mengantar mereka
     * ke halaman masuk, bukan 401 buntu.
     *
     * Dulu 401 memang jawaban yang benar: belum ada login sama sekali, jadi
     * `route('login')` tidak terdaftar dan redirect apa pun akan berakhir
     * RouteNotFoundException alias 500. Sejak login dipasang, rute itu ada di
     * SEMUA lingkungan (di produksi ia mengalihkan ke SSO), jadi tamu bisa
     * diantar ke tempat yang benar-benar berguna.
     *
     * Yang diuji tetap sama: tak satu pun layar ini terbuka tanpa identitas.
     */
    public function test_aman_semua_layar_eva_menolak_tamu(): void
    {
        $this->sebagaiTamu();

        foreach ([
            '/eva/coverage', '/eva/articles', '/eva/faq', '/eva/documents',
            '/eva/preview', '/eva/unanswered', '/eva/conversations', '/eva/ratings',
            '/eva/analytics', '/eva/apps', '/eva/search-settings', '/eva/taxonomy',
            '/eva/recommendation', '/eva/training',
        ] as $url) {
            $this->assertGuest();
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    /** [AMAN] Endpoint tulis pun tertutup: tamu tidak bisa menghapus FAQ. */
    public function test_aman_endpoint_tulis_menolak_tamu(): void
    {
        $faq = Faq::create([
            'question' => 'Q', 'answer' => 'A', 'is_eva_visible' => true,
            'author_id' => User::first()->id,
        ]);

        $this->sebagaiTamu();

        $this->assertGuest();
        $this->deleteJson("/eva/api/faqs/{$faq->id}")->assertUnauthorized();
        $this->assertNotNull(Faq::find($faq->id), 'FAQ harus utuh setelah tamu ditolak.');
    }

    /**
     * [CELAH] IDOR pada draf tiket: `answer_log_id` dipercaya apa adanya.
     * Siapa pun bisa membalik outcome log MILIK ORANG LAIN jadi ticket_draft —
     * dan itu langsung menggeser angka deflection rate di Analytics serta
     * mengeluarkan pertanyaan itu dari daftar kerja Unanswered.
     */
    public function test_celah_idor_ticket_draft_mengubah_log_orang_lain(): void
    {
        $korban = User::factory()->create(['email' => 'korban@adhi.co.id']);
        $log = $this->answerLog('vpn tidak connect', $korban);

        $this->assertSame(AnswerLog::OUTCOME_NO_ANSWER, $log->outcome);

        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log->id, 'question' => 'apa saja',
        ])->assertOk();

        $this->assertSame(AnswerLog::OUTCOME_TICKET_DRAFT, $log->fresh()->outcome);
    }

    /**
     * [DITUTUP] IDOR percakapan — dulu giliran bisa disisipkan ke percakapan
     * milik orang lain hanya dengan menebak angka `conversation_id`.
     *
     * Ditutup saat widget EVA dipasang di portal: `EvaChat::resolveConversation()`
     * mencari percakapan DALAM MILIK PENANYA, bukan lewat id telanjang, sehingga
     * id asing dibalas 404. Perbaikannya dikerjakan justru pada momen itu karena
     * widget adalah endpoint EVA pertama yang terbuka tanpa penjaga identitas —
     * di konsol admin celah ini masih tertahan middleware `eva.access`.
     *
     * Selama identitas masih persona tunggal, tes ini memang belum membedakan
     * siapa pun secara nyata. Yang dijaganya adalah KODENYA: begitu SSO memberi
     * identitas sungguhan, penyaring ini sudah ada di tempatnya.
     */
    public function test_ditutup_giliran_tidak_bisa_disisipkan_ke_percakapan_orang_lain(): void
    {
        $korban = User::factory()->create(['email' => 'korban2@adhi.co.id']);
        $conversation = Conversation::create([
            'user_id' => $korban->id, 'requester_name' => $korban->name,
            'outcome' => Conversation::OUTCOME_OPEN, 'started_at' => now(),
        ]);

        $this->postJson('/eva/api/preview/ask', [
            'question' => 'kalimat sisipan penyerang',
            'conversation_id' => $conversation->id,
        ])->assertNotFound();

        $this->assertSame(
            0,
            $conversation->turns()->count(),
            'Percakapan korban harus utuh — tidak boleh ada giliran yang tersisip.',
        );
    }

    /** [CELAH] Rating orang lain: penilaian ditulis atas nama persona tetap. */
    public function test_celah_rating_bisa_diberikan_ke_log_milik_siapa_pun(): void
    {
        $korban = User::factory()->create(['email' => 'korban3@adhi.co.id']);
        $log = $this->answerLog('printer error', $korban);

        $this->postJson('/eva/api/preview/rate', [
            'answer_log_id' => $log->id, 'stars' => 1, 'comment' => 'jelek sekali',
        ])->assertOk();

        $this->assertSame(1, AnswerRating::where('answer_log_id', $log->id)->count());
    }

    /** [AMAN] Endpoint pencarian terberat dibatasi 20 request per menit. */
    public function test_aman_rate_limit_menahan_banjir_di_preview_ask(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/eva/api/preview/ask', ['question' => "beban ke-{$i} untuk mesin pencari"])
                ->assertOk();
        }

        $this->postJson('/eva/api/preview/ask', ['question' => 'beban ke-21 untuk mesin pencari'])
            ->assertStatus(429);

        $this->assertSame(20, AnswerLog::count(), 'Request ke-21 ditolak sebelum menyentuh mesin pencari.');
    }

    /**
     * [AMAN] Komentar rating tidak bisa jadi XSS: props React dikirim lewat
     * atribut Blade `{{ }}` yang meng-escape, bukan `{!! !!}`.
     */
    public function test_komentar_rating_tidak_lolos_sebagai_html(): void
    {
        $log = $this->answerLog('printer error');
        $payload = '"><img src=x onerror=alert(document.domain)>';

        $this->postJson('/eva/api/preview/rate', [
            'answer_log_id' => $log->id, 'stars' => 1, 'comment' => $payload,
        ])->assertOk();

        $html = $this->get('/eva/ratings')->assertOk()->getContent();

        // Yang menentukan bukan ada/tidaknya kata "onerror", melainkan apakah
        // kurung sudut dan kutipnya masih bisa MENUTUP atribut. Blade `{{ }}`
        // mengubah keduanya jadi entitas, jadi payload tetap jadi teks.
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('"><img', $html);
        $this->assertStringContainsString('&lt;img src=x', $html);
        $this->assertStringContainsString('onerror=alert', html_entity_decode($html));
    }

    /*
    |---------------------------------------------------------------------------
    | §4 — PIPELINE DOKUMEN & ANTREAN
    |---------------------------------------------------------------------------
    */

    /** [AMAN] XLSX tanpa teks tempel ditolak seketika, bukan gagal diam-diam. */
    public function test_xlsx_ditolak_di_muka(): void
    {
        Storage::fake('local');

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('anggaran.xlsx', 12),
        ])->assertStatus(422)->assertJsonValidationErrors('extracted_text');

        $this->assertSame(0, Document::count());
    }

    /** [AMAN] XLSX boleh masuk kalau teksnya ditempel manual — jalur yang disengaja. */
    public function test_xlsx_boleh_masuk_dengan_teks_tempel(): void
    {
        Storage::fake('local');

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('anggaran.xlsx', 12),
            'extracted_text' => 'Isi yang disalin admin.',
        ])->assertStatus(202);
    }

    /** [AMAN] DOCX tanpa word/document.xml berakhir `failed` DENGAN alasan. */
    public function test_docx_rusak_gagal_dengan_alasan_terbaca(): void
    {
        Storage::fake('local');

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/styles.xml', '<xml/>'); // sengaja: bukan document.xml
        $zip->close();

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($path, 'sop-rusak.docx', null, null, true),
        ])->assertStatus(202);

        $document = Document::firstOrFail();

        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->failure_reason);
        $this->assertStringContainsString('tidak bisa dibaca', $document->failure_reason);

        @unlink($path);
    }

    /**
     * [AMAN] Antrean mati tidak lagi berarti dokumen menggantung selamanya:
     * penyapu terjadwal menutupnya jadi `failed` DENGAN alasan yang menunjuk ke
     * penyebab sebenarnya (worker berhenti), bukan ke isi berkasnya.
     */
    public function test_aman_dokumen_macet_disapu_jadi_gagal(): void
    {
        Storage::fake('local');
        Queue::fake(); // Job masuk antrean tapi tidak ada yang mengerjakannya.

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('sop.txt', 'Langkah 1. Buka aplikasi.'),
        ])->assertStatus(202)->assertJsonPath('status', Document::STATUS_PROCESSING);

        $document = Document::firstOrFail();

        // Belum melewati ambang: dokumen yang masih wajar berjalan tidak boleh
        // ikut divonis gagal.
        $this->artisan('eva:sweep-stuck-documents')->assertSuccessful();
        $this->assertSame(Document::STATUS_PROCESSING, $document->fresh()->status);

        $this->travel(config('eva.stuck_after_minutes') + 1)->minutes();
        $this->artisan('eva:sweep-stuck-documents')->assertSuccessful();

        $this->get("/eva/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('status', Document::STATUS_FAILED)
            ->assertJsonPath('failure_reason', fn (?string $alasan) => $alasan !== null
                && str_contains($alasan, 'antrean kemungkinan berhenti'));
    }

    /**
     * [AMAN] Ekstensi dulu diambil dari NAMA BERKAS KIRIMAN KLIEN, jadi berkas
     * apa pun bisa masuk asal namanya berakhiran .txt. Kini ditentukan dari
     * isinya.
     *
     * Satu sisa yang disengaja: teks yang kebetulan berisi kode sumber (mis.
     * PHP) tetap lolos sebagai TXT — MIME-nya memang teks, dan isinya tidak
     * pernah dieksekusi maupun dirender mentah (lihat tes XSS di §3).
     */
    public function test_aman_ekstensi_ditentukan_isi_bukan_nama_berkas(): void
    {
        Storage::fake('local');

        // Biner yang menyamar jadi .txt: dulu nama berkasnya yang menentukan
        // parser mana yang jalan, jadi isi biner masuk sebagai "teks".
        $path = tempnam(sys_get_temp_dir(), 'menyamar');
        file_put_contents($path, "\x7fELF\x02\x01\x01\x00".str_repeat("\x00", 200));

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($path, 'sop.txt', null, null, true),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, Document::count(), 'Berkas menyamar tidak boleh tersimpan sama sekali.');

        // Berkas teks yang jujur tetap lewat — penjaganya bukan larangan .txt.
        $polos = tempnam(sys_get_temp_dir(), 'polos');
        file_put_contents($polos, 'Langkah 1. Buka aplikasi lalu pilih menu Pengaturan.');

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($polos, 'sop.txt', null, null, true),
        ])->assertStatus(202);

        $this->assertSame('TXT', Document::firstOrFail()->extension);

        @unlink($path);
        @unlink($polos);
    }

    /**
     * [AMAN] Batas 20 MB × ~900 karakter/potongan dulu berarti ribuan INSERT
     * dalam SATU transaksi tanpa penjaga apa pun. Kini dibatasi
     * `eva.max_chunks`, dan pemangkasannya dicatat di log — bukan diam-diam.
     */
    public function test_aman_jumlah_potongan_dibatasi(): void
    {
        Storage::fake('local');

        // Paragraf pendek digabung sampai mendekati CHUNK_SIZE, jadi jumlah
        // paragrafnya harus jauh di atas batas potongan untuk menembusnya.
        $batas = (int) config('eva.max_chunks');
        $paragraf = str_repeat("Langkah prosedur yang panjang sekali.\n\n", $batas * 30);

        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Raksasa', 'extension' => 'TXT', 'extracted_text' => $paragraf,
        ])->assertStatus(202);

        $this->assertSame(
            $batas,
            Document::firstOrFail()->chunks()->count(),
            'Satu unggahan tidak boleh melahirkan baris tanpa batas.',
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Perkakas
    |---------------------------------------------------------------------------
    */

    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert([
            'id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP',
        ]);
        DB::table('service_catalog_subjects')->insert([
            'id' => 1, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => 1,
            'name' => 'Password Expired', 'requires_approval' => false,
            'support_level' => 1, 'is_active' => true,
        ]);
    }

    private function addSubject(int $id, string $service, string $subcategory, string $subject): void
    {
        $serviceId = DB::table('service_catalog_services')->where('name', $service)->value('id');

        if ($serviceId === null) {
            $serviceId = $id * 10;
            DB::table('service_catalog_services')->insert(['id' => $serviceId, 'name' => $service]);
        }

        DB::table('service_catalog_subcategories')->insert([
            'id' => $id, 'service_id' => $serviceId, 'name' => $subcategory,
        ]);
        DB::table('service_catalog_subjects')->insert([
            'id' => $id, 'issue_category_id' => 1, 'service_id' => $serviceId, 'subcategory_id' => $id,
            'name' => $subject, 'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ]);
    }

    private function answerLog(string $question, ?User $asker = null): AnswerLog
    {
        return AnswerLog::create([
            'question' => $question,
            'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
            'asked_by' => ($asker ?? User::where('email', 'andi.pratama@adhi.co.id')->firstOrFail())->id,
            'confidence' => 0,
        ]);
    }

    private function articleFromDocument(string $title, string $body): Article
    {
        $document = Document::create([
            'name' => $title, 'extension' => 'TXT', 'extracted_text' => $body,
            'size_bytes' => mb_strlen($body), 'status' => Document::STATUS_INDEXED,
            'uploaded_by' => User::first()->id,
        ]);

        return Article::create([
            'source_document_id' => $document->id,
            'title' => $title, 'summary' => $body, 'body' => $body,
            'status' => Article::STATUS_PUBLISHED, 'is_eva_visible' => true,
            'author_id' => User::first()->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Ticket;
use App\Services\Knowledge\SubjectMatch;
use App\Services\Knowledge\ServiceMatch;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `eva:mine-ticket-subjects` — daftar tugas menulis dari tiket nyata.
 *
 * Yang dikunci di sini bukan kerapian tabelnya, melainkan tiga hal yang membuat
 * daftarnya boleh dipercaya:
 *
 *  1. **Pemetaan persis dibedakan dari tebakan.** Kalau keduanya tercampur,
 *     tebakan lemah terbaca sama meyakinkannya dengan pemetaan katalog.
 *  2. **Nama subject kembar TIDAK dipetakan sembarangan.** "Reset Password" ada
 *     di dua cabang katalog; menimpanya diam-diam mengarahkan pekerjaan menulis
 *     ke cabang yang salah tanpa satu pun tanda.
 *  3. **Tidak satu baris pun ditulis.** Perintah ini membaca tabel tim
 *     (`tickets`) — mengisi `catalog_subject_id` dari tebakan melanggar aturan
 *     #5.
 */
final class MineTicketSubjectsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,SubjectMatch[]> teks tiket → calon Pencarian B */
    private array $script = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seedCatalog();
        $this->fakeSubjectSearch();
    }

    /**
     * Katalog fixture, sengaja memuat SATU kasus kembar: "Reset Password" dua
     * kali di layanan AKUN APLIKASI, beda sub category — bentuk yang benar-benar
     * ada di katalog tim.
     */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert([
            ['id' => 1, 'name' => 'SAP'],
            ['id' => 2, 'name' => 'AKUN APLIKASI'],
        ]);
        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'INTEGRATION'],
            ['id' => 2, 'service_id' => 1, 'name' => 'PERFORMANCE'],
            ['id' => 3, 'service_id' => 2, 'name' => 'SAP'],
            ['id' => 4, 'service_id' => 2, 'name' => 'SILO (OTHER APPS)'],
        ]);

        $subject = fn (int $id, int $service, int $subcat, string $name) => [
            'id' => $id, 'issue_category_id' => 1, 'service_id' => $service,
            'subcategory_id' => $subcat, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 1, 1, 'Interface Error'),
            $subject(2, 1, 2, 'Not Responding'),
            $subject(3, 2, 3, 'Reset Password'),
            $subject(4, 2, 4, 'Reset Password'),
        ]);
    }

    /** Pencarian B palsu — hanya menjawab teks yang sudah ditulis di skrip. */
    private function fakeSubjectSearch(): void
    {
        $this->app->instance(SubjectSearch::class, new class($this) implements SubjectSearch
        {
            public function __construct(private readonly MineTicketSubjectsTest $test) {}

            public function cocokkan(string $pertanyaan, int $limit = 5): array
            {
                return array_slice($this->test->scriptFor($pertanyaan), 0, $limit);
            }

            public function terbaik(string $pertanyaan): ?SubjectMatch
            {
                return $this->test->scriptFor($pertanyaan)[0] ?? null;
            }

            public function calonSeri(string $pertanyaan): array
            {
                return [];
            }

            // Tes ini menguji jalur SUBJECT. Null berarti "tidak ada layanan
            // yang bisa disimpulkan", sehingga jalur "Lainnya" tidak ikut
            // menyala dan tidak mengaburkan yang sedang diperiksa.
            public function layananTerbaik(string $pertanyaan): ?ServiceMatch
            {
                return null;
            }
        });
    }

    /** @return SubjectMatch[] */
    public function scriptFor(string $text): array
    {
        foreach ($this->script as $needle => $matches) {
            if (str_contains($text, $needle)) {
                return $matches;
            }
        }

        return [];
    }

    private function tebakanUntuk(string $needle, int $subjectId, int $confidence): void
    {
        $this->script[$needle] = [new SubjectMatch(
            subjectId: $subjectId,
            subject: 'Not Responding',
            service: 'SAP',
            subcategory: 'PERFORMANCE',
            issueCategory: 'Access Request',
            confidence: $confidence,
            requiresApproval: false,
            supportLevel: 1,
        )];
    }

    /** Tiket punya banyak kolom WAJIB yang tak ada urusannya dengan perintah ini. */
    private function slaPolicyId(): int
    {
        return DB::table('sla_policies')->insertGetId([
            'policy_name' => 'Standar',
            'priority' => 'Medium',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
        ]);
    }

    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_no' => 'TKT-'.fake()->unique()->numberBetween(1000, 9999),
            'requester_name' => 'Andi Pratama',
            'sla_policy_id' => $this->slaPolicyId(),
            'priority' => 'Medium',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHour(),
            'resolution_due_at' => now()->addDay(),
            'warning_at' => now()->addHours(6),
            'title' => 'Interface Error — SAP',
            'subject_name' => 'Interface Error',
            'service_name' => 'SAP',
            'subcategory_name' => 'INTEGRATION',
            'description' => 'Interface SAP gagal mengirim data ke sistem tetangga.',
            'status' => 'Open',
            'is_draft' => false,
        ], $attributes));
    }

    // ---- pemetaan persis ---------------------------------------------------

    public function test_tiket_dipetakan_persis_lewat_nama_subject_dan_layanan(): void
    {
        $this->ticket();

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('SAP › INTEGRATION › Interface Error')
            ->expectsOutputToContain('Tidak terpetakan: 0 tiket.')
            ->assertSuccessful();
    }

    /** Nama yang unik tetap terpetakan walau nama layanannya berbeda ejaan. */
    public function test_nama_unik_tetap_terpetakan_walau_layanan_beda_ejaan(): void
    {
        $this->ticket(['service_name' => 'SAP ECC']);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('SAP › INTEGRATION › Interface Error')
            ->assertSuccessful();
    }

    /**
     * Kasus yang paling mudah salah: nama kembar di SATU layanan. Tanpa Pencarian
     * B yang bisa membedakan, tiket ini harus berakhir "tidak terpetakan" —
     * bukan mendarat di cabang yang kebetulan terbaca belakangan.
     */
    public function test_nama_subject_kembar_tidak_dipetakan_sembarangan(): void
    {
        $this->ticket([
            'title' => 'Reset Password — AKUN APLIKASI',
            'subject_name' => 'Reset Password',
            'service_name' => 'AKUN APLIKASI',
            'description' => 'Tolong reset password saya.',
        ]);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('Tidak terpetakan: 1 tiket.')
            ->assertSuccessful();
    }

    // ---- tebakan -----------------------------------------------------------

    public function test_subject_asing_jatuh_ke_pencarian_b(): void
    {
        $this->tebakanUntuk('Aplikasi hang', 2, SubjectSearch::SUGGEST_FLOOR);
        $this->ticket([
            'title' => 'Aplikasi hang terus',
            'subject_name' => 'Aplikasi hang',
            'service_name' => 'SAP',
        ]);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('SAP › PERFORMANCE › Not Responding')
            ->expectsOutputToContain('Tidak terpetakan: 0 tiket.')
            ->assertSuccessful();
    }

    /** Ambang diuji pada nilai persis: satu poin di bawah SUGGEST_FLOOR ditolak. */
    public function test_tebakan_terlalu_lemah_tidak_dipakai(): void
    {
        $this->tebakanUntuk('Aplikasi hang', 2, SubjectSearch::SUGGEST_FLOOR - 1);
        $this->ticket([
            'title' => 'Aplikasi hang terus',
            'subject_name' => 'Aplikasi hang',
            'service_name' => 'SAP',
        ]);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('Tidak terpetakan: 1 tiket.')
            ->assertSuccessful();
    }

    // ---- gerbang -----------------------------------------------------------

    /** Draf belum dikirim — sebagian justru lahir dari EVA sendiri (aturan #4). */
    public function test_tiket_draf_tidak_dihitung(): void
    {
        $this->ticket(['is_draft' => true]);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('0 tiket dibaca')
            ->assertSuccessful();
    }

    /** Subject yang materinya sudah ada bukan tugas menulis — disembunyikan. */
    public function test_subject_yang_sudah_bermateri_disembunyikan(): void
    {
        $this->ticket();
        Article::create([
            'title' => 'SOP Interface Error',
            'summary' => 'Ringkasan.',
            'body' => 'Langkah menangani interface error.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
            'catalog_subject_id' => 1,
        ]);

        $this->artisan('eva:mine-ticket-subjects')
            ->expectsOutputToContain('Tidak ada subject bermateri-kosong')
            ->expectsOutputToContain('Sudah punya materi: 1 subject (1 tiket).')
            ->assertSuccessful();

        $this->artisan('eva:mine-ticket-subjects --all')
            ->expectsOutputToContain('SAP › INTEGRATION › Interface Error')
            ->assertSuccessful();
    }

    // ---- invarian ----------------------------------------------------------

    /** Membaca tabel tim tidak boleh meninggalkan jejak apa pun di sana. */
    public function test_tidak_menulis_satu_baris_pun(): void
    {
        $ticket = $this->ticket();

        $this->artisan('eva:mine-ticket-subjects')->assertSuccessful();

        $this->assertNull($ticket->fresh()->catalog_subject_id, 'aturan #5: kolom katalog tim tidak disentuh');
        $this->assertSame(1, Ticket::count());
        $this->assertSame(0, Article::count());
    }
}

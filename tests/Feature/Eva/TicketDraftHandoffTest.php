<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Ticket;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatch;
use App\Services\Knowledge\ServiceMatch;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Serah-terima draf dari widget EVA ke form Buat Tiket milik Requester.
 *
 * Dua halaman berbeda dengan dua pemuatan berbeda, jadi draf harus dititipkan
 * di suatu tempat. Yang dijaga di sini adalah kontrak titipan itu:
 *
 *   1. Draf mendarat di SESI, bukan cuma di badan JSON. Balasan JSON hanya
 *      hidup selama gelembung chat; begitu karyawan mengeklik tautannya,
 *      halaman berganti dan balasan itu hilang.
 *   2. Draf SEKALI PAKAI. Kalau tidak dibuang setelah dibaca, form akan
 *      membuka diri sendiri dan terisi lagi setiap kali karyawan kembali ke
 *      dashboard — termasuk berhari-hari kemudian.
 *   3. Aturan #4 tidak goyah: menitipkan draf tetap tidak menerbitkan tiket.
 */
final class TicketDraftHandoffTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Widget maupun dashboard requester sama-sama menuntut sesi ber-role
        // Requester: yang pertama lewat `auth`, yang kedua lewat `role:requester`.
        $this->actingAsRole('requester');

        // Pencarian dan pencocokan subject dipalsukan lewat INTERFACE-nya,
        // bukan lewat kelas konkretnya: SubjectMatcher final dan tidak bisa
        // di-mock, dan tes ini tidak sedang menguji kualitas pencocokan.
        $this->instance(KnowledgeSearch::class, new class implements KnowledgeSearch
        {
            /** @return SearchHit[] */
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return [];
            }
        });

        $this->subjectTebakan(null);
    }

    public function test_draf_dititipkan_ke_sesi_supaya_selamat_melewati_pindah_halaman(): void
    {
        $logId = $this->mintaDraf('printer lantai 7 macet total');

        $this->assertNotNull($logId);
        $this->assertSame(
            'printer lantai 7 macet total',
            session('eva.ticket_draft.description'),
        );
    }

    public function test_dashboard_requester_menyerahkan_draf_ke_props_lalu_membuangnya(): void
    {
        $this->mintaDraf('printer lantai 7 macet total');

        $this->get(route('dashboard.requester'))
            ->assertOk()
            ->assertSee('printer lantai 7 macet total', escape: false);

        // Sudah diserahkan → sesi harus bersih, supaya kunjungan berikutnya
        // tidak membuka form yang terisi sendiri tanpa diminta.
        $this->assertNull(session('eva.ticket_draft'));

        $this->get(route('dashboard.requester'))
            ->assertOk()
            ->assertDontSee('printer lantai 7 macet total', escape: false);
    }

    public function test_subject_tebakan_ikut_dititipkan_supaya_katalog_bisa_terisi(): void
    {
        $this->subjectTebakan(new SubjectMatch(
            subjectId: 41,
            subject: 'Printer Jaringan',
            service: 'PERANGKAT',
            subcategory: 'Printer',
            issueCategory: 'Incident',
            confidence: 88,
            requiresApproval: false,
            supportLevel: 1,
        ));

        $this->mintaDraf('printer lantai 7 macet total');

        $this->assertSame(41, session('eva.ticket_draft.subject.subject_id'));
    }

    public function test_menitipkan_draf_tetap_tidak_menerbitkan_tiket(): void
    {
        $sebelum = Ticket::count();

        $this->mintaDraf('printer lantai 7 macet total');

        $this->assertSame($sebelum, Ticket::count());
        $this->assertSame(AnswerLog::OUTCOME_TICKET_DRAFT, AnswerLog::sole()->outcome);
    }

    /** EVA menyerah pada pertanyaannya, lalu drafnya diminta. */
    /**
     * Keadaan ketiga: EVA tahu APLIKASINYA, tidak tahu masalahnya.
     *
     * Sebelumnya draf hanya mengenal "tahu subject" atau "tidak tahu apa-apa",
     * dan karyawan yang mengetik nama aplikasinya sendiri tetap ditinggal di
     * form kosong berisi 19 Layanan. Sekarang Layanan itu ikut dititipkan,
     * supaya form terbuka pada jalur "Lainnya" milik aplikasi yang benar.
     */
    public function test_layanan_dititipkan_saat_tak_ada_subject_yang_cocok(): void
    {
        $this->subjectTebakan(null, new ServiceMatch(
            serviceId: 7,
            service: 'ELISA',
            issueCategories: ['Incident'],
            matchedTerms: ['elisa'],
        ));

        $this->mintaDraf('bagaimana saya bisa melaporkan ketika ada kendala di elisa');

        $this->assertSame(7, session('eva.ticket_draft.service.service_id'));
        $this->assertSame('ELISA', session('eva.ticket_draft.service.service'));

        // Layanan ini cuma punya satu Issue Category, jadi mengisikannya bukan
        // tebakan melainkan satu-satunya kemungkinan.
        $this->assertSame('Incident', session('eva.ticket_draft.service.issue_category'));

        // Aturan #4 tetap: menitipkan draf tidak menerbitkan tiket.
        $this->assertSame(0, Ticket::count());
    }

    /**
     * Jalur "Lainnya" adalah JARING PENGAMAN, bukan pilihan pertama.
     *
     * Selama masih ada subject yang layak dilihat manusia, draf harus diam dan
     * membiarkan karyawan memilih dari daftar. Tanpa penjaga ini "Lainnya"
     * pelan-pelan jadi keranjang terbesar, tiketnya di-broadcast ke seluruh PIC
     * alih-alih satu orang, dan katalog berhenti tumbuh karena tak ada lagi
     * yang menunjukkan subject mana yang kurang.
     */
    public function test_layanan_tidak_dipakai_saat_masih_ada_calon_subject(): void
    {
        $calon = new SubjectMatch(
            subjectId: 12,
            subject: 'Reset Password',
            service: 'AKUN APLIKASI',
            subcategory: 'SAP',
            issueCategory: 'Access Request',
            confidence: 44,
            requiresApproval: false,
            supportLevel: 1,
        );

        $this->subjectTebakan(null, new ServiceMatch(serviceId: 7, service: 'ELISA'), [$calon]);

        $this->mintaDraf('reset password');

        $this->assertNull(session('eva.ticket_draft.service'));
    }

    private function mintaDraf(string $pertanyaan): int
    {
        $ask = $this->postJson(route('eva.assistant.ask'), ['question' => $pertanyaan]);
        $ask->assertOk()->assertJsonPath('type', 'no_answer');

        $this->postJson(route('eva.assistant.ticket-draft'), [
            'answer_log_id' => $ask->json('answer_log_id'),
            'question' => $pertanyaan,
        ])->assertOk();

        return (int) $ask->json('answer_log_id');
    }

    /**
     * @param  SubjectMatch[]  $calon  calon yang tetap muncul walau terbaik()
     *                                 menyerah — dipakai menguji bahwa jalur
     *                                 "Lainnya" TIDAK menyala saat masih ada
     *                                 subject yang layak dilihat manusia
     */
    private function subjectTebakan(?SubjectMatch $match, ?ServiceMatch $layanan = null, array $calon = []): void
    {
        $this->instance(SubjectSearch::class, new class($match, $layanan, $calon) implements SubjectSearch
        {
            public function __construct(
                private readonly ?SubjectMatch $match,
                private readonly ?ServiceMatch $layanan,
                private readonly array $calon,
            ) {}

            /** @return SubjectMatch[] */
            public function cocokkan(string $pertanyaan, int $limit = 5): array
            {
                if ($this->calon !== []) {
                    return $this->calon;
                }

                return $this->match === null ? [] : [$this->match];
            }

            public function terbaik(string $pertanyaan): ?SubjectMatch
            {
                return $this->match;
            }

            /** @return SubjectMatch[] */
            public function calonSeri(string $pertanyaan): array
            {
                return [];
            }

            public function layananTerbaik(string $pertanyaan): ?ServiceMatch
            {
                return $this->layanan;
            }
        });
    }
}

<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\DismissedQuestion;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\KnowledgeStats;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Unanswered Questions — celah materi, langsung dari perilaku karyawan.
 *
 * Yang penting soal layar ini: sebuah pertanyaan dianggap masih jadi celah
 * kalau DITANYAKAN ULANG SEKARANG pun EVA tetap tidak menemukan jawaban.
 * Statusnya tidak disimpan sebagai kolom dan tidak ada tombol "tandai selesai".
 *
 * Konsekuensinya disengaja: begitu admin menulis FAQ atau mengunggah dokumen
 * yang menutup celah itu, barisnya hilang sendiri dari daftar ini. Daftar yang
 * statusnya disimpan manual selalu berakhir bohong — orang menandai selesai
 * padahal materinya tidak pernah dibuat, atau lupa menandai padahal sudah.
 *
 * TOMBOL SINGKIRKAN BUKAN PENGECUALIAN DARI ITU. Ia tidak menyatakan "celah ini
 * sudah selesai" — pemeriksaan ulang di atas tetap satu-satunya yang berhak
 * menyatakannya. Yang ia urus adalah pertanyaan yang TIDAK AKAN PERNAH dijawab
 * materi (salah ketik, sapaan, permintaan pribadi): tanpa jalan keluar,
 * baris-baris itu menumpuk selamanya dan mendorong pekerjaan nyata ke luar
 * batas 40. Keputusannya pun kedaluwarsa sendiri begitu pertanyaannya
 * ditanyakan lagi — lihat DismissedQuestion::hiddenQuestions().
 */
class UnansweredController extends Controller
{
    /** Log tak terjawab yang diperiksa ulang. Cukup untuk daftar kerja nyata. */
    private const CANDIDATE_LIMIT = 40;

    public function __construct(
        private readonly KnowledgeStats $stats,
        private readonly KnowledgeSearch $search,
    ) {}

    public function index(): View
    {
        $hidden = DismissedQuestion::hiddenQuestions();

        $candidates = $this->stats->topUnansweredQuestions(self::CANDIDATE_LIMIT, $hidden);

        $gaps = $candidates
            ->map(fn (array $row) => [...$row, ...$this->recheck($row['question'])])
            ->values();

        return view('eva.unanswered', [
            'gaps' => $gaps->where('is_still_gap', true)->values()->all(),
            'closed' => $gaps->where('is_still_gap', false)->values()->all(),
            'threshold' => KnowledgeSearch::MIN_CONFIDENCE,
            'endpoints' => [
                'dismiss' => route('eva.unanswered.dismiss'),
                'dismissMany' => route('eva.unanswered.dismiss-many'),
            ],
            'links' => [
                'faq' => route('eva.faq'),
                'documents' => route('eva.documents'),
            ],
        ]);
    }

    /**
     * Menyingkirkan pertanyaan dari daftar kerja. TIDAK menghapus apa pun dari
     * kb_answer_logs — riwayat dan seluruh angka Analytics tetap utuh.
     *
     * Aman ditekan dua kali: keputusan disimpan per TEKS pertanyaan, jadi
     * penekanan kedua hanya memperbarui waktunya.
     */
    public function dismiss(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:500']);

        $dismissal = DismissedQuestion::updateOrCreate(
            ['question' => $data['question']],
            ['dismissed_at' => now(), 'dismissed_by' => CurrentActor::admin()->id],
        );

        return response()->json($this->present($dismissal));
    }

    /**
     * Menyingkirkan beberapa pertanyaan dalam SATU permintaan.
     *
     * Dipakai tombol "Hapus semua" pada daftar "Telah terjawab". Sengaja bukan
     * pemanggilan `dismiss()` berulang dari klien: kegagalan di tengah 20
     * permintaan meninggalkan separuh terhapus tanpa ada yang tahu baris mana,
     * dan layar tidak punya cara jujur melaporkannya.
     *
     * Batas 200 bukan hiasan: daftar kerja hanya memeriksa 40 kandidat, jadi
     * muatan yang jauh lebih besar dari itu pasti bukan datang dari layar.
     */
    public function dismissMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'questions' => 'required|array|min:1|max:200',
            'questions.*' => 'required|string|max:500',
        ]);

        $adminId = CurrentActor::admin()->id;

        // Satu transaksi: sebagian terhapus lebih buruk daripada tidak terhapus
        // sama sekali, karena admin membaca "Hapus semua" sebagai tuntas.
        DB::transaction(function () use ($data, $adminId) {
            foreach (array_unique($data['questions']) as $question) {
                DismissedQuestion::updateOrCreate(
                    ['question' => $question],
                    ['dismissed_at' => now(), 'dismissed_by' => $adminId],
                );
            }
        });

        return response()->json(['dismissed' => count(array_unique($data['questions']))]);
    }

    /**
     * Menarik kembali keputusan menyingkirkan.
     *
     * TIDAK lagi dipanggil layar mana pun: sejak "Hapus" berarti barisnya
     * langsung hilang, daftar "Dihapus dari daftar kerja" beserta tombol
     * Kembalikan-nya ditiadakan. Endpoint ini sengaja dipertahankan sebagai
     * satu-satunya jalan membatalkan penghapusan yang keliru — dipanggil
     * manual, bukan dari konsol.
     */
    public function restore(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:500']);

        DismissedQuestion::where('question', $data['question'])->delete();

        return response()->json(['question' => $data['question'], 'restored' => true]);
    }

    /** @return array<string,mixed> */
    private function present(DismissedQuestion $dismissal): array
    {
        return [
            'question' => $dismissal->question,
            'dismissed_at' => $dismissal->dismissed_at?->diffForHumans(),
            'dismissed_by_name' => $dismissal->dismissedBy?->name,
        ];
    }

    /**
     * Jalankan ulang pertanyaannya lewat pencarian yang sama dengan EVA.
     * Kandidat terbaik yang masih di bawah ambang = celah belum tertutup.
     */
    private function recheck(string $question): array
    {
        $best = $this->search->cari($question, 1)[0] ?? null;

        return [
            'is_still_gap' => $best === null || $best->confidence < KnowledgeSearch::MIN_CONFIDENCE,
            'best_match_title' => $best?->title,
            'best_match_confidence' => $best?->confidence ?? 0,
        ];
    }
}

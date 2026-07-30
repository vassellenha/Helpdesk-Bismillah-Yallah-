<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Log Percakapan — membaca percakapan karyawan dengan EVA apa adanya.
 *
 * Gunanya bukan statistik (itu ada di Analytics), melainkan membaca kalimat
 * aslinya: di mana EVA salah paham, di mana jawabannya benar tapi tidak
 * menolong, di mana karyawan menyerah lalu minta tiket.
 */
class ConversationController extends Controller
{
    private const PER_PAGE = 60;

    public function index(): View
    {
        $conversations = Conversation::with(['turns', 'user:id,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn (Conversation $conversation) => $this->present($conversation));

        return view('eva.conversations', [
            'conversations' => $conversations,
            'stats' => [
                'total' => Conversation::count(),
                'answered' => Conversation::where('outcome', Conversation::OUTCOME_ANSWERED)->count(),
                'ticket' => Conversation::where('outcome', Conversation::OUTCOME_TICKET)->count(),
                'abandoned' => Conversation::where('outcome', Conversation::OUTCOME_ABANDONED)->count(),
            ],
            'showing' => $conversations->count(),
        ]);
    }

    /**
     * Menghapus TRANSKRIP percakapan — bukan riwayat pertanyaannya.
     *
     * kb_conversation_turns ikut terhapus (FK `cascadeOnDelete`), tapi
     * kb_answer_logs TIDAK: kolom `conversation_id`-nya `nullOnDelete`, jadi
     * barisnya tetap ada, hanya tautannya lepas. Itu satu-satunya cara
     * "menghapus log percakapan" yang aman — kb_answer_logs adalah sumber
     * tunggal Analytics, Unanswered Questions, dan deflection rate, dan
     * menghapusnya berarti mengubah angka bulan lalu tanpa sadar.
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    private function present(Conversation $conversation): array
    {
        $turns = $conversation->turns;

        return [
            'id' => $conversation->id,
            'requester_name' => $conversation->requester_name ?? $conversation->user?->name ?? 'Tanpa nama',
            'department' => $conversation->department,
            'outcome' => $conversation->outcome,
            'ticket_reference' => $conversation->ticket_reference,
            'started_at' => $conversation->started_at?->diffForHumans(),
            // Pertanyaan pembuka dipakai sebagai judul baris — itu yang dicari
            // orang saat menelusuri log, bukan nomor percakapan.
            'opening_question' => $turns->firstWhere('role', ConversationTurn::ROLE_USER)?->message ?? '—',
            'confidence' => $turns->max('confidence'),
            'turn_count' => $turns->count(),
            'turns' => $turns->map(fn (ConversationTurn $turn) => [
                'id' => $turn->id,
                'role' => $turn->role,
                'message' => $turn->message,
                'confidence' => $turn->confidence,
                'is_clarifying' => $turn->is_clarifying,
                'source_label' => $turn->source_type ? class_basename($turn->source_type).' #'.$turn->source_id : null,
            ])->all(),
        ];
    }
}

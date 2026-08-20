<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\EvaChat;
use App\Services\Knowledge\KnowledgeSearch;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * EVA Preview — mencoba EVA persis seperti yang dilihat karyawan.
 *
 * Yang membuat layar ini berguna: ia memakai EvaChat yang sama dengan widget
 * di portal. Setiap pertanyaan di sini benar-benar tercatat di kb_answer_logs,
 * jadi mencoba EVA otomatis mengisi Unanswered Questions.
 */
class PreviewController extends Controller
{
    public function __construct(private readonly EvaChat $chat) {}

    public function index(): View
    {
        return view('eva.preview', [
            'endpoints' => [
                'ask' => route('eva.preview.ask'),
                'rate' => route('eva.preview.rate'),
                'note' => route('eva.preview.note'),
                'ticketDraft' => route('eva.preview.ticket-draft'),
                // Sengaja menunjuk rute widget, bukan kembaran di grup preview.
                // Isi materi yang dibaca admin di sini HARUS sama persis dengan
                // yang dibaca karyawan — Preview kehilangan gunanya begitu ia
                // memperlihatkan sesuatu yang berbeda dari kenyataan.
                'material' => route('eva.assistant.material', ['type' => '__type__', 'id' => '__id__']),
            ],
            'thresholds' => [
                'min_confidence' => KnowledgeSearch::MIN_CONFIDENCE,
                'hedge_confidence' => KnowledgeSearch::HEDGE_CONFIDENCE,
            ],
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate(EvaChat::ASK_RULES);

        return response()->json($this->chat->ask(
            $data['question'],
            $data['conversation_id'] ?? null,
            CurrentActor::requester(),
        ));
    }

    /**
     * Bintang dari karyawan. Sekali nilai per jawaban — ditegakkan unique di
     * database, jadi percobaan kedua ditolak dengan pesan yang jelas, bukan
     * diam-diam menimpa nilai pertama.
     */
    public function rate(Request $request): JsonResponse
    {
        $data = $request->validate(EvaChat::rateRules());

        $accepted = $this->chat->rate(
            $data['answer_log_id'],
            $data['stars'],
            $data['reason'] ?? null,
            $data['comment'] ?? null,
            CurrentActor::requester(),
        );

        if (! $accepted) {
            return response()->json([
                'message' => 'Jawaban ini sudah Anda nilai sebelumnya.',
            ], 409);
        }

        return response()->json(['rated' => true]);
    }

    /**
     * Catatan tertulis yang menyertai bintang.
     *
     * Sebelumnya endpoint ini hanya ada di widget portal, sehingga kotak ulasan
     * tidak bisa ditampilkan di Preview — dan Preview jadi tidak lagi
     * memperlihatkan apa yang sebenarnya dilihat user, padahal itu seluruh
     * gunanya. Isinya sengaja sama persis dengan AssistantController::note:
     * keduanya memanggil EvaChat::annotate yang sama.
     */
    public function note(Request $request): JsonResponse
    {
        $data = $request->validate(EvaChat::NOTE_RULES);

        $attached = $this->chat->annotate(
            $data['answer_log_id'],
            $data['reason'] ?? null,
            $data['comment'] ?? null,
            CurrentActor::requester(),
        );

        if (! $attached) {
            return response()->json([
                'message' => 'Catatan hanya bisa dilampirkan pada jawaban yang sudah Anda nilai.',
            ], 409);
        }

        return response()->json(['noted' => true]);
    }

    /**
     * EVA berhenti di DRAF (aturan #4).
     *
     * Endpoint ini tidak menulis satu baris pun ke tabel tiket. Nomor tiket
     * terbit setelah karyawan mengirim sendiri lewat form Requester —
     * penomoran dan SLA milik sistem Helpdesk, bukan asisten.
     */
    public function ticketDraft(Request $request): JsonResponse
    {
        $data = $request->validate(EvaChat::TICKET_DRAFT_RULES);

        return response()->json(
            $this->chat->ticketDraft($data['answer_log_id'], $data['question']),
        );
    }
}

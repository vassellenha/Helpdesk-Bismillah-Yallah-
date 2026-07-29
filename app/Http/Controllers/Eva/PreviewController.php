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
                'ticketDraft' => route('eva.preview.ticket-draft'),
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

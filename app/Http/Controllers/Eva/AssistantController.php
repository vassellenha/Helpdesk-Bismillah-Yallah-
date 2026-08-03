<?php

declare(strict_types=1);

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\EvaChat;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Permukaan EVA untuk KARYAWAN — widget mengambang di portal.
 *
 * Bedanya dengan PreviewController bukan pada logikanya (keduanya memanggil
 * EvaChat yang sama persis), melainkan pada SIAPA yang boleh masuk. Preview
 * duduk di balik middleware `eva.access` karena ia bagian konsol admin; widget
 * ini justru harus terbuka bagi siapa pun yang membuka portal — di situlah
 * pertanyaan karyawan yang sesungguhnya berasal.
 *
 * Konsekuensi yang tidak boleh dilupakan: ini endpoint EVA pertama yang tidak
 * dijaga identitas sama sekali. Yang menahannya sekarang hanya throttle per
 * route dan CSRF bawaan grup `web`. Begitu SSO ADHI terpasang, di sinilah
 * identitas sungguhnya masuk — `CurrentActor::requester()` di bawah adalah
 * persona sementara, bukan penanya yang nyata.
 */
final class AssistantController extends Controller
{
    public function __construct(private readonly EvaChat $chat) {}

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
     * Bintang berikut catatan dari karyawan.
     *
     * `reason` dan `comment` sudah lama ada di kb_answer_ratings dan sudah
     * lama divalidasi, tapi belum pernah ada satu pun kotak teks yang bisa
     * mengisinya — panel tanggapan di layar Rating & Feedback selama ini
     * membaca kolom yang selalu kosong. Widget inilah sumber isi yang
     * ditunggu: kalimat karyawan, bukan catatan admin atas percobaannya
     * sendiri.
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
     * Catatan menyusul setelah bintang terkirim.
     *
     * Dipisah dari `rate()` supaya bintangnya sudah aman tercatat sebelum
     * karyawan mulai mengetik — kalau keduanya menunggu satu tombol, widget
     * yang ditutup di tengah menulis membuang penilaiannya juga.
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
     * EVA berhenti di DRAF (aturan #4) — tidak ada satu baris pun ditulis ke
     * tabel tiket dari sini.
     */
    public function ticketDraft(Request $request): JsonResponse
    {
        $data = $request->validate(EvaChat::TICKET_DRAFT_RULES);
        $result = $this->chat->ticketDraft($data['answer_log_id'], $data['question']);

        /*
         | Draf dititipkan ke SESI, bukan cukup dikembalikan sebagai JSON.
         |
         | Widget hidup di halaman portal, sedangkan form Buat Tiket ada di
         | dashboard Requester — halaman lain, pemuatan lain. Begitu karyawan
         | mengeklik tautannya, balasan JSON tadi ikut hilang bersama halamannya.
         |
         | Titipannya ditulis di CONTROLLER, bukan di dalam EvaChat: layanan itu
         | juga dipakai EVA Preview milik admin, dan Preview tidak boleh
         | diam-diam mengisi form Buat Tiket milik siapa pun.
         */
        $request->session()->put('eva.ticket_draft', $result['draft']);

        return response()->json($result);
    }
}

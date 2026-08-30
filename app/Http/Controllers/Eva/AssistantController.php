<?php

declare(strict_types=1);

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\EvaChat;
use App\Support\CurrentActor;
use App\Support\Eva\MaterialLookup;
use App\Support\Eva\SourceDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            // Dicor di BATAS MASUK, bukan dilonggarkan di hilir. Widget mengirim
            // form (lihat resources/js/lib/api.js), dan pada form setiap nilai
            // tiba sebagai string — aturan `integer` meloloskan "213" tanpa
            // mengubahnya. Melonggarkan tipe EvaChat menjadi `int|string` akan
            // memindahkan kelalaian ini ke seluruh pemanggil; yang benar adalah
            // memastikan bentuknya SEKALI, di tempat data mentah masuk.
            isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            CurrentActor::requester(),
        ));
    }

    /**
     * Materi yang dikutip sebuah jawaban, BERIKUT dokumen aslinya.
     *
     * Judul sumber di bawah jawaban selama ini teks mati: karyawan tahu
     * panduannya bernama apa, tapi tidak punya jalan membacanya tanpa
     * meninggalkan percakapan. Endpoint ini yang membukanya di tempat.
     *
     * Yang dipulangkan bukan hanya artikel hasil ekstraksi, melainkan juga
     * keterangan dokumen sumbernya — nama berkas, formatnya, dan alamat untuk
     * membukanya. Popup memakai keterangan itu untuk menampilkan DOKUMENNYA,
     * dan hanya jatuh ke teks bila berkasnya memang tidak ada.
     *
     * Jenis dan nomor yang tidak lolos gerbang MaterialLookup dijawab 404 yang
     * sama — tidak dibedakan antara "tidak ada" dan "ada tapi disembunyikan".
     * Membedakannya akan memberi tahu penebak nomor bahwa materi itu memang
     * ada, dan itu satu-satunya hal yang ingin diketahuinya.
     */
    public function material(string $type, int $id): JsonResponse
    {
        $material = MaterialLookup::find($type, $id);

        abort_if($material === null, 404, 'Materi tidak ditemukan.');

        return response()->json($material);
    }

    /**
     * Berkas asli dokumen yang dikutip — satu-satunya pintu keluarnya.
     *
     * Berkasnya disimpan di disk privat (storage/app/private), di luar document
     * root, jadi tidak ada jalan lain ke sana selain lewat sini. Karena itu
     * gerbangnya harus lengkap, dan ketiganya menjawab 404 yang SAMA:
     *   - dokumen yang tidak boleh dikutip (scopeQuotable) — tidak dibedakan
     *     dari yang tidak ada, supaya penebak nomor tidak belajar apa pun;
     *   - dokumen yang memang tidak punya berkas (isinya diketik admin);
     *   - baris yang berkasnya sudah hilang dari disk.
     *
     * `inline`, bukan `attachment`: PDF dibaca langsung di dalam popup. Format
     * yang tidak bisa dirender browser tetap berakhir sebagai unduhan — itu
     * keputusan browser, dan popup sudah menyiapkan karyawan untuk itu.
     */
    public function documentFile(Document $document): StreamedResponse
    {
        abort_unless(
            Document::query()->quotable()->whereKey($document->getKey())->exists(),
            404,
            'Dokumen tidak ditemukan.',
        );

        abort_unless($document->hasFile(), 404, 'Dokumen ini tidak punya berkas asli.');

        abort_unless(
            Storage::disk('local')->exists((string) $document->storage_path),
            404,
            'Berkas dokumen tidak ada di penyimpanan.',
        );

        $filename = SourceDocument::filename($document);

        return Storage::disk('local')->response((string) $document->storage_path, $filename, [
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            // Sudah diperiksa per permintaan; menyimpannya di cache bersama
            // (proxy kantor) berarti orang berikutnya bisa menerima berkas yang
            // bukan haknya tanpa pernah menyentuh gerbang di atas.
            'Cache-Control' => 'private, no-store',
        ]);
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
            (int) $data['answer_log_id'],
            (int) $data['stars'],
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
            (int) $data['answer_log_id'],
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
        $result = $this->chat->ticketDraft((int) $data['answer_log_id'], $data['question']);

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

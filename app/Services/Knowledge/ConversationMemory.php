<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;

/**
 * Beberapa giliran terakhir sebuah percakapan, siap dikirim sebagai konteks.
 *
 * Sampai sekarang `kb_conversation_turns` hanya ditulis, tidak pernah dibaca
 * kembali oleh jalur menjawab — EvaResponder menerima objek Conversation tapi
 * memakainya semata untuk mencatat log. Akibatnya setiap pertanyaan dijawab
 * dari nol, dan pertanyaan lanjutan seperti "kalau masih gagal gimana?" dicari
 * di KB apa adanya lalu berakhir tidak terjawab. Kelas ini yang membuka
 * ingatan itu.
 *
 * Murni pembacaan basis data, tanpa panggilan model. Dipisah dari
 * ConversationEngine dengan sengaja: berapa banyak yang diingat dan bagaimana
 * bentuknya adalah aturan produk yang harus bisa diuji tanpa jaringan sama
 * sekali.
 */
final class ConversationMemory
{
    /**
     * Enam giliran — kira-kira tiga tanya-jawab.
     *
     * Bukan angka yang dipilih demi hemat token, melainkan demi KETEPATAN:
     * konteks yang terlalu panjang membuat model menarik pertanyaan lanjutan
     * kembali ke topik lama yang sudah selesai. Percakapan helpdesk juga
     * pendek — karyawan datang dengan satu kendala, bukan untuk berbincang.
     */
    public const MAX_TURNS = 6;

    /**
     * Giliran terakhir percakapan, urut dari yang paling lama ke paling baru.
     *
     * Urutannya dibaca dari kolom `ordinal`, BUKAN dari urutan penyimpanan:
     * keduanya kebetulan sama hari ini, tapi ordinal-lah yang menjadi janji
     * urutan percakapan di skema, dan tes yang ada sudah menegakkannya.
     *
     * @return list<array{role: string, message: string}> kosong bila belum ada
     *                                                    percakapan atau belum
     *                                                    ada giliran sama sekali
     */
    public static function recall(?Conversation $conversation, int $max = self::MAX_TURNS): array
    {
        if ($conversation === null || $max <= 0) {
            return [];
        }

        /*
         | `reorder()`, BUKAN `orderByDesc()`.
         |
         | Relasi turns() sudah membawa orderBy('ordinal') menaik. Menambah
         | orderByDesc hanya menempelkan klausa KEDUA — "ORDER BY ordinal ASC,
         | ordinal DESC" — yang tidak pernah berlaku, sehingga limit() memungut
         | giliran TERTUA. Gejalanya diam dan menyesatkan: EVA mengingat pembuka
         | percakapan dan melupakan kalimat yang barusan diucapkan, persis
         | kebalikan dari yang dibutuhkan pertanyaan lanjutan.
        */
        return $conversation->turns()
            ->reorder('ordinal', 'desc')
            ->limit($max)
            ->get(['role', 'message'])
            ->reverse()
            ->values()
            ->map(fn (ConversationTurn $turn) => [
                'role' => $turn->role,
                'message' => (string) $turn->message,
            ])
            ->all();
    }

    /**
     * Ingatan itu dalam bentuk transkrip untuk disisipkan ke prompt.
     *
     * Diberi label "Karyawan"/"EVA", bukan "user"/"assistant": isi ini masuk
     * sebagai SATU pesan konteks, bukan sebagai riwayat percakapan model. Kalau
     * dikirim sebagai riwayat asli, model memperlakukan jawaban EVA terdahulu
     * sebagai ucapannya sendiri yang boleh dilanjutkan — dan mulai menambah
     * keterangan yang tidak pernah ada di KB.
     *
     * @param  list<array{role: string, message: string}>  $memory
     */
    public static function transcript(array $memory): string
    {
        if ($memory === []) {
            return '';
        }

        $lines = array_map(
            fn (array $turn) => ($turn['role'] === ConversationTurn::ROLE_USER ? 'Karyawan' : 'EVA')
                .': '.trim($turn['message']),
            $memory,
        );

        return implode("\n", $lines);
    }
}

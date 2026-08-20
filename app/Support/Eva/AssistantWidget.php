<?php

declare(strict_types=1);

namespace App\Support\Eva;

/**
 * Prop yang ditanam ke widget EVA di setiap halaman yang memasangnya.
 *
 * Berdiri sendiri, bukan method controller, karena yang memanggilnya adalah
 * LAYOUT — bukan satu aksi. Layout yang mengimpor controller demi satu array
 * konfigurasi akan menyeret seluruh dependensinya ke halaman yang bahkan tidak
 * memanggil aksi apa pun.
 *
 * Ambang keyakinan SENGAJA tidak dikirim. Widget tidak lagi menampilkan angka
 * keyakinan maupun ambangnya — keduanya alat kerja admin, dan tempatnya di EVA
 * Preview. Prop yang tidak dibaca siapa pun tetap ikut terkirim ke setiap
 * halaman berwidget, lalu perlahan terbaca sebagai sesuatu yang masih dipakai.
 */
final class AssistantWidget
{
    /**
     * @param  int  $offsetBottom  jarak dari dasar layar. Dinaikkan pada layout
     *                             yang pojok kanan bawahnya sudah terpakai
     *                             tombol lain, supaya keduanya tidak bertumpuk.
     * @return array<string, mixed>
     */
    public static function props(int $offsetBottom = 24): array
    {
        return [
            'endpoints' => [
                'ask' => route('eva.assistant.ask'),
                'rate' => route('eva.assistant.rate'),
                'note' => route('eva.assistant.note'),
                'ticketDraft' => route('eva.assistant.ticket-draft'),
                // Alamat bercetakan: klien mengganti __type__ dan __id__ dengan
                // milik materi yang diklik. Dibangun lewat route() supaya
                // bentuk alamatnya tetap ditentukan satu tempat — daftar rute —
                // bukan disusun ulang dengan tangan di dalam JavaScript.
                'material' => route('eva.assistant.material', ['type' => '__type__', 'id' => '__id__']),
            ],
            'offsetBottom' => $offsetBottom,
        ];
    }
}

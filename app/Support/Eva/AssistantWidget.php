<?php

declare(strict_types=1);

namespace App\Support\Eva;

use App\Services\Knowledge\KnowledgeSearch;

/**
 * Prop yang ditanam ke widget EVA di setiap halaman yang memasangnya.
 *
 * Berdiri sendiri, bukan method controller, karena yang memanggilnya adalah
 * LAYOUT — bukan satu aksi. Layout yang mengimpor controller demi satu array
 * konfigurasi akan menyeret seluruh dependensinya ke halaman yang bahkan tidak
 * memanggil aksi apa pun.
 *
 * Ambang keyakinan ikut dikirim supaya widget tidak mengetik ulang angka yang
 * sudah hidup di KnowledgeSearch — angka yang ditulis dua kali akan berselisih
 * begitu salah satunya digeser.
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
            ],
            'thresholds' => [
                'min_confidence' => KnowledgeSearch::MIN_CONFIDENCE,
                'hedge_confidence' => KnowledgeSearch::HEDGE_CONFIDENCE,
            ],
            'offsetBottom' => $offsetBottom,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Kapan tiket boleh diarahkan ke sub category "Lainnya" milik sebuah Layanan.
 *
 * Aturannya satu kalimat, tapi HARUS satu sumber. Ia dipakai dua tempat yang
 * mengaku menjawab pertanyaan yang sama:
 *
 *   EvaChat::ticketDraft()               → draf sungguhan yang dilihat karyawan
 *   RecommendationController::test()     → bangku uji yang dilihat admin
 *
 * Ini pelajaran yang sudah pernah dibayar di berkas ini: selama TIE_MARGIN masih
 * private di SubjectMatcher, layar Ticket Recommendation memberi lencana hijau
 * "akan terisi otomatis" pada dua calon yang seri — padahal justru tidak ada
 * yang terisi. Layar uji yang berbeda pendapat dengan perilaku sungguhan lebih
 * buruk daripada tidak ada layar uji sama sekali, karena admin memakainya untuk
 * mengambil keputusan.
 */
final class TicketRouting
{
    public function __construct(private readonly SubjectSearch $subjects) {}

    /**
     * LAYANAN cadangan untuk pertanyaan ini, atau null bila tidak berlaku.
     *
     * Penjaganya sengaja "tidak ada calon SAMA SEKALI", bukan sekadar
     * `terbaik()` yang null. Keduanya berbeda arti: terbaik() juga menyerah saat
     * dua calon SERI, dan di keadaan itu daftar calon justru berisi jawaban yang
     * benar — karyawan tinggal memilih. Melompat ke "Lainnya" di situ berarti
     * membuang subject yang sudah ketemu, lalu mem-broadcast tiketnya ke seluruh
     * PIC padahal satu orang saja cukup.
     *
     * Tanpa penjaga ini "Lainnya" pelan-pelan menjadi keranjang terbesar:
     * tiketnya tidak membawa catalog_subject_id, sehingga tidak pernah muncul di
     * Coverage maupun Ticket Recommendation sebagai permintaan artikel — justru
     * sinyal yang paling dibutuhkan kedua layar itu.
     */
    public function layananCadangan(string $pertanyaan): ?ServiceMatch
    {
        if ($this->subjects->cocokkan($pertanyaan, 1) !== []) {
            return null;
        }

        return $this->subjects->layananTerbaik($pertanyaan);
    }
}

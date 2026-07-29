<?php

namespace App\Services\Knowledge;

use App\Models\Ticket;
use App\Support\Eva\CatalogOptions;
use Illuminate\Support\Collection;

/**
 * Menurunkan daftar tugas menulis dari TIKET NYATA.
 *
 * Rencana lama memakai `kb_answer_logs` untuk memilih subject mana yang ditulis
 * duluan. Itu tidak jalan sekarang: seluruh log berasal dari EVA Preview
 * (admin), dan isinya cuma segelintir pertanyaan unik — terlalu tipis untuk
 * mengurutkan 140 subject. Sinyal yang jauh lebih tebal sudah ada sejak awal di
 * tabel `tickets`, hanya tidak terbaca: kolom `catalog_subject_id` semuanya
 * NULL, sehingga tiket TAMPAK tak punya subject.
 *
 * Padahal `subject_name` + `service_name` terisi. Jadi sebagian besar tiket
 * bisa dipetakan ke katalog secara PERSIS, tanpa menebak sama sekali; sisanya
 * baru dilempar ke Pencarian B (SubjectMatcher) atas judul + deskripsi.
 *
 * Kedua sumber itu DIBEDAKAN di hasilnya ("katalog" vs "tebakan"). Menyatukan
 * keduanya akan membuat tebakan lemah terbaca sama meyakinkannya dengan
 * pemetaan persis — dan daftar tugas menulis yang salah urut lebih buruk
 * daripada tidak ada daftar sama sekali.
 *
 * TIDAK menulis apa pun, termasuk ke `tickets.catalog_subject_id` — mengisi
 * kolom milik tim dari tebakan melanggar aturan #5.
 *
 * Dipakai bersama oleh perintah `eva:mine-ticket-subjects` dan kartu daftar
 * kerja di Coverage Dashboard. Logikanya sengaja hidup di sini, bukan di
 * perintahnya: dua tempat yang memetakan tiket dengan aturan berbeda akan
 * melaporkan jumlah berbeda untuk pertanyaan yang sama.
 */
final class TicketSubjectMiner
{
    /** Contoh judul tiket yang disimpan per subject, sekadar pengingat konteks. */
    private const EXAMPLES_PER_SUBJECT = 2;

    public function __construct(private readonly SubjectSearch $matcher) {}

    /**
     * Hasil penambangan: berapa tiket menunjuk tiap subject katalog.
     *
     * Dihitung ulang tiap dipanggil, tidak disimpan di cache. Pemanggilnya cuma
     * perintah manual yang justru dijalankan SESUDAH admin mengubah sesuatu —
     * jawaban dari cache akan terbaca seperti perubahannya tidak berpengaruh.
     *
     * `catalogEmpty` dibedakan dari "tidak ada tiket". Keduanya sama-sama
     * menghasilkan daftar kosong, tapi sebabnya berbeda jauh: yang satu berarti
     * katalog tim belum terisi, yang lain berarti belum ada tiket masuk.
     * Menyamakan keduanya membuat pesan di layar menyalahkan hal yang keliru.
     *
     * @return array{rows:Collection<int,array<string,mixed>>,tickets:int,unmapped:int,catalogEmpty:bool}
     */
    public function tally(): array
    {
        return $this->compute();
    }

    /** @return array{rows:Collection<int,array<string,mixed>>,tickets:int,unmapped:int,catalogEmpty:bool} */
    private function compute(): array
    {
        $catalog = $this->catalogIndex();

        if ($catalog['byServiceAndName'] === [] && $catalog['byName'] === []) {
            return ['rows' => collect(), 'tickets' => 0, 'unmapped' => 0, 'catalogEmpty' => true];
        }

        $tally = [];
        $unmapped = 0;
        $tickets = 0;

        // Draf sengaja dilewati: draf adalah tiket yang BELUM dikirim — sebagian
        // di antaranya justru lahir dari EVA sendiri (aturan #4), jadi
        // menghitungnya berarti memakai jawaban EVA sebagai bukti permintaan.
        Ticket::query()
            ->where('is_draft', false)
            ->orderBy('id')
            ->chunk(200, function (Collection $chunk) use (&$tally, &$unmapped, &$tickets, $catalog) {
                foreach ($chunk as $ticket) {
                    $tickets++;
                    $resolved = $this->resolve($ticket, $catalog);

                    if ($resolved === null) {
                        $unmapped++;

                        continue;
                    }

                    $this->record($tally, $resolved, $ticket);
                }
            });

        return [
            'rows' => collect($tally)->sortByDesc('total')->values(),
            'tickets' => $tickets,
            'unmapped' => $unmapped,
            'catalogEmpty' => false,
        ];
    }

    /**
     * Dua indeks katalog, KEDUANYA hanya memuat kunci yang menunjuk tepat satu
     * subject.
     *
     * Nama subject bisa kembar di dua tempat sekaligus: "Reset Password" ada di
     * AKUN APLIKASI › SAP dan AKUN APLIKASI › SILO (OTHER APPS) — sama layanan,
     * beda sub category. Indeks yang menimpa kunci kembar akan memilih cabang
     * yang kebetulan terbaca belakangan, dan pekerjaan menulis diarahkan ke
     * cabang yang keliru TANPA satu pun tanda bahwa ada pilihan yang diambil.
     * Kunci kembar karena itu dibuang; tiketnya jatuh ke Pencarian B, yang
     * membaca deskripsi dan memang dirancang membedakan cabang.
     *
     * @return array{byServiceAndName:array<string,array<string,mixed>>,byName:array<string,array<string,mixed>>}
     */
    private function catalogIndex(): array
    {
        $byServiceAndName = [];
        $byName = [];

        foreach (CatalogOptions::all() as $option) {
            $name = $this->normalize($option['subject']);
            $byServiceAndName[$this->normalize($option['service']).'|'.$name][] = $option;
            $byName[$name][] = $option;
        }

        return [
            'byServiceAndName' => $this->onlyUnambiguous($byServiceAndName),
            'byName' => $this->onlyUnambiguous($byName),
        ];
    }

    /**
     * @param  array<string,array<int,array<string,mixed>>>  $index
     * @return array<string,array<string,mixed>>
     */
    private function onlyUnambiguous(array $index): array
    {
        return array_map(
            fn (array $options) => $options[0],
            array_filter($index, fn (array $options) => count($options) === 1),
        );
    }

    /**
     * @param  array{byServiceAndName:array<string,mixed>,byName:array<string,mixed>}  $catalog
     * @return array{option:array<string,mixed>,source:string}|null
     */
    private function resolve(Ticket $ticket, array $catalog): ?array
    {
        $name = $this->normalize((string) $ticket->subject_name);

        if ($name !== '') {
            $exact = $catalog['byServiceAndName'][$this->normalize((string) $ticket->service_name).'|'.$name]
                ?? $catalog['byName'][$name]
                ?? null;

            if ($exact !== null) {
                return ['option' => $exact, 'source' => 'katalog'];
            }
        }

        return $this->guess($ticket);
    }

    /** @return array{option:array<string,mixed>,source:string}|null */
    private function guess(Ticket $ticket): ?array
    {
        $text = trim(($ticket->subject_name ?? $ticket->title ?? '').' '.($ticket->description ?? ''));

        if ($text === '') {
            return null;
        }

        $match = $this->matcher->cocokkan($text, 1)[0] ?? null;

        // SUGGEST_FLOOR, bukan MIN_CONFIDENCE: taruhannya ringan (daftar yang
        // dibaca manusia), dan yang mahal justru menyembunyikan subject yang
        // benar dari daftar tugas menulis.
        if ($match === null || $match->confidence < SubjectSearch::SUGGEST_FLOOR) {
            return null;
        }

        return [
            'option' => [
                'id' => $match->subjectId,
                'subject' => $match->subject,
                'service' => $match->service,
                'subcategory' => $match->subcategory,
                'label' => $match->service.' › '.$match->subcategory.' › '.$match->subject,
            ],
            'source' => 'tebakan',
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $tally
     * @param  array{option:array<string,mixed>,source:string}  $resolved
     */
    private function record(array &$tally, array $resolved, Ticket $ticket): void
    {
        $id = $resolved['option']['id'];

        $tally[$id] ??= [
            'id' => $id,
            'label' => $resolved['option']['label'],
            'total' => 0,
            'katalog' => 0,
            'tebakan' => 0,
            'examples' => [],
        ];

        $tally[$id]['total']++;
        $tally[$id][$resolved['source']]++;

        if (count($tally[$id]['examples']) < self::EXAMPLES_PER_SUBJECT) {
            $tally[$id]['examples'][] = (string) ($ticket->title ?? $ticket->subject_name);
        }
    }

    private function normalize(string $value): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }
}

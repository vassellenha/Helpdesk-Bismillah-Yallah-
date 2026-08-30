<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Hasil satu perangkuman: teksnya, DAN potongan mana saja yang dipakai.
 *
 * Indeksnya ikut dibawa karena tanpa itu jawaban rangkuman tidak bisa
 * dipertanggungjawabkan. EVA menjahit satu jawaban dari beberapa dokumen, tapi
 * yang dulu ditampilkan sebagai rujukan hanya kandidat TERATAS — sehingga
 * karyawan yang mengeklik rujukan untuk memastikan nomor formulir atau batas
 * waktu tidak menemukannya di sana, karena fakta itu datang dari dokumen lain
 * yang tidak pernah disebut. Rujukan yang tidak memuat isi yang dirujuknya
 * merusak kepercayaan lebih dalam daripada tidak ada rujukan sama sekali.
 *
 * Indeks mengacu ke ARRAY POTONGAN yang dioper ke rangkum(), 0-based, dan
 * pemanggil yang bertanggung jawab memetakannya kembali ke sumber aslinya.
 */
final class Synthesis
{
    /** @param list<int> $usedPassages indeks 0-based, boleh kosong */
    public function __construct(
        public readonly string $text,
        public readonly array $usedPassages = [],
    ) {}
}

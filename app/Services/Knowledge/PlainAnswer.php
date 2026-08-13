<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Membersihkan tanda format Markdown dari jawaban model.
 *
 * Model menulis dengan kebiasaan Markdown — `**Lupa Password**`, `# Judul`,
 * backtick untuk istilah teknis — sementara gelembung percakapan EVA
 * menampilkan teks apa adanya. Hasilnya bintang ganda dan pagar muncul mentah
 * di layar karyawan. Sebelum GPT dipasang ini tidak pernah terjadi: teks
 * artikel ditulis tangan tanpa markdown.
 *
 * Ini jaring pengaman, BUKAN pengganti instruksi di prompt. Prompt-nya sudah
 * melarang markdown; kelas ini yang menangani saat model tetap memakainya —
 * dan model memang sesekali begitu, tidak peduli seberapa tegas larangannya.
 *
 * Yang TIDAK dibuang: baris baru dan penanda daftar. Keduanya justru yang
 * membuat prosedur bertahap tetap terbaca sebagai langkah, bukan satu paragraf
 * panjang.
 */
final class PlainAnswer
{
    public static function bersihkan(string $text): string
    {
        $pola = [
            // Penanda daftar disamakan lebih dulu, selagi "*" di awal baris
            // masih bisa dibedakan dari "*" pembungkus miring di bawah.
            '/^[ \t]*[*+][ \t]+/mu' => '- ',

            // Judul: "## Langkah" → "Langkah".
            '/^[ \t]*#{1,6}[ \t]*/mu' => '',

            // Tebal, miring, dan kode. Pembungkusnya wajib menempel ke isinya
            // supaya perkalian biasa ("2 * 3") tidak ikut terpotong.
            '/\*\*(?=\S)(.+?)(?<=\S)\*\*/su' => '$1',
            '/__(?=\S)(.+?)(?<=\S)__/su' => '$1',
            '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/su' => '$1',
            '/`(?=\S)(.+?)(?<=\S)`/su' => '$1',
        ];

        $bersih = preg_replace(array_keys($pola), array_values($pola), $text);

        // Tiga baris kosong berturut-turut atau lebih dirapatkan jadi satu
        // jeda paragraf; sisanya dibiarkan apa adanya.
        $bersih = preg_replace("/\n{3,}/u", "\n\n", (string) $bersih);

        return trim((string) $bersih);
    }
}

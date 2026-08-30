<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Menyusun SATU jawaban dari beberapa potongan Knowledge Base sekaligus.
 *
 * Bedanya dengan AnswerParaphraser: parafrase menulis ulang satu teks yang
 * sudah dipilih; kelas ini membaca beberapa kandidat dan merangkainya. Itu yang
 * membuat EVA bisa menjawab pertanyaan yang jawabannya tersebar — syaratnya di
 * satu artikel, langkahnya di artikel lain, pengecualiannya di FAQ.
 *
 * KONTRAK:
 * - Yang dikembalikan bukan sekadar teks melainkan Synthesis, karena jawaban
 *   yang dijahit dari beberapa dokumen HARUS bisa menyebut dokumen mana saja
 *   yang dipakainya. Lihat Synthesis.
 * - Mengembalikan null berarti "tidak bisa dijawab dari potongan ini". Pemanggil
 *   WAJIB memperlakukannya sebagai belum ketemu, bukan sebagai kegagalan teknis
 *   — termasuk saat mesinnya memang tidak terpasang.
 * - TIDAK BOLEH melempar exception. Gangguan apa pun = null, dan EVA kembali ke
 *   perilaku lamanya (jawab dari satu sumber terbaik, atau tawarkan draf tiket).
 * - Jawabannya HANYA boleh berisi hal yang tertulis di potongan yang dioper.
 *   Ini asisten SOP perusahaan: mengarang prosedur yang terdengar masuk akal
 *   lebih berbahaya daripada mengaku tidak tahu.
 */
interface KnowledgeSynthesizer
{
    /**
     * @param  list<array{title:string,text:string}>  $passages
     */
    public function rangkum(string $question, array $passages): ?Synthesis;
}

<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Menulis ulang jawaban Knowledge Base agar lebih enak dibaca.
 *
 * Seam keempat EVA, sejajar dengan KnowledgeSearch, SubjectSearch, dan
 * PdfTextReader: mesinnya ditukar lewat satu baris bind() di AppServiceProvider,
 * dan EvaResponder tidak pernah tahu model apa yang dipakai — atau apakah ada
 * model sama sekali.
 *
 * Batas wewenangnya sempit dan disengaja: yang masuk ke sini adalah jawaban yang
 * SUDAH ditemukan di KB. Implementasi apa pun hanya boleh mengubah cara
 * kalimatnya dibaca, tidak boleh menambah, menghapus, atau mengoreksi isinya.
 * EVA menjawab prosedur SOP — jawaban yang terdengar lebih ramah tapi
 * langkahnya bergeser jauh lebih berbahaya daripada jawaban yang kaku.
 *
 * KONTRAK: parafrase() TIDAK BOLEH melempar exception dan tidak boleh
 * mengembalikan string kosong. Kegagalan apa pun — jaringan mati, kunci
 * ditolak, model menjawab ngawur — wajib berakhir sebagai teks aslinya. EVA
 * yang menjawab kaku masih menjawab; EVA yang error tidak menjawab sama sekali.
 */
interface AnswerParaphraser
{
    public function parafrase(string $answer): string;
}

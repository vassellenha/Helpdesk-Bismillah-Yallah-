<?php

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\QuestionTokenizer;
use PHPUnit\Framework\TestCase;

/**
 * QuestionTokenizer tidak menyentuh DB maupun facade, jadi diuji sebagai unit
 * murni (extends PHPUnit\Framework\TestCase, tanpa boot aplikasi).
 *
 * Yang dikunci di sini adalah SIFAT yang jadi alasan kelas ini dibuat:
 * pertanyaan dan isi artikel harus dilucuti dengan aturan yang SAMA, supaya
 * "membukanya" dan "dibuka" bertemu di bentuk dasar yang sama.
 */
final class QuestionTokenizerTest extends TestCase
{
    private QuestionTokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenizer = new QuestionTokenizer;
    }

    public function test_tokens_memecah_dan_mengecilkan_huruf(): void
    {
        $this->assertSame(
            ['reset', 'password', 'sap'],
            $this->tokenizer->tokens('Reset, Password SAP!'),
        );
    }

    public function test_tokens_kosong_untuk_null(): void
    {
        $this->assertSame([], $this->tokenizer->tokens(null));
    }

    public function test_significant_membuang_stopword_dan_kata_pendek(): void
    {
        // "saya", "tidak", "bisa" stopword; "di" terlalu pendek (< 3 huruf) —
        // tetapi "sap" (3 huruf) lolos dan wajib dipertahankan.
        $this->assertSame(
            ['reset', 'password', 'sap'],
            $this->tokenizer->significant('saya tidak bisa reset password di sap'),
        );
    }

    public function test_significant_membuang_duplikat(): void
    {
        $this->assertSame(
            ['password'],
            $this->tokenizer->significant('password password password'),
        );
    }

    /**
     * Inti kelas ini: dua bentuk berimbuhan dari kata yang sama harus menyusut
     * ke stem yang sama. Tanpa ini, dokumen "akun dibuka" tidak pernah cocok
     * dengan pertanyaan "cara membukanya".
     */
    public function test_imbuhan_berbeda_menyusut_ke_stem_sama(): void
    {
        $this->assertSame(
            $this->tokenizer->stem('dibuka'),
            $this->tokenizer->stem('membukanya'),
            '"dibuka" dan "membukanya" harus punya stem sama',
        );
    }

    public function test_stem_melucuti_awalan_dan_akhiran(): void
    {
        // membukanya -> (buang "nya") membuka -> (buang "mem") buka
        $this->assertSame('buka', $this->tokenizer->stem('membukanya'));
    }

    public function test_stem_menjaga_kata_pendek_utuh(): void
    {
        // Melucuti "sap" akan menyisakan potongan di bawah panjang minimal,
        // jadi kata aslinya dipertahankan.
        $this->assertSame('sap', $this->tokenizer->stem('sap'));
    }

    public function test_stem_tidak_merusak_password(): void
    {
        // "password" tidak diawali "pe" (huruf keduanya "a"), jadi tidak boleh
        // ikut terlucuti jadi potongan tak berarti.
        $this->assertSame('password', $this->tokenizer->stem('password'));
    }
}

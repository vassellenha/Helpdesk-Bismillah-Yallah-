<?php

declare(strict_types=1);

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\PlainAnswer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gelembung EVA menampilkan teks apa adanya, sementara model menulis dengan
 * kebiasaan Markdown. Tanpa pembersihan ini, karyawan melihat "**Lupa
 * Password**" lengkap dengan bintangnya.
 */
final class PlainAnswerTest extends TestCase
{
    #[DataProvider('contoh')]
    public function test_tanda_format_dibuang(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, PlainAnswer::bersihkan($masukan));
    }

    public static function contoh(): array
    {
        return [
            'tebal' => ['Gunakan **Lupa Password** di Portal SSO.', 'Gunakan Lupa Password di Portal SSO.'],
            'tebal garis bawah' => ['Berlaku __15 menit__ saja.', 'Berlaku 15 menit saja.'],
            'miring' => ['Buka menu *Akun Saya*.', 'Buka menu Akun Saya.'],
            'kode' => ['Jalankan `reset-password` di portal.', 'Jalankan reset-password di portal.'],
            'judul' => ["## Langkah Reset\nBuka portal.", "Langkah Reset\nBuka portal."],
            'daftar bintang' => ["* Buka portal\n* Pilih akun", "- Buka portal\n- Pilih akun"],
            'campuran' => ['**Portal SSO** → menu `Akun Saya`', 'Portal SSO → menu Akun Saya'],
        ];
    }

    /**
     * Yang dipertahankan sama pentingnya dengan yang dibuang: prosedur bertahap
     * hanya terbaca kalau langkahnya tetap terpisah baris.
     */
    public function test_baris_baru_dan_penomoran_dipertahankan(): void
    {
        $langkah = "1. Buka Portal SSO\n2. Pilih Akun Saya\n3. Klik Ubah Password";

        $this->assertSame($langkah, PlainAnswer::bersihkan($langkah));
    }

    public function test_perkalian_biasa_tidak_ikut_terpotong(): void
    {
        $this->assertSame('Kapasitas 2 * 3 slot.', PlainAnswer::bersihkan('Kapasitas 2 * 3 slot.'));
    }

    public function test_teks_polos_tidak_berubah(): void
    {
        $teks = 'Buka Portal SSO, pilih Akun Saya, lalu klik Ubah Password SAP.';

        $this->assertSame($teks, PlainAnswer::bersihkan($teks));
    }
}

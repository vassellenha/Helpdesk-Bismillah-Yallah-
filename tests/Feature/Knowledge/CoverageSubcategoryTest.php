<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\CoverageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kesiapan per sub category dikirim UTUH.
 *
 * Sebelumnya daftar ini dipotong di 10 teratas oleh server. Karena urutannya
 * MENURUN dari kesiapan tertinggi, yang terpotong justru sub category dengan
 * kesiapan terburuk — persis yang jadi alasan layar ini dibuka. Dan tidak ada
 * satu pun jalan di seluruh konsol untuk mencapai sisanya.
 *
 * Pemenggalan sekarang urusan layar (pagination), yang tiap halamannya bisa
 * dibuka. Tes ini menjaga agar cap itu tidak diam-diam kembali.
 */
final class CoverageSubcategoryTest extends TestCase
{
    use RefreshDatabase;

    /** Lebih banyak dari cap lama (10), supaya potongannya ketahuan. */
    private const SUBCATEGORY_COUNT = 14;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function seedCatalog(int $subcategories): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);

        foreach (range(1, $subcategories) as $i) {
            DB::table('service_catalog_subcategories')->insert([
                'id' => $i, 'service_id' => 1, 'name' => 'SUB '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);

            DB::table('service_catalog_subjects')->insert([
                'id' => $i, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => $i,
                'name' => 'Masalah '.$i, 'requires_approval' => false,
                'support_level' => 1, 'is_active' => true,
            ]);
        }
    }

    public function test_seluruh_sub_category_dikirim_tanpa_dipotong(): void
    {
        $this->seedCatalog(self::SUBCATEGORY_COUNT);

        $result = app(CoverageCalculator::class)->bySubcategory();

        $this->assertCount(self::SUBCATEGORY_COUNT, $result['rows'], 'daftar tidak boleh dipotong di server');
        $this->assertSame(self::SUBCATEGORY_COUNT, $result['total']);
        $this->assertSame(self::SUBCATEGORY_COUNT, $result['shown'], '"shown" harus jujur soal berapa yang benar-benar dikirim');
    }

    /** Layar bisa memenggal sendiri hanya kalau bahannya utuh — termasuk saat kosong. */
    public function test_katalog_kosong_menghasilkan_daftar_kosong_bukan_error(): void
    {
        $result = app(CoverageCalculator::class)->bySubcategory();

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['total']);
    }
}

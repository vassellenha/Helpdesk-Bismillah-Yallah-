<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\ConfidenceScorer;
use App\Services\Knowledge\QuestionTokenizer;
use App\Services\Knowledge\SynonymExpander;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * ConfidenceScorer sendiri tidak menyentuh DB, tetapi bergantung pada
 * SynonymExpander yang membaca peta sinonim lewat Cache. Alih-alih menyiapkan
 * DB (migrasi kb_articles memakai FULLTEXT yang tidak ada di SQLite tes), peta
 * itu DISUNTIK langsung ke cache array. Dengan begitu penilai teruji tanpa satu
 * query pun — dan tetap memakai SynonymExpander sungguhan, bukan tiruan.
 *
 * Kunci: cache key harus sama dengan milik SynonymExpander. Kalau kelak
 * berganti, tes ini GAGAL KERAS (map() jatuh ke DB yang tidak ada), bukan diam
 * memberi hasil salah — itu justru yang diinginkan.
 */
final class ConfidenceScorerTest extends TestCase
{
    private const SYNONYM_CACHE_KEY = 'eva.synonym-map';

    private ConfidenceScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSynonyms([]);
        $tokenizer = new QuestionTokenizer;
        $this->scorer = new ConfidenceScorer($tokenizer, new SynonymExpander($tokenizer));
    }

    /** @param array<string,string[]> $map */
    private function seedSynonyms(array $map): void
    {
        Cache::put(self::SYNONYM_CACHE_KEY, $map, 600);
    }

    public function test_pertanyaan_kosong_menghasilkan_nol(): void
    {
        $this->assertSame(0, $this->scorer->score([], 'judul apa saja', 'isi apa saja'));
    }

    public function test_tanpa_kecocokan_di_isi_menghasilkan_nol(): void
    {
        $this->assertSame(0, $this->scorer->score(['xyz'], 'judul lain', 'isi lain'));
    }

    /**
     * REGRESI bug yang kita temukan lewat coba-coba: artikel yang hanya MENYEBUT
     * "reset password SAP" sebagai rujukan silang tidak boleh mengalahkan
     * artikel yang benar-benar BERJUDUL itu. Judul memikul bobot lebih besar.
     */
    public function test_kecocokan_judul_mengalahkan_kecocokan_isi_saja(): void
    {
        $q = ['reset', 'password', 'sap'];

        $benar = $this->scorer->score(
            $q,
            'SOP Reset Password SAP',
            'panduan mengganti password sap yang kedaluwarsa',
        );

        $rujukanSilang = $this->scorer->score(
            $q,
            'SOP Unlock Akun SAP',
            'lihat juga SOP Reset Password SAP untuk kasus lupa password',
        );

        $this->assertGreaterThan(
            $rujukanSilang,
            $benar,
            'artikel berjudul tepat harus unggul atas yang hanya menyebutnya',
        );
        $this->assertSame(97, $benar, 'kecocokan judul+isi penuh (3 kata) menyentuh batas atas');
        $this->assertSame(68, $rujukanSilang, 'hanya isi yang cocok penuh, judul cuma "sap"');
    }

    public function test_batas_atas_tidak_pernah_melewati_97(): void
    {
        // Dua kata, cocok penuh di judul dan isi → 100 mentah, dipangkas 97.
        $skor = $this->scorer->score(['reset', 'password'], 'reset password', 'reset password');

        $this->assertSame(97, $skor);
    }

    public function test_pertanyaan_satu_kata_diredam(): void
    {
        // Satu kata cocok sempurna = 100 mentah, diredam 0.75 → 75. Kontras
        // dengan dua-kata sempurna (97) membuktikan peredam bekerja.
        $skor = $this->scorer->score(['forticlient'], 'panduan forticlient', 'instal forticlient');

        $this->assertSame(75, $skor);
    }

    /**
     * Sinonim dihitung PENUH, bukan setengah. "sandi" yang bertemu "password"
     * adalah kata yang sama dalam kosakata berbeda.
     */
    public function test_kecocokan_sinonim_dihitung_penuh(): void
    {
        $this->seedSynonyms([
            'sandi' => ['sandi', 'password'],
            'password' => ['password', 'sandi'],
        ]);

        $skor = $this->scorer->score(['sandi'], 'reset password sap', 'ganti password sap');

        // Satu kata cocok penuh lewat sinonim → 100 mentah, diredam → 75.
        // Kalau sinonim didiskon, angkanya akan jatuh di bawah ini.
        $this->assertSame(75, $skor);
    }
}

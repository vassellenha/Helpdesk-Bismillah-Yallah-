<?php

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\DocumentTextExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Membaca isi berkas yang diunggah, TANPA dependensi composer baru.
 *
 * Yang bisa dibaca sekarang:
 *  - TXT/MD — teks apa adanya.
 *  - DOCX   — sebenarnya arsip ZIP berisi word/document.xml. ZipArchive dan
 *             SimpleXML sudah ada di PHP, jadi ini bukan akal-akalan melainkan
 *             format aslinya memang begitu.
 *
 * Yang BELUM: PDF dan XLSX mengembalikan null — bukan string kosong. Bedanya
 * penting: null berarti "belum bisa dibaca, minta ditempel", sedangkan string
 * kosong akan lolos ke indexer dan melahirkan artikel tanpa isi yang terlihat
 * berhasil. PDF butuh smalot/pdfparser (izin pemilik), dan PDF hasil PINDAI
 * tetap butuh OCR yang sama sekali lain.
 */
final class DocumentTextExtractorTest extends TestCase
{
    private DocumentTextExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DocumentTextExtractor;
    }

    /**
     * Berkas dikirim sebagai PATH, bukan UploadedFile: ekstraktor membaca
     * berkas yang SUDAH TERSIMPAN di disk privat, karena pembacaannya kini
     * dikerjakan IndexDocument di antrean — saat itu unggahan sementara sudah
     * lama dihapus.
     */
    private function upload(string $contents, string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kbdoc');
        file_put_contents($path, $contents);

        return $path;
    }

    /** Membangun DOCX minimal yang sah: ZIP berisi word/document.xml. */
    private function docx(string $bodyXml, bool $includeDocumentXml = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kbdocx');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');

        if ($includeDocumentXml) {
            $zip->addFromString('word/document.xml',
                '<?xml version="1.0"?><w:document xmlns:w="http://x"><w:body>'.$bodyXml.'</w:body></w:document>');
        }

        $zip->close();

        return $path;
    }

    private function paragraph(string $text): string
    {
        return '<w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p>';
    }

    public function test_txt_dibaca_apa_adanya(): void
    {
        $path = $this->upload("Baris pertama\nBaris kedua", 'sop.txt');

        $this->assertSame("Baris pertama\nBaris kedua", $this->extractor->extract($path, 'TXT'));
    }

    public function test_md_dibaca_apa_adanya(): void
    {
        $path = $this->upload('# Judul', 'sop.md');

        $this->assertSame('# Judul', $this->extractor->extract($path, 'MD'));
    }

    public function test_docx_diekstrak_jadi_teks(): void
    {
        $path = $this->docx($this->paragraph('Akun SAP terkunci setelah lima kali gagal login.'));

        $this->assertSame(
            'Akun SAP terkunci setelah lima kali gagal login.',
            $this->extractor->extract($path, 'DOCX'),
        );
    }

    /** Tiap paragraf jadi baris sendiri — kalau tidak, seluruh SOP jadi satu paragraf raksasa. */
    public function test_paragraf_docx_jadi_baris_terpisah(): void
    {
        $path = $this->docx($this->paragraph('Langkah 1').$this->paragraph('Langkah 2'));

        $this->assertSame("Langkah 1\nLangkah 2", $this->extractor->extract($path, 'DOCX'));
    }

    /** Word memecah satu kalimat jadi beberapa <w:t> saat ada perubahan format. */
    public function test_potongan_teks_dalam_satu_paragraf_disambung(): void
    {
        $path = $this->docx('<w:p><w:r><w:t>Password </w:t></w:r><w:r><w:t>kedaluwarsa</w:t></w:r></w:p>');

        $this->assertSame('Password kedaluwarsa', $this->extractor->extract($path, 'DOCX'));
    }

    public function test_entitas_xml_dikembalikan_ke_karakter_asli(): void
    {
        $path = $this->docx($this->paragraph('Klik &quot;Login&quot; &amp; tunggu'));

        $this->assertSame('Klik "Login" & tunggu', $this->extractor->extract($path, 'DOCX'));
    }

    /**
     * PDF & XLSX mengembalikan null, BUKAN string kosong — supaya pemanggil
     * bisa membedakan "belum bisa dibaca" dari "berkasnya memang kosong".
     */
    public function test_pdf_belum_bisa_dibaca(): void
    {
        $this->assertNull($this->extractor->extract($this->upload('%PDF-1.4', 'sop.pdf'), 'PDF'));
        $this->assertFalse($this->extractor->canRead('PDF'));
    }

    public function test_xlsx_belum_bisa_dibaca(): void
    {
        $this->assertNull($this->extractor->extract($this->upload('PK', 'data.xlsx'), 'XLSX'));
    }

    public function test_format_yang_bisa_dibaca_diumumkan(): void
    {
        $this->assertTrue($this->extractor->canRead('TXT'));
        $this->assertTrue($this->extractor->canRead('docx'), 'huruf kecil harus tetap dikenali');
        $this->assertFalse($this->extractor->canRead('XLSX'));
    }

    /** Berkas rusak tidak boleh meledak — ia cuma "tidak terbaca". */
    public function test_docx_yang_bukan_zip_tidak_meledak(): void
    {
        $path = $this->upload('ini jelas bukan zip', 'rusak.docx');

        $this->assertNull($this->extractor->extract($path, 'DOCX'));
    }

    public function test_docx_tanpa_document_xml_tidak_terbaca(): void
    {
        $path = $this->docx('', includeDocumentXml: false);

        $this->assertNull($this->extractor->extract($path, 'DOCX'));
    }

    /** DOCX sah tapi isinya kosong tetap null — tak ada gunanya jadi artikel. */
    public function test_docx_kosong_dianggap_tidak_terbaca(): void
    {
        $path = $this->docx($this->paragraph('   '));

        $this->assertNull($this->extractor->extract($path, 'DOCX'));
    }
}

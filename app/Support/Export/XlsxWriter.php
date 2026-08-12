<?php

namespace App\Support\Export;

use RuntimeException;
use ZipArchive;

/**
 * Menulis satu sheet .xlsx asli tanpa pustaka pihak ketiga.
 *
 * SEBELUMNYA tombol "Excel" di Reporting Team Lead mengunduh CSV. Excel memang
 * bisa membukanya, tapi tanpa BOM huruf beraksen jadi rusak, dan di Windows
 * dengan pemisah daftar titik-koma seluruh baris masuk ke satu kolom. Yang
 * diunduh pun berekstensi .csv, jadi klaim tombolnya tidak pernah benar.
 *
 * SEKARANG isinya benar-benar workbook OOXML: satu ZIP berisi part minimum yang
 * dituntut spesifikasi (content types, relationship, workbook, worksheet,
 * styles). Nilai teks ditulis sebagai inline string sehingga tidak perlu tabel
 * sharedStrings, dan angka murni ditulis sebagai angka supaya bisa langsung
 * dijumlahkan di Excel.
 */
class XlsxWriter
{
    /** Indeks cellXfs di styles.xml — 0 normal, 1 tebal (baris header). */
    private const STYLE_DEFAULT = 0;

    private const STYLE_HEADER = 1;

    /** Batas panjang nama sheet yang diterima Excel. */
    private const SHEET_NAME_MAX = 31;

    /**
     * Mengembalikan isi berkas .xlsx sebagai string biner.
     *
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string|int|float|null>>  $rows
     */
    public static function sheet(string $sheetName, array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat berkas sementara untuk ekspor Excel.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Tidak bisa menulis berkas Excel.');
        }

        foreach (self::parts($sheetName, $headers, $rows) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $binary = file_get_contents($path);
        @unlink($path);

        if ($binary === false) {
            throw new RuntimeException('Berkas Excel gagal dibaca kembali setelah ditulis.');
        }

        return $binary;
    }

    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string|int|float|null>>  $rows
     * @return array<string,string>
     */
    private static function parts(string $sheetName, array $headers, array $rows): array
    {
        return [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels' => self::rootRels(),
            'xl/workbook.xml' => self::workbook($sheetName),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => self::worksheet($headers, $rows),
        ];
    }

    private static function contentTypes(): string
    {
        return self::xmlHeader().
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
            '</Types>';
    }

    private static function rootRels(): string
    {
        return self::xmlHeader().
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return self::xmlHeader().
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="'.self::escape(self::safeSheetName($sheetName)).'" sheetId="1" r:id="rId1"/></sheets>'.
            '</workbook>';
    }

    private static function workbookRels(): string
    {
        return self::xmlHeader().
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'.
            '</Relationships>';
    }

    private static function styles(): string
    {
        return self::xmlHeader().
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'.
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'.
            '<borders count="1"><border/></borders>'.
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'.
            '<cellXfs count="2">'.
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'.
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'.
            '</cellXfs>'.
            '</styleSheet>';
    }

    /**
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string|int|float|null>>  $rows
     */
    private static function worksheet(array $headers, array $rows): string
    {
        $xml = self::xmlHeader().
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $xml .= self::row(1, array_values($headers), self::STYLE_HEADER);

        foreach (array_values($rows) as $index => $cells) {
            $xml .= self::row($index + 2, array_values($cells), self::STYLE_DEFAULT);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /** @param array<int,string|int|float|null> $cells */
    private static function row(int $number, array $cells, int $style): string
    {
        $xml = '<row r="'.$number.'">';

        foreach ($cells as $index => $value) {
            $xml .= self::cell(self::columnName($index).$number, $value, $style);
        }

        return $xml.'</row>';
    }

    private static function cell(string $reference, string|int|float|null $value, int $style): string
    {
        $text = (string) ($value ?? '');
        $attributes = ' r="'.$reference.'"'.($style === self::STYLE_DEFAULT ? '' : ' s="'.$style.'"');

        if (self::isNumeric($text)) {
            return '<c'.$attributes.'><v>'.$text.'</v></c>';
        }

        return '<c'.$attributes.' t="inlineStr"><is><t xml:space="preserve">'.self::escape($text).'</t></is></c>';
    }

    /**
     * Hanya angka polos yang boleh jadi sel numerik. "0812…" tetap teks supaya
     * nol depannya tidak hilang, dan "80%" tetap teks supaya tidak diam-diam
     * berubah arti.
     */
    private static function isNumeric(string $text): bool
    {
        return $text !== ''
            && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $text) === 1;
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function columnName(int $index): string
    {
        $name = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    /** Excel menolak nama sheet yang panjang atau memuat : \ / ? * [ ]. */
    private static function safeSheetName(string $name): string
    {
        $clean = trim(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $name));

        return $clean === '' ? 'Sheet1' : mb_substr($clean, 0, self::SHEET_NAME_MAX);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function xmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    }
}

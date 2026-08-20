<?php

namespace App\Support;

/**
 * Short display code for a department/division name — "DEPT-INFRA2" instead
 * of the raw dept_id ("07") the real company API sends, which means nothing
 * to a human reading the user's profile.
 *
 * Display-only. Never write this back to kode_departemen/kode_divisi — those
 * columns still hold whatever raw code the API/CSV import gave them (or
 * null), because the Edit tab's plain text input reads the very same value
 * this class formats for the read-only Detail tab. Overwrite that column
 * with a formatted string here and an untouched "Save" on the Edit tab would
 * bake "DEPT-INFRA2" into the database as if it were the real code.
 *
 * The curated map isn't guesswork for every entry: APG, APP, APB, ACP, and
 * INFRA1/INFRA2 are the literal segments ADHI already uses in real NPP
 * numbers (e.g. "E/APG/6050/04", "E/SPL INFRA2-JB01/93/13" — see the
 * imported employee export), not invented codes. Everything else in the map
 * follows the same convention for names that didn't happen to appear in a
 * sampled NPP. A name outside the map — a department onboarded after this
 * was written — falls back to a generic short-code deriver so it still gets
 * something instead of a blank.
 */
class OrgAbbreviation
{
    private const KNOWN = [
        'Departemen Infrastruktur I' => 'INFRA1',
        'Departemen Infrastruktur II' => 'INFRA2',
        'Departemen Gedung' => 'GEDUNG',
        'Departemen Energi & Industrial' => 'ENERGI',
        'Departemen Perkeretaapian' => 'KAI',
        'Dept. Pengendali Operasi' => 'PENGOP',
        'Dept. Teknologi Informasi' => 'TI',
        'Kantor Pusat' => 'KP',
        'PT ADHI PERSADA GEDUNG' => 'APG',
        'PT ADHI PERSADA PROPERTI' => 'APP',
        'PT ADHI PERSADA BETON' => 'APB',
        'PT ADHI COMMUTER PROPERTI' => 'ACP',
        'PT JASAMARGA JOGJA SOLO' => 'JMJS',
        'PT JASAMARGA JOGJA BAWEN' => 'JMJB',
        'PT JALINTIM ADHI ABIPRAYA' => 'JAA',
        'PT BOGOR SERPONG INFRA SELARAS' => 'BSIS',
        'PT DUMAI TIRTA PERSADA' => 'DTP',
        'PT ADHI JALINTIM RIAU' => 'AJR',
        'PT KARIAN WATER SERVICE' => 'KWS',
        'PT KARYA LOGISTIK NUSANTARA' => 'KLN',
        'Divisi Operasi II' => 'OPS2',
    ];

    private const STRIP_PREFIXES = ['departemen', 'dept.', 'dept', 'divisi', 'div.', 'div', 'pt', 'kantor'];

    private const ROMAN_SUFFIX = ['III' => '3', 'II' => '2', 'I' => '1'];

    /** ":prefix-:code" (e.g. "DEPT-INFRA2"), or null when there's no name to work from. */
    public static function withPrefix(?string $name, string $prefix): ?string
    {
        $code = self::code($name);

        return $code === null ? null : "{$prefix}-{$code}";
    }

    public static function code(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return self::KNOWN[$name] ?? self::derive($name);
    }

    /**
     * Strips a leading category word, folds a trailing roman numeral into a
     * digit suffix on the previous word ("Infrastruktur II" -> "INFRA2"), and
     * otherwise takes initials for a multi-word name or a short truncation
     * for a single word. Approximate by nature — only ever runs for a name
     * outside KNOWN.
     */
    private static function derive(string $name): string
    {
        $words = preg_split('/\s+/', $name) ?: [];

        if ($words !== [] && in_array(mb_strtolower(rtrim($words[0], '.')), self::STRIP_PREFIXES, true)) {
            array_shift($words);
        }

        if ($words === []) {
            return mb_strtoupper(mb_substr($name, 0, 5));
        }

        $lastWord = end($words);
        $romanDigit = self::ROMAN_SUFFIX[mb_strtoupper($lastWord)] ?? null;

        if ($romanDigit !== null && count($words) > 1) {
            array_pop($words);

            return mb_strtoupper(mb_substr(end($words), 0, 5)).$romanDigit;
        }

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($lastWord, 0, 6));
        }

        // Multi-word, no numeral — initials read better than truncating the
        // last word alone for a name like "Energi & Industrial".
        $initials = implode('', array_map(
            fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)),
            array_filter($words, fn (string $w) => $w !== '&'),
        ));

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($lastWord, 0, 5));
    }
}

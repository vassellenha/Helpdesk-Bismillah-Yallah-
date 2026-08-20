<?php

namespace App\Support\EmployeeDirectory;

/**
 * Reads back the CSV produced by User & Role Management's own "Export"
 * button (see UserRoleController::export()) so an Admin can push edits made
 * outside the app — a spreadsheet reconciliation, a snapshot pulled from
 * another environment — through the exact same pipeline as the live API:
 * mapping, matching by NIP, admin-override protection, and the audit trail.
 *
 * Narrower than the real API on purpose:
 *  - the export has no dept_id/division_id/proy_unit_id, only the resolved
 *    "Unit Kerja" name, so those coded columns are simply never in the
 *    returned rows — field_map finds no key for them and leaves
 *    kode_departemen/kode_divisi/kode_proyek untouched, exactly like a
 *    payload that omits a field would.
 *  - the export's Role column is never read here. employees:sync's own rule
 *    — role assignment stays an Admin decision, no data feed can grant one —
 *    applies just as much to a CSV an Admin drags in as it does to the live
 *    API.
 */
class CsvEmployeeDirectory implements EmployeeDirectory
{
    private const STATUS_TO_API_CODE = [
        'aktif' => 'Y',
        'nonaktif' => 'N',
    ];

    public function __construct(private string $csvPath) {}

    /** @return array<int,array<string,mixed>> */
    public function fetch(): array
    {
        if (! is_file($this->csvPath)) {
            return [];
        }

        $handle = fopen($this->csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        // The export writes a UTF-8 BOM so Excel opens it without mangling
        // accented characters — skip it, or the "Nama" header never matches.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }
        $header = array_map('trim', $header);

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < count($header)) {
                continue;
            }
            $r = array_combine($header, array_slice($line, 0, count($header)));

            // No NPP means no reliable key to match this row against an
            // existing account — nothing safe to do with it.
            $npp = trim((string) ($r['NPP'] ?? ''));
            if ($npp === '' || $npp === '-') {
                continue;
            }

            $rows[] = [
                'name' => trim((string) ($r['Nama'] ?? '')),
                'npp' => $npp,
                'email' => self::blankDash($r['Email'] ?? ''),
                'phone_number' => self::blankDash($r['Telepon'] ?? ''),
                'job_position' => self::blankDash($r['Jabatan'] ?? ''),
                'dept_name' => trim((string) ($r['Unit Kerja'] ?? '')),
                'active' => self::STATUS_TO_API_CODE[strtolower(trim((string) ($r['Status'] ?? '')))] ?? '',
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** The export writes "-" for an empty cell; the sync's own blank rule expects "". */
    private static function blankDash(string $value): string
    {
        $value = trim($value);

        return $value === '-' ? '' : $value;
    }
}

<?php

namespace App\Support\EmployeeDirectory;

use Illuminate\Support\Facades\Log;

/**
 * Offline stand-in for the ADHI employee API: reads a JSON fixture shaped like
 * the payload we expect. Lets the whole sync — mapping, matching, create,
 * update, audit — run and be tested before any credential exists.
 *
 * Edit database/fixtures/employees.json to rehearse a scenario (a new hire, a
 * resignation, a changed phone number) without touching the database directly.
 */
class MockEmployeeDirectory implements EmployeeDirectory
{
    public function __construct(private string $fixturePath) {}

    public function fetch(): array
    {
        if (! is_file($this->fixturePath)) {
            Log::warning('[EmployeeDirectory:mock] Fixture tidak ditemukan.', ['path' => $this->fixturePath]);

            return [];
        }

        $rows = json_decode((string) file_get_contents($this->fixturePath), true);

        if (! is_array($rows)) {
            Log::error('[EmployeeDirectory:mock] Fixture bukan JSON array yang valid.', ['path' => $this->fixturePath]);

            return [];
        }

        return $rows;
    }
}

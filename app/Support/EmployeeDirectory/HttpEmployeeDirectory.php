<?php

namespace App\Support\EmployeeDirectory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The real ADHI employee API: one bearer-authenticated GET returning the
 * employee list. Provide EMPLOYEE_DIRECTORY_URL and EMPLOYEE_DIRECTORY_TOKEN
 * in .env and flip the driver to "http" to activate.
 *
 * Pagination is deliberately not implemented — the endpoint contract is not
 * known yet. If the real API paginates, that loop belongs here and nowhere else.
 */
class HttpEmployeeDirectory implements EmployeeDirectory
{
    public function __construct(
        private ?string $baseUrl,
        private ?string $token,
        private string $endpoint = '/api/v1/employees',
        private int $timeout = 30,
        private string $collectionKey = 'data',
    ) {}

    public function fetch(): array
    {
        if (empty($this->baseUrl)) {
            Log::warning('[EmployeeDirectory:http] EMPLOYEE_DIRECTORY_URL kosong — sync dilewati.');

            return [];
        }

        $url = rtrim($this->baseUrl, '/').'/'.ltrim($this->endpoint, '/');

        try {
            $request = Http::timeout($this->timeout)->acceptJson();

            if (! empty($this->token)) {
                $request = $request->withToken($this->token);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                Log::error('[EmployeeDirectory:http] Endpoint menolak permintaan.', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $rows = $this->collectionKey === ''
                ? $response->json()
                : $response->json($this->collectionKey);

            if (! is_array($rows)) {
                Log::error('[EmployeeDirectory:http] Bentuk respons tidak dikenali.', [
                    'url' => $url,
                    'collection_key' => $this->collectionKey,
                ]);

                return [];
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::error('[EmployeeDirectory:http] Exception saat mengambil data pegawai.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

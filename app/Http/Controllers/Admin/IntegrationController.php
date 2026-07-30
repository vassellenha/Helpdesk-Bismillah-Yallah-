<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Support\DummyData;
use App\Support\EmployeeSync;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Admin → Integrasi. The control surface for the company employee API: what is
 * configured, whether it answers, what the last runs did, and a button to run
 * one now.
 *
 * Deliberately shows no credentials. The URL and token live in .env (see
 * config/integrations.php) because they differ per environment and must not end
 * up in a database that gets dumped, restored, and copied between machines. This
 * page reports only *whether* a token is present — never its value.
 */
class IntegrationController extends Controller
{
    public function index(): View
    {
        return view('admin.integrations', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'integration' => $this->configPayload(),
            'history' => $this->history(),
            'syncUrl' => route('admin.integrations.sync'),
            'testUrl' => route('admin.integrations.test'),
        ]);
    }

    /**
     * Connection test = a dry run. It exercises the real driver end to end and
     * reports what a live sync would change, without writing anything — far more
     * informative than a bare "reachable: yes".
     */
    public function test(): JsonResponse
    {
        $summary = EmployeeSync::run(dryRun: true);

        return response()->json([
            'ok' => $summary['fetched'] > 0,
            'summary' => $summary,
            'message' => $summary['fetched'] > 0
                ? "Sumber menjawab: {$summary['fetched']} data pegawai diterima."
                : 'Sumber tidak mengembalikan data. Cek URL, token, dan log aplikasi.',
        ], $summary['fetched'] > 0 ? 200 : 422);
    }

    public function sync(): JsonResponse
    {
        $summary = EmployeeSync::run();

        if ($summary['fetched'] === 0) {
            return response()->json([
                'message' => 'Tidak ada data pegawai yang diterima dari sumber. Cek konfigurasi integrasi atau log aplikasi.',
            ], 422);
        }

        return response()->json([
            'summary' => $summary,
            'history' => $this->history(),
        ]);
    }

    /** @return array<string,mixed> */
    private function configPayload(): array
    {
        $config = config('integrations.employee_directory');
        $driver = $config['driver'] ?? 'mock';

        return [
            'driver' => $driver,
            'driverLabel' => $driver === 'http' ? 'API perusahaan (live)' : 'Mock — fixture lokal',
            'isLive' => $driver === 'http',
            'baseUrl' => $config['http']['base_url'] ?: null,
            'endpoint' => $config['http']['endpoint'] ?? null,
            'timeout' => $config['http']['timeout'] ?? null,
            'collectionKey' => $config['http']['collection_key'] ?? null,
            // Never the token itself — only whether one is configured.
            'tokenSet' => ! blank($config['http']['token'] ?? null),
            'fixture' => $driver === 'mock' ? ($config['mock']['fixture'] ?? null) : null,
            'matchBy' => $config['match_by'] ?? 'nip',
            'deactivateMissing' => (bool) ($config['deactivate_missing'] ?? false),
            'overwriteWithEmpty' => (bool) ($config['overwrite_with_empty'] ?? false),
            'fieldMap' => $config['field_map'] ?? [],
            'statusMap' => $config['status_map'] ?? [],
            'defaultRole' => $config['default_role'] ?? 'Requester',
        ];
    }

    /**
     * Past runs, read back from the shared audit trail rather than a table of
     * its own — EmployeeSync already records every run there, and a second store
     * would be one more thing that can disagree with it.
     *
     * @return array<int,array<string,mixed>>
     */
    private function history(int $limit = 15): array
    {
        return AuditTrail::with('actor')
            ->where('module', 'integration')
            ->where('action', 'sync')
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(fn (AuditTrail $a) => [
                'id' => $a->id,
                'at' => $a->created_at->format('d M Y · H:i'),
                'by' => $a->actor?->name ?? '—',
                'description' => $a->description,
                'summary' => $a->new_value,
                'failed' => str_contains($a->description, 'GAGAL'),
            ])
            ->values()
            ->all();
    }
}

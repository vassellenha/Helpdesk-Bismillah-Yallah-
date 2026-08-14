<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan 21 kategori "Layanan — Subcategory" yang ditandai kuning oleh
 * Admin di file spreadsheet referensi (Conceptual Helpdesk 2.0.xlsx, sheet
 * "Ticket") ke Service Catalog.
 *
 * MURNI TAMBAHAN, sengaja: `firstOrCreate` di setiap level berarti Layanan
 * yang sudah ada (CCM, CRM, DHIERA, SAP) tidak pernah dibuat ulang atau
 * disentuh — hanya baris yang belum ada yang ditambahkan.
 *
 * PIC (support_agent_id/it_agent_id) SENGAJA dibiarkan null di sini — Admin
 * yang akan mengisinya sendiri lewat Service Catalog nanti. Karena itu ini
 * ditulis langsung lewat Eloquent, bukan lewat ServiceCatalogController::
 * store(), yang mewajibkan PIC terisi sebelum sebuah Subject bisa disimpan
 * (lihat assertPicAssigned()) — lewat command ini tidak akan pernah kena
 * validasi itu.
 *
 * Idempotent: aman dijalankan berkali-kali, `firstOrCreate` tidak pernah
 * menduplikasi baris yang sudah ada.
 */
class SeedYellowCatalog extends Command
{
    protected $signature = 'support:seed-yellow-catalog';

    protected $description = 'Menambahkan kategori Layanan/Subcategory yang ditandai kuning di spreadsheet referensi';

    /**
     * Layanan → daftar nama Subcategory baru di bawahnya.
     *
     * @var array<string,array<int,string>>
     */
    private const MAP = [
        'CCM' => ['Kendala Aplikasi', 'Permintaan Akses'],
        'DHIERA' => [
            'Finance Akunting', 'Finance Keuangan', 'Finance Pajak', 'Marketing',
            'Planning', 'Procurement', 'Production', 'SDM',
        ],
        'SAP' => ['User ID & Hak Akses'],
        'Lisensi' => ['BIM', 'BIM Allplan', 'BIM Autodesk', 'BIM Cubicost', 'BIM Cutting Ops', 'Adobe/Nitro'],
        'QHSE' => ['Adhi Manpower', 'SIQHA'],
        'SDM' => ['Peminjaman Ruangan ALC'],
    ];

    public function handle(): int
    {
        $issueCategory = IssueCategory::where('name', 'Service Request')->firstOrFail();
        $actor = $this->auditActor();
        $added = [];

        DB::transaction(function () use ($issueCategory, $actor, &$added) {
            foreach (self::MAP as $serviceName => $subcategoryNames) {
                $service = ServiceCatalogService::firstOrCreate(['name' => $serviceName]);

                foreach ($subcategoryNames as $subcategoryName) {
                    $subcategory = ServiceCatalogSubcategory::firstOrCreate([
                        'service_id' => $service->id,
                        'name' => $subcategoryName,
                    ]);

                    // Nama Subject sama dengan Subcategory — spreadsheet
                    // sumbernya tidak punya level ketiga, dan tanpa satu
                    // Subject di sini, dropdown Subject di form tiket akan
                    // kosong begitu Subcategory ini dipilih (dead end).
                    [$subject, $created] = $this->firstOrCreateSubject(
                        $issueCategory->id, $service->id, $subcategory->id, $subcategoryName
                    );

                    if ($created) {
                        $added[] = "{$serviceName} — {$subcategoryName}";
                    }
                }
            }

            if ($added !== [] && $actor) {
                AuditTrail::record($actor, [
                    'module' => 'service_catalog',
                    'action' => 'create',
                    'target_type' => 'subject',
                    'target_name' => 'Kategori kuning (spreadsheet referensi)',
                    'new_value' => ['ditambahkan' => $added],
                    'description' => "{$actor->name} menambahkan ".count($added).' kategori dari spreadsheet referensi: '.implode(', ', $added).'.',
                ]);
            }
        });

        if ($added === []) {
            $this->info('Semua kategori sudah ada — tidak ada yang ditambahkan.');

            return self::SUCCESS;
        }

        $this->table(['Ditambahkan'], array_map(fn (string $row) => [$row], $added));
        $this->info(count($added).' kategori baru ditambahkan. PIC belum diisi — atur lewat Admin -> Service Catalog.');

        return self::SUCCESS;
    }

    /**
     * Dijalankan lewat CLI — tidak ada siapa pun yang login. Atribusi jatuh
     * ke Administrator pertama, sama seperti EmployeeSync::auditActor()
     * menangani sinkronisasi terjadwal.
     */
    private function auditActor(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{0:ServiceCatalogSubject,1:bool} [subject, apakah baru dibuat]
     */
    private function firstOrCreateSubject(int $issueCategoryId, int $serviceId, int $subcategoryId, string $name): array
    {
        $existing = ServiceCatalogSubject::where('subcategory_id', $subcategoryId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $subject = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategoryId,
            'service_id' => $serviceId,
            'subcategory_id' => $subcategoryId,
            'name' => $name,
            'requires_approval' => false,
            'support_agent_id' => null,
            'it_agent_id' => null,
            'support_level' => 1,
            'is_active' => true,
        ]);

        return [$subject, true];
    }
}

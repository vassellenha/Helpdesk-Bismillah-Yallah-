<?php

namespace Database\Seeders;

use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Import of the master Service Catalog data supplied by the business in
 * "Insiden & Service List Issue for Helpdesk 2.0 (2).xlsx" (sheets:
 * MASTER APLIKASI, INSIDEN, SERVICE, USER ACCESS), pre-flattened into
 * database/seeders/data/service_catalog.csv. The database, not this file,
 * is the runtime source of truth from here on — see ServiceCatalogController.
 *
 * Every write below is a firstOrCreate, so this seeder is safe to re-run
 * after the CSV or MASTER_APPLICATIONS list gets updated — it only inserts
 * what's missing, never duplicates or overwrites existing rows.
 */
class ServiceCatalogSeeder extends Seeder
{
    private const IT_AGENTS = ['Aditya Dwi Nugraha', 'Arief Kurniawan', 'Febria Sahrina', 'Agung Wijayanto', 'Naufal Akbar', 'Sarah', 'Kevin', 'Rian'];
    private const BPO_AGENTS = ['Genta Pratama', 'Rio Saputra', 'Lutfi Ramadhan', 'Maya Prameswari', 'Denny Firmansyah'];

    /**
     * Full company application list from the "MASTER APLIKASI" sheet —
     * broader than the subset of apps that have Incident/Service/Access
     * item definitions below. Apps with no defined subcategories still
     * appear in the New Ticket "Application" picker; their Sub Category
     * gracefully falls back to "Other" (see NewTicketModal.jsx).
     */
    private const MASTER_APPLICATIONS = [
        'ADELE', 'ADHI MAN-POWER', 'ADHIMIS-JO', 'ADHISEHAT', 'AISO', 'ANDINI', 'ANTISPAM',
        'APB ERP', 'APG ERP', 'ARINA (DASHBOARD)', 'ARISE', 'Asset Management System', 'BIMO',
        'CCM', 'CLOUDIA', 'CRM', 'DHIERA', 'EA ADHI', 'ELISA', 'ERISKA', 'FIDA', 'HRIS',
        'iBLAST', 'ILMU', 'InnoDash', 'INSAP', 'KMS', 'MAIA', 'MAILIA', 'NAGIA', 'Sahabat APP',
        'SHISAN', 'SKK', 'WIDIA',
    ];

    private static function agentEmail(string $name): string
    {
        $slug = str(str($name)->ascii())->lower()->replace(' ', '.');

        return "{$slug}@adhikarya-helpdesk.test";
    }

    public function run(): void
    {
        foreach (self::MASTER_APPLICATIONS as $name) {
            ServiceCatalogService::firstOrCreate(['name' => $name]);
        }

        $issueCategories = collect(['Incident', 'Service Request', 'Access Request'])
            ->mapWithKeys(fn ($name) => [$name => IssueCategory::firstOrCreate(['name' => $name])->id]);

        $itAgentIds = collect(self::IT_AGENTS)->map(fn ($name) => SupportAgent::updateOrCreate(
            ['name' => $name, 'type' => 'it'],
            ['email' => self::agentEmail($name)]
        )->id);
        $bpoAgentIds = collect(self::BPO_AGENTS)->map(fn ($name) => SupportAgent::updateOrCreate(
            ['name' => $name, 'type' => 'bpo'],
            ['email' => self::agentEmail($name)]
        )->id);

        // Aditya Dwi Nugraha is also the seeded "Support IT" login persona
        // (see UserRoleSeeder) — link his SupportAgent row to that real user
        // so CurrentActor::support() can resolve "my tickets" via this row.
        if ($user = User::where('email', 'aditya.nugraha@adhi.co.id')->first()) {
            SupportAgent::where(['name' => 'Aditya Dwi Nugraha', 'type' => 'it'])->update(['user_id' => $user->id]);
        }

        // Denny Firmansyah is the seeded "Support BPO" login persona (see
        // UserRoleSeeder) — same link as Aditya above, so
        // CurrentActor::supportBpo() can resolve "my tickets" via this row.
        if ($user = User::where('email', 'denny.firmansyah@adhi.co.id')->first()) {
            SupportAgent::where(['name' => 'Denny Firmansyah', 'type' => 'bpo'])->update(['user_id' => $user->id]);
        }

        $itCursor = 0;
        $bpoCursor = 0;

        $path = database_path('seeders/data/service_catalog.csv');
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            $service = ServiceCatalogService::firstOrCreate(['name' => $data['layanan']]);
            $subcategory = ServiceCatalogSubcategory::firstOrCreate([
                'service_id' => $service->id,
                'name' => $data['subcategory'],
            ]);

            $supportAgentId = null;
            if ($data['support_type'] === 'BPO') {
                $supportAgentId = $bpoAgentIds[$bpoCursor % $bpoAgentIds->count()];
                $bpoCursor++;
            } elseif ($data['support_type'] === 'IT' || $data['support_type'] === 'Tim IT') {
                $supportAgentId = $itAgentIds[$itCursor % $itAgentIds->count()];
                $itCursor++;
            }

            ServiceCatalogSubject::firstOrCreate(
                [
                    'issue_category_id' => $issueCategories[$data['issue_category']],
                    'subcategory_id' => $subcategory->id,
                    'name' => $data['subject'],
                ],
                [
                    'service_id' => $service->id,
                    'requires_approval' => (bool) $data['requires_approval'],
                    'support_agent_id' => $supportAgentId,
                    'support_level' => (int) $data['support_level'],
                    'is_active' => true,
                ]
            );
        }

        fclose($handle);

        $this->alignAccessRequestSequenceRouting($issueCategories['Access Request']);
    }

    /**
     * The business spec ("REV Insiden & Service List Issue for Helpdesk 2.0
     * (1).xlsx") marks these 12 Access Request subjects as needing BOTH BPO
     * and IT (BPO handles first, escalates to IT) — the CSV import above
     * only carries one support_type per row, so they were seeded with just
     * an IT agent in support_agent_id and it_agent_id left null, which made
     * new tickets route straight to Support IT and skip BPO entirely. This
     * moves that IT agent into it_agent_id (the escalation target, same
     * person — no reason to reassign them) and puts a BPO agent in
     * support_agent_id so the routing matches the spec. Safe to re-run: once
     * it_agent_id is set, the subject is skipped.
     */
    private function alignAccessRequestSequenceRouting(int $accessRequestCategoryId): void
    {
        $bpoRotation = collect(['Genta Pratama', 'Rio Saputra', 'Lutfi Ramadhan', 'Maya Prameswari']);
        $fixes = [
            ['subcategory' => 'SAP', 'subject' => 'Penonaktifan akun'],
            ['subcategory' => 'SAP', 'subject' => 'Perubahan data akun'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Pembuatan akun baru'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Penonaktifan akun'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Perubahan data akun'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Reset Password'],
            // Demo ticket AR-2026-0045 already used this subject — route it to
            // Denny Firmansyah specifically so the BPO->IT fix is directly
            // demonstrable through the Support BPO workspace.
            ['subcategory' => 'SAP', 'subject' => 'Penambahan akses aplikasi', 'bpo' => 'Denny Firmansyah'],
            ['subcategory' => 'SAP', 'subject' => 'Penghapusan akses aplikasi'],
            ['subcategory' => 'SAP', 'subject' => 'Perubahan akses aplikasi'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Penambahan akses aplikasi'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Penghapusan akses aplikasi'],
            ['subcategory' => 'SILO (OTHER APPS)', 'subject' => 'Perubahan akses aplikasi'],
        ];

        $bpoCursor = 0;

        foreach ($fixes as $fix) {
            $subject = ServiceCatalogSubject::where('issue_category_id', $accessRequestCategoryId)
                ->where('name', $fix['subject'])
                ->whereHas('subcategory', fn ($q) => $q->where('name', $fix['subcategory']))
                ->first();

            if (! $subject || $subject->it_agent_id) {
                continue;
            }

            if (isset($fix['bpo'])) {
                $bpoName = $fix['bpo'];
            } else {
                $bpoName = $bpoRotation[$bpoCursor % $bpoRotation->count()];
                $bpoCursor++;
            }

            $bpoAgentId = SupportAgent::where('name', $bpoName)->where('type', 'bpo')->value('id');

            $subject->update([
                'it_agent_id' => $subject->support_agent_id,
                'support_agent_id' => $bpoAgentId,
            ]);
        }
    }
}

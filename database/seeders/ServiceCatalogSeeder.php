<?php

namespace Database\Seeders;

use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use Illuminate\Database\Seeder;

/**
 * One-time import of the master Service Catalog data supplied by the
 * business in "REV Insiden & Service List Issue for Helpdesk 2.0.xlsx"
 * (sheets: INSIDEN, SERVICE, USER ACCESS), pre-flattened into
 * database/seeders/data/service_catalog.csv. The database, not this file,
 * is the runtime source of truth from here on — see ServiceCatalogController.
 */
class ServiceCatalogSeeder extends Seeder
{
    private const IT_AGENTS = ['Aditya Dwi Nugraha', 'Arief Kurniawan', 'Febria Sahrina', 'Agung Wijayanto', 'Naufal Akbar', 'Sarah', 'Kevin', 'Rian'];
    private const BPO_AGENTS = ['Genta Pratama', 'Rio Saputra', 'Lutfi Ramadhan', 'Maya Prameswari'];

    public function run(): void
    {
        if (ServiceCatalogSubject::exists()) {
            return;
        }

        $issueCategories = collect(['Incident', 'Service Request', 'Access Request'])
            ->mapWithKeys(fn ($name) => [$name => IssueCategory::firstOrCreate(['name' => $name])->id]);

        $itAgentIds = collect(self::IT_AGENTS)->map(fn ($name) => SupportAgent::firstOrCreate(['name' => $name, 'type' => 'it'])->id);
        $bpoAgentIds = collect(self::BPO_AGENTS)->map(fn ($name) => SupportAgent::firstOrCreate(['name' => $name, 'type' => 'bpo'])->id);

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
    }
}

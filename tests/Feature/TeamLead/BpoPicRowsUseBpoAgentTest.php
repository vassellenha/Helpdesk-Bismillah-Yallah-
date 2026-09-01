<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Panel "PIC per Subjek Tiket" membaca katalog layanan, dan satu subjek
 * menyimpan DUA pemilik: `support_agent_id` (PIC BPO) dan `it_agent_id`
 * (PIC IT). Tiap Team Lead harus membaca kolom desk-nya sendiri.
 *
 * Kalau kolomnya salah, panel ini tidak error dan tidak kosong — ia menampilkan
 * daftar nama yang tampak masuk akal, hanya saja orang-orangnya bukan bawahan
 * yang membukanya. Kesalahan yang terlihat benar seperti itu tidak akan
 * ketahuan tanpa tes.
 */
final class BpoPicRowsUseBpoAgentTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_tiap_desk_membaca_kolom_pic_miliknya_sendiri(): void
    {
        $it = $this->deskAgent('it', 'Agung Wijayanto');
        $bpo = $this->deskAgent('bpo', 'Denny Firmansyah');

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);

        ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id,
            'service_id' => $service->id,
            'subcategory_id' => $subcategory->id,
            'name' => 'Akses ELISA',
            'requires_approval' => false,
            'support_agent_id' => $bpo->id,
            'it_agent_id' => $it->id,
            'support_level' => 2,
            'is_active' => true,
        ]);

        $this->actingAsRole('team-lead');
        $picIt = collect($this->getJson(route('team-lead.data-feed'))->json('picRows'))->pluck('pic');
        $this->assertContains('Agung Wijayanto', $picIt->all());
        $this->assertNotContains('Denny Firmansyah', $picIt->all());

        $this->actingAsRole('team-lead-bpo');
        $picBpo = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('picRows'))->pluck('pic');
        $this->assertContains('Denny Firmansyah', $picBpo->all());
        $this->assertNotContains(
            'Agung Wijayanto',
            $picBpo->all(),
            'Team Lead BPO harus melihat PIC BPO subjek itu, bukan PIC IT-nya.'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * AuditTrail::record() auto-captures IP/URL from the current routed request
 * (see App\Models\AuditTrail::hasRoutedRequest()), so every existing call
 * site — here, UserRoleController::toggleUserStatus() — gets it for free
 * without passing anything extra.
 */
class AuditTrailIpAndUrlTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    public function test_menonaktifkan_user_mencatat_ip_dan_url_di_audit_trail(): void
    {
        $this->actingAsEvaAdmin();
        $target = User::factory()->create(['helpdesk_access' => 'enabled']);

        $this->post(route('admin.users.toggle', $target))->assertOk();

        $entry = AuditTrail::where('target_type', 'user')->where('target_id', $target->id)->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame('user_role_management', $entry->module);
        $this->assertSame('deactivate', $entry->action);
        $this->assertNotNull($entry->ip_address);
        $this->assertStringContainsString('admin/users/'.$target->id.'/toggle', $entry->url);
    }

    public function test_record_tanpa_konteks_request_tidak_mengisi_ip_dan_url(): void
    {
        $actor = User::factory()->create();

        $entry = AuditTrail::record($actor, [
            'module' => 'integration',
            'action' => 'sync',
            'target_type' => 'user',
            'target_name' => $actor->name,
            'description' => 'sinkronisasi sistem',
        ]);

        $this->assertNull($entry->ip_address);
        $this->assertNull($entry->url);
    }
}

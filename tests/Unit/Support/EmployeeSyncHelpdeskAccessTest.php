<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\EmployeeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * A brand-new employee synced in already inactive at the company used to land
 * with helpdesk_access at the schema default ('enabled') — nobody had granted
 * them access, but the User & Role Management row action menu read that flag
 * literally and offered "Nonaktifkan Akses" first, as if disabling were still
 * pending for someone who was never active in the first place. This is what
 * happened to Denny Firmansyah (and, unnoticed, to every other employee who
 * arrived already inactive — Siti Nurhaliza, Maria Christin).
 *
 * createUser() now seeds helpdesk_access from the same incoming status,
 * because a fresh account has no Admin decision to protect yet.
 */
class EmployeeSyncHelpdeskAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'employees_fixture_').'.json';
        Config::set('integrations.employee_directory.driver', 'mock');
        Config::set('integrations.employee_directory.mock.fixture', $this->fixturePath);
        Config::set('integrations.employee_directory.overwrite_with_empty', false);

        // EmployeeSync::run() records its own audit entry as CurrentActor::admin(),
        // which falls back to this fixed NIP outside a real SSO session.
        User::factory()->create(['nip' => '19870114001', 'status' => 'active', 'helpdesk_access' => 'enabled']);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);
        parent::tearDown();
    }

    public function test_karyawan_baru_yang_sudah_nonaktif_dibuat_dengan_akses_nonaktif(): void
    {
        $this->writeFixture([$this->row('19960130096', 'Denny Firmansyah', 'NONAKTIF')]);

        EmployeeSync::run();

        $user = User::where('nip', '19960130096')->firstOrFail();
        $this->assertSame('inactive', $user->status);
        $this->assertSame('disabled', $user->helpdesk_access);
    }

    public function test_karyawan_baru_yang_aktif_tetap_dibuat_dengan_akses_aktif(): void
    {
        $this->writeFixture([$this->row('10027761', 'Agung Wijayanto', 'AKTIF')]);

        EmployeeSync::run();

        $user = User::where('nip', '10027761')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertSame('enabled', $user->helpdesk_access);
    }

    /** @return array<string,mixed> */
    private function row(string $nip, string $name, string $statusCode): array
    {
        return [
            'nama_lengkap' => $name,
            'email_korporat' => strtolower(str_replace(' ', '.', $name)).'@adhi.test',
            'nip' => $nip,
            'status_pegawai' => $statusCode,
            'jabatan' => 'Staff',
        ];
    }

    /** @param  array<int,array<string,mixed>>  $rows */
    private function writeFixture(array $rows): void
    {
        file_put_contents($this->fixturePath, json_encode($rows));
    }
}

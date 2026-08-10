<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\EmployeeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * 1.278 of the 3.847 employees the ADHI API sends have no corporate email
 * (verified 2026-08-10). `users.email` is unique, so writing them as "" lets the
 * first one through and collides the other 1.277 — aborting the sync partway,
 * after some employees have already been created. Blank now becomes NULL, which
 * a unique index accepts any number of times.
 *
 * The two-blank-rows test is the one that matters: a single blank row passed
 * before this fix too, which is exactly why the bug survived to production
 * config.
 */
class EmployeeSyncBlankEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'employees_blank_email_').'.json';
        Config::set('integrations.employee_directory.driver', 'mock');
        Config::set('integrations.employee_directory.mock.fixture', $this->fixturePath);
        Config::set('integrations.employee_directory.overwrite_with_empty', false);

        // EmployeeSync::run() attributes its audit entry to CurrentActor::admin(),
        // which falls back to this fixed NIP outside a real SSO session.
        User::factory()->create(['nip' => '19870114001', 'status' => 'active', 'helpdesk_access' => 'enabled']);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);
        parent::tearDown();
    }

    public function test_dua_pegawai_tanpa_email_dua_duanya_dibuat_tanpa_tabrakan(): void
    {
        $this->writeFixture([
            $this->row('B/22/07/2410/79', 'Kevin Tanpa Email', ''),
            $this->row('B/22/07/2410/80', 'Rian Tanpa Email', ''),
        ]);

        $summary = EmployeeSync::run();

        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, User::whereNull('email')->whereIn('nip', ['B/22/07/2410/79', 'B/22/07/2410/80'])->count());
    }

    public function test_email_kosong_disimpan_sebagai_null_bukan_string_kosong(): void
    {
        $this->writeFixture([$this->row('B/22/07/2410/81', 'Tanpa Email', '')]);

        EmployeeSync::run();

        $user = User::where('nip', 'B/22/07/2410/81')->firstOrFail();
        $this->assertNull($user->email, 'Email kosong harus NULL — "" hanya boleh ada satu baris di unique index.');
    }

    public function test_email_yang_terisi_tetap_ditulis_apa_adanya(): void
    {
        $this->writeFixture([$this->row('B/22/07/2410/82', 'Punya Email', 'punya.email@adhi.co.id')]);

        EmployeeSync::run();

        $this->assertSame('punya.email@adhi.co.id', User::where('nip', 'B/22/07/2410/82')->firstOrFail()->email);
    }

    /**
     * With overwrite_with_empty = false, an API row that stops sending an email
     * must not wipe the address already on file — otherwise one sparse feed
     * would strip the helpdesk of every address it has, and with it every
     * notification email.
     */
    public function test_email_yang_sudah_ada_tidak_dihapus_oleh_api_yang_mengirim_kosong(): void
    {
        User::factory()->create([
            'nip' => 'B/22/07/2410/83',
            'email' => 'sudah.ada@adhi.co.id',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $this->writeFixture([$this->row('B/22/07/2410/83', 'Sudah Ada', '')]);

        EmployeeSync::run();

        $this->assertSame('sudah.ada@adhi.co.id', User::where('nip', 'B/22/07/2410/83')->firstOrFail()->email);
    }

    /** @return array<string,mixed> */
    private function row(string $npp, string $name, string $email): array
    {
        return [
            'npp' => $npp,
            'name' => $name,
            'email' => $email,
            'active' => 'Y',
            'job_position' => 'Staff',
        ];
    }

    /** @param  array<int,array<string,mixed>>  $rows */
    private function writeFixture(array $rows): void
    {
        file_put_contents($this->fixturePath, json_encode($rows));
    }
}

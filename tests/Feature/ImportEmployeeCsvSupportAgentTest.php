<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impor CSV yang memberi role Support HARUS ikut membuat baris support_agents.
 *
 * `roles` dan `support_agents` dua hal berbeda: yang pertama menentukan siapa
 * boleh MEMBUKA layar Support, yang kedua adalah identitas KERJANYA — baris
 * yang ditunjuk tickets.assigned_agent_id dan yang mengisi dropdown PIC di
 * Service Catalog.
 *
 * UserRoleController sudah memanggil SupportAgentSync pada dua jalurnya, jadi
 * Admin yang bekerja lewat layar aman. Importer ini luput: ia membaca kolom
 * "Role" dari CSV apa adanya, sehingga satu baris berisi "Support IT" memberi
 * rolenya tanpa membuat baris agentnya. Gejalanya persis kegagalan yang dulu
 * membuat SupportAgentSync ditulis — orangnya tidak muncul di dropdown PIC,
 * dan dashboard Support-nya menjawab 404 — tapi kali ini lewat pintu lain,
 * diam-diam, tanpa satu pun pesan galat.
 */
final class ImportEmployeeCsvSupportAgentTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        // --apply sengaja ditolak di luar environment local (lihat
        // ImportEmployeeCsvExport::handle). Tes berjalan di "testing", jadi
        // environment-nya disamarkan agar jalur yang diuji benar-benar jalan.
        $this->app->detectEnvironment(fn () => 'local');

        foreach (['Requester', 'Support IT', 'Support BPO'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $this->path = storage_path('app/uji-impor-'.uniqid().'.csv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /** @param list<array{0:string,1:string,2:string,3:string}> $baris NPP, Nama, Email, Role */
    private function tulisCsv(array $baris): void
    {
        $isi = "NPP,Nama,Email,Telepon,Jabatan,Unit Kerja,Status,Terakhir Login,Role\n";

        foreach ($baris as [$npp, $nama, $email, $role]) {
            $isi .= sprintf("%s,%s,%s,-,Staf,TI,Aktif,-,\"%s\"\n", $npp, $nama, $email, $role);
        }

        file_put_contents($this->path, $isi);
    }

    public function test_role_support_dari_csv_ikut_membuat_baris_agent(): void
    {
        $this->tulisCsv([['9001', 'Budi Support', 'budi.support@adhi.co.id', 'Requester, Support IT']]);

        $this->artisan('employees:import-csv', ['path' => $this->path, '--apply' => true])
            ->assertSuccessful();

        $user = User::where('nip', '9001')->firstOrFail();
        $agent = SupportAgent::where('user_id', $user->id)->where('type', 'it')->first();

        $this->assertNotNull($agent, 'baris support_agents tidak dibuat untuk role Support IT');
        $this->assertTrue($agent->is_active);
        $this->assertSame('Budi Support', $agent->name);
    }

    /** Dua role Support sekaligus berarti dua baris agent, bukan satu. */
    public function test_dua_role_support_membuat_dua_baris_agent(): void
    {
        $this->tulisCsv([['9002', 'Sari Ganda', 'sari.ganda@adhi.co.id', 'Support IT, Support BPO']]);

        $this->artisan('employees:import-csv', ['path' => $this->path, '--apply' => true])
            ->assertSuccessful();

        $user = User::where('nip', '9002')->firstOrFail();

        $this->assertSame(
            ['bpo', 'it'],
            SupportAgent::where('user_id', $user->id)->pluck('type')->sort()->values()->all(),
        );
    }

    /** Role biasa tidak boleh menghasilkan baris agent apa pun. */
    public function test_role_bukan_support_tidak_membuat_baris_agent(): void
    {
        $this->tulisCsv([['9003', 'Ani Biasa', 'ani.biasa@adhi.co.id', 'Requester']]);

        $this->artisan('employees:import-csv', ['path' => $this->path, '--apply' => true])
            ->assertSuccessful();

        $user = User::where('nip', '9003')->firstOrFail();

        $this->assertSame(0, SupportAgent::where('user_id', $user->id)->count());
    }

    /** Pratinjau tetap pratinjau: tanpa --apply tidak ada yang ditulis. */
    public function test_pratinjau_tidak_membuat_baris_agent(): void
    {
        $this->tulisCsv([['9004', 'Dedi Coba', 'dedi.coba@adhi.co.id', 'Support BPO']]);

        $this->artisan('employees:import-csv', ['path' => $this->path])->assertSuccessful();

        $this->assertSame(0, SupportAgent::count());
        $this->assertNull(User::where('nip', '9004')->first());
    }

    /** User yang SUDAH ADA dan baru diberi role Support lewat CSV juga terurus. */
    public function test_user_lama_yang_baru_diberi_role_support_ikut_dapat_baris_agent(): void
    {
        $user = User::create([
            'nip' => '9005',
            'name' => 'Rina Lama',
            'email' => 'rina.lama@adhi.co.id',
            'password' => bcrypt('rahasia'),
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::where('name', 'Requester')->value('id'));

        $this->tulisCsv([['9005', 'Rina Lama', 'rina.lama@adhi.co.id', 'Requester, Support BPO']]);

        $this->artisan('employees:import-csv', ['path' => $this->path, '--apply' => true])
            ->assertSuccessful();

        $agent = SupportAgent::where('user_id', $user->id)->where('type', 'bpo')->first();

        $this->assertNotNull($agent, 'user lama yang naik jadi Support BPO tidak dapat baris agent');
        $this->assertTrue($agent->is_active);
    }
}

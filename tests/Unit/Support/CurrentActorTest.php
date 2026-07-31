<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\CurrentActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tujuh persona tetap dicari lewat NIP, bukan email — dan tes ini ada karena
 * asumsi sebelumnya (email) baru saja terbukti salah di database dev
 * sungguhan: seorang admin mengubah nama & email "Karina Putri" jadi
 * "Karina AESPA" lewat fitur admin-overridden-fields yang baru masuk, dan
 * approver() langsung 404 — bukan exception yang jelas, seluruh halaman
 * approver tampak seperti route yang hilang.
 *
 * NIP dipilih karena itu KUNCI PENCOCOKAN EmployeeSync sendiri
 * ($matchBy = 'nip' bawaan di config/integrations.php) — mengubahnya berarti
 * memutus pencocokan sinkronisasi karyawan itu sendiri, jauh lebih jarang
 * disentuh iseng dibanding nama tampilan atau email.
 */
final class CurrentActorTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $nip, string $roleName, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'name' => 'Nama Lama',
            'email' => 'lama@adhi.co.id',
        ], $overrides));

        $role = \App\Models\Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_approver_tetap_ketemu_setelah_nama_dan_email_diubah_admin(): void
    {
        $persona = $this->persona('19900322014', 'Approver', [
            'name' => 'Karina Putri',
            'email' => 'karina.putri@adhi.co.id',
        ]);

        // Admin mengedit tampilan — persis kejadian yang memicu 404 di database
        // dev sungguhan. NIP-nya TIDAK berubah.
        $persona->update(['name' => 'Karina AESPA', 'email' => 'karina.mantep@adhi.co.id']);

        $ditemukan = CurrentActor::approver();

        $this->assertSame($persona->id, $ditemukan->id);
    }

    public function test_admin_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('19870114001', 'Administrator', [
            'email' => 'marcell.laforteza@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::admin()->id);
    }

    public function test_requester_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('19950418102', 'Requester', [
            'email' => 'andi.pratama@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::requester()->id);
    }

    public function test_support_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('10027761', 'Support IT', [
            'email' => 'aditya.nugraha@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::support()->id);
    }

    public function test_team_lead_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('19891117033', 'Team Lead', [
            'email' => 'raka.mahendra@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::teamLead()->id);
    }

    public function test_support_bpo_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('19960130096', 'Support BPO', [
            'email' => 'denny.firmansyah@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::supportBpo()->id);
    }

    public function test_knowledge_admin_tetap_ketemu_setelah_email_diubah(): void
    {
        $persona = $this->persona('19920504052', 'Knowledge Administrator', [
            'email' => 'nina.amelia@adhi.co.id',
        ]);

        $persona->update(['email' => 'sudah-ganti@adhi.co.id']);

        $this->assertSame($persona->id, CurrentActor::knowledgeAdmin()->id);
    }
}

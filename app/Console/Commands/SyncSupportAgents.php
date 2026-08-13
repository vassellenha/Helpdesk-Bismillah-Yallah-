<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\SupportAgent;
use App\Models\User;
use App\Support\SupportAgentSync;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Menyelaraskan support_agents dengan role Support yang sudah terlanjur
 * diberikan sebelum SupportAgentSync ada.
 *
 * Sejak sinkronisasi dipasang di UserRoleController, setiap pemberian role
 * Support membuat baris agentnya sendiri. Yang tidak tertangani adalah data
 * LAMA: user yang sudah memegang rolenya sejak sebelum itu tetap tanpa baris
 * agent, dan dashboard Support-nya tetap 404.
 *
 * Ditulis sebagai perintah, bukan migrasi, karena ini menyentuh data yang
 * berbeda di tiap lingkungan dan pantas dilihat dulu sebelum diubah. Bawaannya
 * HANYA melaporkan; perubahan baru terjadi dengan --apply.
 */
class SyncSupportAgents extends Command
{
    protected $signature = 'support:sync-agents {--apply : Simpan perubahannya, bukan sekadar melihat}';

    protected $description = 'Membuat/menonaktifkan baris support_agents agar sesuai role Support tiap user';

    /** Nama role → tipe agent, sama dengan SupportAgentSync. */
    private const ROLES = ['Support IT' => 'it', 'Support BPO' => 'bpo'];

    public function handle(): int
    {
        $baris = [];

        foreach (self::ROLES as $role => $type) {
            if (! Role::where('name', $role)->exists()) {
                continue;
            }

            foreach ($this->pemegang($role) as $user) {
                $agent = SupportAgent::where('user_id', $user->id)->where('type', $type)->first();

                if ($agent === null) {
                    $baris[] = [$user->name, $role, 'belum punya baris agent', 'akan dibuat'];
                } elseif (! $agent->is_active) {
                    $baris[] = [$user->name, $role, 'agentnya nonaktif', 'akan diaktifkan'];
                }
            }
        }

        // Arah sebaliknya: agent aktif yang rolenya sudah dicabut.
        foreach ($this->agentTanpaRole() as [$agent, $type]) {
            $baris[] = [$agent->name, strtoupper($type), 'rolenya sudah dicabut', 'akan dinonaktifkan'];
        }

        if ($baris === []) {
            $this->info('Semua role Support sudah sesuai dengan baris support_agents.');

            return self::SUCCESS;
        }

        $this->table(['Nama', 'Role', 'Keadaan sekarang', 'Tindakan'], $baris);

        if (! $this->option('apply')) {
            $this->warn(count($baris).' penyesuaian menunggu.');
            $this->line('Jalankan ulang dengan --apply untuk menyimpannya.');

            return self::SUCCESS;
        }

        // Dua arah sekaligus: yang memegang rolenya, DAN yang punya baris agent
        // tapi rolenya sudah dicabut. Tanpa kelompok kedua, agent yang harus
        // dinonaktifkan tidak pernah ikut disentuh.
        $idBerAgent = SupportAgent::whereNotNull('user_id')->pluck('user_id');

        $tersentuh = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_keys(self::ROLES)))
            ->orWhereIn('id', $idBerAgent)
            ->with('roles')
            ->get();

        foreach ($tersentuh as $user) {
            SupportAgentSync::reconcile($user);
        }

        $this->info(count($baris).' penyesuaian diterapkan. Dashboard Support mereka tidak lagi 404.');

        return self::SUCCESS;
    }

    /** @return Collection<int,User> */
    private function pemegang(string $role)
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))->get();
    }

    /** @return list<array{0:SupportAgent,1:string}> */
    private function agentTanpaRole(): array
    {
        $hasil = [];

        foreach (self::ROLES as $role => $type) {
            $agents = SupportAgent::where('type', $type)
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->with('user.roles')
                ->get();

            foreach ($agents as $agent) {
                if ($agent->user && ! $agent->user->roles->pluck('name')->contains($role)) {
                    $hasil[] = [$agent, $type];
                }
            }
        }

        return $hasil;
    }
}

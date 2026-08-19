<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pengisian satu kali: berikan role Approver (dan pastikan Requester) ke
 * setiap user yang jabatannya mengandung kata "manager".
 *
 * Latar belakangnya: EmployeeSync hanya pernah memberi role default
 * (Requester) ke pegawai yang disinkronkan — tidak ada aturan otomatis yang
 * membaca jabatan. Approver sejauh ini hanya melekat pada satu akun demo yang
 * di-seed manual (lihat UserRoleSeeder), jadi hampir semua manajer sungguhan
 * tidak muncul di dropdown approver tiket manapun.
 *
 * Ini bukan aturan permanen — perintah ini hanya mengisi data yang sudah ada
 * sekarang. User baru dari sync berikutnya tetap harus diberi role Approver
 * secara manual lewat User & Role Management.
 *
 * Aman diulang: hanya menempelkan role yang belum dimiliki, tidak pernah
 * melepas role lain yang sudah ada pada user tersebut.
 */
class AssignApproverToManagers extends Command
{
    protected $signature = 'users:assign-approver-managers
                            {--apply : Tulis ke database. Tanpa ini perintah hanya menampilkan rencana.}';

    protected $description = 'Berikan role Approver ke semua user yang jabatannya mengandung "manager".';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $approver = Role::where('name', 'Approver')->first();
        $requester = Role::where('name', 'Requester')->first();

        if (! $approver || ! $requester) {
            $this->error('Role Approver dan/atau Requester tidak ditemukan di tabel roles.');

            return self::FAILURE;
        }

        $users = User::whereNotNull('jabatan')
            ->where('jabatan', 'like', '%manager%')
            ->with('roles')
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada user dengan jabatan mengandung "manager". Tidak ada yang dikerjakan.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($users as $user) {
            $ownedIds = $user->roles->pluck('id')->all();
            $toAttach = array_values(array_diff([$approver->id, $requester->id], $ownedIds));

            if ($toAttach === []) {
                continue;
            }

            $labels = collect($toAttach)->map(fn ($id) => $id === $approver->id ? 'Approver' : 'Requester');

            $rows[] = ['user' => $user, 'attach' => $toAttach, 'labels' => $labels];
        }

        if ($rows === []) {
            $this->info(sprintf(
                'Ditemukan %d user dengan jabatan mengandung "manager", tapi semuanya sudah punya role Approver dan Requester. Tidak ada yang dikerjakan.',
                $users->count(),
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Ditemukan %d user dengan jabatan mengandung "manager", %d di antaranya perlu ditambah role.',
            $users->count(),
            count($rows),
        ));

        $this->table(
            ['Nama', 'Jabatan', 'Role ditambahkan'],
            collect($rows)->map(fn (array $r) => [
                $r['user']->name,
                $r['user']->jabatan,
                $r['labels']->implode(', '),
            ])->all(),
        );

        if (! $apply) {
            $this->newLine();
            $this->info('Ini baru rencana. Jalankan ulang dengan --apply untuk menulisnya.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                $r['user']->roles()->attach($r['attach']);
            }
        });

        $this->newLine();
        $this->info(count($rows).' user diperbarui.');

        return self::SUCCESS;
    }
}

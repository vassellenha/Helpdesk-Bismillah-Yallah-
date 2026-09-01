<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    private const ROLES = [
        ['name' => 'Requester', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Approver', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Support IT', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Support BPO', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Team Lead IT', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Team Lead BPO', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Administrator', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Knowledge Administrator', 'type' => 'system', 'status' => 'active', 'locked' => false],
    ];

    private const USERS = [
        ['name' => 'Andi Pratama', 'nip' => '19950418102', 'email' => 'andi.pratama@adhi.co.id', 'phone' => '+6281200011122', 'address' => 'Jl. Raya Pasar Minggu KM 18, Pancoran, Jakarta Selatan 12510', 'unit' => 'IT & Operations Bureau', 'jabatan' => 'Requester', 'kode_departemen' => 'DEPT-ITO', 'kode_divisi' => 'DIV-OPS', 'roles' => ['Requester'], 'status' => 'active'],
        ['name' => 'Marcell Laforteza', 'nip' => '19870114001', 'email' => 'marcell.laforteza@adhi.co.id', 'phone' => '+6281234567890', 'address' => 'Jl. Tebet Barat Dalam Raya No. 44, Tebet, Jakarta Selatan 12810', 'unit' => 'Dept. Strategi Korporasi', 'jabatan' => 'Administrator Sistem', 'kode_departemen' => 'DEPT-STK', 'kode_divisi' => 'DIV-KOR', 'roles' => ['Administrator', 'Requester'], 'status' => 'active'],
        ['name' => 'Karina Putri', 'nip' => '19900322014', 'email' => 'karina.putri@adhi.co.id', 'phone' => '+6281298765432', 'address' => 'Jl. Cipinang Cempedak IV No. 12, Jatinegara, Jakarta Timur 13340', 'unit' => 'Dept. Pengendali Operasi', 'jabatan' => 'Manager Dept. Pengendali Operasi', 'kode_departemen' => 'DEPT-PGO', 'kode_divisi' => 'DIV-OPS', 'roles' => ['Approver', 'Team Lead IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Rizky Hidayat', 'nip' => '19880609027', 'email' => 'rizky.hidayat@adhi.co.id', 'phone' => '+6281322233344', 'address' => 'Jl. Margonda Raya No. 210, Beji, Depok 16424', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Team Lead', 'kode_departemen' => 'DEPT-SCM', 'kode_divisi' => 'DIV-OPS', 'roles' => ['Team Lead BPO', 'Requester'], 'status' => 'active'],
        ['name' => 'Dimas Kurniawan', 'nip' => '10021190', 'email' => 'dimas.kurniawan@adhi.co.id', 'phone' => '+6281811166677', 'address' => 'Jl. Bintaro Utama Sektor 3A No. 8, Pesanggrahan, Jakarta Selatan 12330', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Team Lead', 'kode_departemen' => 'DEPT-KEU', 'kode_divisi' => 'DIV-FIN', 'roles' => ['Team Lead IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Raka Mahendra', 'nip' => '19891117033', 'email' => 'raka.mahendra@adhi.co.id', 'phone' => '+6281911177788', 'address' => 'Jl. Cikutra Baru Raya No. 27, Cibeunying, Bandung 40124', 'unit' => 'Dept. Infrastruktur I', 'jabatan' => 'Team Lead', 'kode_departemen' => 'DEPT-INF1', 'kode_divisi' => 'DIV-INF', 'roles' => ['Team Lead IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Fajar Nugraha', 'nip' => '19850228041', 'email' => 'fajar.nugraha@adhi.co.id', 'phone' => '+6281433344455', 'address' => 'Jl. Kelapa Gading Boulevard Blok C No. 5, Kelapa Gading, Jakarta Utara 14240', 'unit' => 'Satuan Pengawas Internal', 'jabatan' => 'Team Lead', 'kode_departemen' => 'DEPT-SPI', 'kode_divisi' => 'DIV-KOR', 'roles' => ['Team Lead IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Nina Amelia', 'nip' => '19920504052', 'email' => 'nina.amelia@adhi.co.id', 'phone' => '+6281544455566', 'address' => 'Jl. Ciputat Raya No. 91, Pondok Pinang, Jakarta Selatan 12310', 'unit' => 'Adhi Learning Center', 'jabatan' => 'Knowledge Administrator', 'kode_departemen' => 'DEPT-ALC', 'kode_divisi' => 'DIV-SDM', 'roles' => ['Team Lead IT', 'Knowledge Administrator', 'Requester'], 'status' => 'active'],
        ['name' => 'Aditya Dwi Nugraha', 'nip' => '10027761', 'email' => 'aditya.nugraha@adhi.co.id', 'phone' => '+6281611144455', 'address' => 'Jl. Duren Tiga Selatan No. 17, Pancoran, Jakarta Selatan 12760', 'unit' => 'Dept. Strava', 'jabatan' => 'IT Support Staff', 'kode_departemen' => 'DEPT-STV', 'kode_divisi' => 'DIV-ITO', 'roles' => ['Support IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Siti Nurhaliza', 'nip' => '19930712063', 'email' => 'siti.nurhaliza@adhi.co.id', 'phone' => '+6281755566677', 'address' => 'Jl. Kebagusan Raya No. 33, Pasar Minggu, Jakarta Selatan 12520', 'unit' => 'Dept. SDM', 'jabatan' => 'HR Staff', 'kode_departemen' => 'DEPT-SDM', 'kode_divisi' => 'DIV-SDM', 'roles' => ['Requester'], 'status' => 'inactive'],
        ['name' => 'Budi Santoso', 'nip' => '19940815074', 'email' => 'budi.santoso@adhi.co.id', 'phone' => '+6281866677788', 'address' => 'Jl. MT Haryono No. 56, Balikpapan Kota, Balikpapan 76112', 'unit' => 'Dept. Proyek Balikpapan', 'jabatan' => 'Site Engineer', 'kode_departemen' => 'DEPT-PBP', 'kode_divisi' => 'DIV-INF', 'kode_proyek' => 'PRJ-BPP-01', 'nama_proyek' => 'Pembangunan Jalan Tol Balikpapan', 'roles' => ['Requester'], 'status' => 'active'],
        ['name' => 'Maria Christin', 'nip' => '19910925085', 'email' => 'maria.christin@adhi.co.id', 'phone' => '+6281977788899', 'address' => 'Jl. Sunter Agung Podomoro Blok B2 No. 14, Tanjung Priok, Jakarta Utara 14350', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Finance Staff', 'kode_departemen' => 'DEPT-KEU', 'kode_divisi' => 'DIV-FIN', 'roles' => ['Requester'], 'status' => 'inactive'],
        ['name' => 'Denny Firmansyah', 'nip' => '19960130096', 'email' => 'denny.firmansyah@adhi.co.id', 'phone' => '+6282188899900', 'address' => 'Jl. Raya Bogor KM 26 No. 4, Ciracas, Jakarta Timur 13740', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Procurement Staff', 'kode_departemen' => 'DEPT-SCM', 'kode_divisi' => 'DIV-OPS', 'roles' => ['Support BPO', 'Requester'], 'status' => 'active'],
    ];

    public function run(): void
    {
        $roles = collect(self::ROLES)->mapWithKeys(fn ($r) => [$r['name'] => Role::firstOrCreate(['name' => $r['name']], $r)->id]);

        foreach (self::USERS as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'username' => $data['email'],
                    'nip' => $data['nip'],
                    'phone' => $data['phone'],
                    'address' => $data['address'] ?? null,
                    'unit' => $data['unit'],
                    'jabatan' => $data['jabatan'],
                    'kode_departemen' => $data['kode_departemen'] ?? null,
                    'kode_divisi' => $data['kode_divisi'] ?? null,
                    'kode_proyek' => $data['kode_proyek'] ?? null,
                    'nama_proyek' => $data['nama_proyek'] ?? null,
                    'status' => $data['status'],
                    'last_login_at' => now()->subHours(random_int(1, 48)),
                ]
            );

            // Columns introduced after these demo accounts were first seeded stay
            // null on re-run, so fill the blanks without clobbering admin edits.
            foreach (['username', 'address', 'kode_departemen', 'kode_divisi'] as $column) {
                if ($user->{$column} === null) {
                    $user->{$column} = $column === 'username' ? $data['email'] : ($data[$column] ?? null);
                }
            }
            $user->save();

            $user->roles()->sync(collect($data['roles'])->map(fn ($name) => $roles[$name]));
        }
    }
}

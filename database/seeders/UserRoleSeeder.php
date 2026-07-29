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
        ['name' => 'Team Lead', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Administrator', 'type' => 'system', 'status' => 'active', 'locked' => false],
        ['name' => 'Knowledge Administrator', 'type' => 'system', 'status' => 'active', 'locked' => false],
    ];

    private const USERS = [
        ['name' => 'Andi Pratama', 'nip' => '19950418102', 'email' => 'andi.pratama@adhi.co.id', 'whatsapp' => '+6281200011122', 'unit' => 'IT & Operations Bureau', 'jabatan' => 'Requester', 'roles' => ['Requester'], 'status' => 'active'],
        ['name' => 'Marcell Laforteza', 'nip' => '19870114001', 'email' => 'marcell.laforteza@adhi.co.id', 'whatsapp' => '+6281234567890', 'unit' => 'Dept. Strategi Korporasi', 'jabatan' => 'Administrator Sistem', 'roles' => ['Administrator', 'Requester'], 'status' => 'active'],
        ['name' => 'Karina Putri', 'nip' => '19900322014', 'email' => 'karina.putri@adhi.co.id', 'whatsapp' => '+6281298765432', 'unit' => 'Dept. Pengendali Operasi', 'jabatan' => 'Manager Dept. Pengendali Operasi', 'roles' => ['Approver', 'Team Lead', 'Requester'], 'status' => 'active'],
        ['name' => 'Rizky Hidayat', 'nip' => '19880609027', 'email' => 'rizky.hidayat@adhi.co.id', 'whatsapp' => '+6281322233344', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Team Lead', 'roles' => ['Team Lead', 'Requester'], 'status' => 'active'],
        ['name' => 'Dimas Kurniawan', 'nip' => '10021190', 'email' => 'dimas.kurniawan@adhi.co.id', 'whatsapp' => '+6281811166677', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Team Lead', 'roles' => ['Team Lead', 'Requester'], 'status' => 'active'],
        ['name' => 'Raka Mahendra', 'nip' => '19891117033', 'email' => 'raka.mahendra@adhi.co.id', 'whatsapp' => '+6281911177788', 'unit' => 'Dept. Infrastruktur I', 'jabatan' => 'Team Lead', 'roles' => ['Team Lead', 'Requester'], 'status' => 'active'],
        ['name' => 'Fajar Nugraha', 'nip' => '19850228041', 'email' => 'fajar.nugraha@adhi.co.id', 'whatsapp' => '+6281433344455', 'unit' => 'Satuan Pengawas Internal', 'jabatan' => 'Team Lead', 'roles' => ['Team Lead', 'Requester'], 'status' => 'active'],
        ['name' => 'Nina Amelia', 'nip' => '19920504052', 'email' => 'nina.amelia@adhi.co.id', 'whatsapp' => '+6281544455566', 'unit' => 'Adhi Learning Center', 'jabatan' => 'Knowledge Administrator', 'roles' => ['Team Lead', 'Knowledge Administrator', 'Requester'], 'status' => 'active'],
        ['name' => 'Aditya Dwi Nugraha', 'nip' => '10027761', 'email' => 'aditya.nugraha@adhi.co.id', 'whatsapp' => '+6281611144455', 'unit' => 'Dept. Strava', 'jabatan' => 'IT Support Staff', 'roles' => ['Support IT', 'Requester'], 'status' => 'active'],
        ['name' => 'Siti Nurhaliza', 'nip' => '19930712063', 'email' => 'siti.nurhaliza@adhi.co.id', 'whatsapp' => '+6281755566677', 'unit' => 'Dept. SDM', 'jabatan' => 'HR Staff', 'roles' => ['Requester'], 'status' => 'inactive'],
        ['name' => 'Budi Santoso', 'nip' => '19940815074', 'email' => 'budi.santoso@adhi.co.id', 'whatsapp' => '+6281866677788', 'unit' => 'Dept. Proyek Balikpapan', 'jabatan' => 'Site Engineer', 'kode_proyek' => 'PRJ-BPP-01', 'nama_proyek' => 'Pembangunan Jalan Tol Balikpapan', 'roles' => ['Requester'], 'status' => 'active'],
        ['name' => 'Maria Christin', 'nip' => '19910925085', 'email' => 'maria.christin@adhi.co.id', 'whatsapp' => '+6281977788899', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Finance Staff', 'roles' => ['Requester'], 'status' => 'inactive'],
        ['name' => 'Denny Firmansyah', 'nip' => '19960130096', 'email' => 'denny.firmansyah@adhi.co.id', 'whatsapp' => '+6282188899900', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Procurement Staff', 'roles' => ['Support BPO', 'Requester'], 'status' => 'active'],
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
                    'nip' => $data['nip'],
                    'whatsapp' => $data['whatsapp'],
                    'unit' => $data['unit'],
                    'jabatan' => $data['jabatan'],
                    'kode_proyek' => $data['kode_proyek'] ?? null,
                    'nama_proyek' => $data['nama_proyek'] ?? null,
                    'status' => $data['status'],
                    'last_login_at' => now()->subHours(random_int(1, 48)),
                ]
            );

            $user->roles()->sync(collect($data['roles'])->map(fn ($name) => $roles[$name]));
        }
    }
}

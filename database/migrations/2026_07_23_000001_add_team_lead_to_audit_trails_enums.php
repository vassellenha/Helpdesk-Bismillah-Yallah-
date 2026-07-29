<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team Lead corrective actions (SLA teguran, reassign, raise priority) land
 * in the same shared Audit Trail the Admin/Approval/Support modules already
 * write to — "team_lead" is a fifth module on that log, mirroring the
 * ticket_approval / ticket_support additions before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate') NOT NULL");
    }

    /*
     | ENUM MODIFY hanya dikenal MySQL/MariaDB.
     |
     | Tes repo ini berjalan di SQLite :memory:, dan di sana `ALTER TABLE ...
     | MODIFY` adalah syntax error yang MEMATIKAN SELURUH migrasi — bukan satu
     | tes gagal, melainkan setiap tes berbasis database tidak pernah sampai ke
     | logikanya. Penjaga driver ini memakai pola yang sudah dipakai migrasi
     | kb_* untuk indeks FULLTEXT.
     |
     | Aman dilewati di SQLite: kolomnya di sana bertipe string tanpa batasan
     | ENUM, jadi nilai baru memang sudah bisa masuk tanpa ALTER apa pun.
    */
    private function mendukungEnumModify(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

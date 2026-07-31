<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "integration" module + "sync" action — one audit row per employee directory
 * sync run (EmployeeSync::run()), so an unexplained change to someone's profile
 * can be traced back to the company API rather than to an Admin.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management', 'integration') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating', 'return', 'sync') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating', 'return') NOT NULL");
    }

    /*
     | ENUM MODIFY hanya dikenal MySQL/MariaDB.
     |
     | Tes repo ini berjalan di SQLite :memory:, dan di sana `ALTER TABLE ...
     | MODIFY` adalah syntax error yang MEMATIKAN SELURUH migrasi — bukan satu
     | tes gagal, melainkan setiap tes berbasis database tidak pernah sampai ke
     | logikanya. Penjaga driver ini memakai pola yang sudah dipakai migrasi
     | audit_trails/kb_* lain di repo ini.
     |
     | Aman dilewati di SQLite: kolomnya di sana bertipe string tanpa batasan
     | ENUM, jadi nilai baru memang sudah bisa masuk tanpa ALTER apa pun.
    */
    private function mendukungEnumModify(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

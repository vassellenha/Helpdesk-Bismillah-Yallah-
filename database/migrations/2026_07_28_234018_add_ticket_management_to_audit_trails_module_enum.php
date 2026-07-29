<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin's Ticket Management console gains its first mutating action — the
 * per-ticket "exclude rating from stats" toggle — so it needs its own
 * module on the shared Audit Trail, mirroring the ticket_approval /
 * ticket_support / team_lead additions before it. The action itself reuses
 * the existing activate/deactivate values, no action enum change needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead') NOT NULL");
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "remind_rating" — a Team Lead's rating teguran (reprimand about an agent's
 * overall satisfaction rating, not tied to a single ticket's SLA) — is a new
 * action alongside the existing "remind" (SLA teguran) in the team_lead module.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority') NOT NULL");
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->mendukungEnumModify()) {
            return;
        }

        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management') NOT NULL");
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role') NOT NULL");
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

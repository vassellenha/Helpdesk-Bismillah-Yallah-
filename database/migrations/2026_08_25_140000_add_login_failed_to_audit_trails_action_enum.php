<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "login_failed" — seseorang mencoba masuk memakai akun yang sudah tidak boleh
 * masuk (resign di data kepegawaian, atau aksesnya dicabut Administrator).
 *
 * Sampai sekarang enum ini hanya mengenal "login": pencatatannya menumpang
 * event Login bawaan Laravel, dan event itu cuma menyala kalau login berhasil.
 * Yang ditolak tidak meninggalkan jejak sama sekali — tidak di audit_trails,
 * tidak juga di log aplikasi. Padahal justru percobaan yang ditolak itulah
 * yang dicari Administrator saat memeriksa keamanan.
 *
 * Blueprint::change(), bukan `ALTER ... MODIFY` khusus MySQL — lihat
 * 2026_08_06_110000_add_start_to_audit_trails_action_enum.php untuk alasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout',
                'auto_close', 'delete', 'login_failed',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout',
                'auto_close', 'delete',
            ])->change();
        });
    }
};

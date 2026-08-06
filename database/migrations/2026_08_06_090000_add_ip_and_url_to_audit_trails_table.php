<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every audit entry should say where it came from, not just who and what.
 * Nullable because system-triggered entries (EmployeeSync's console-run sync)
 * have no request to read an IP/URL from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('actor_id');
            $table->string('url', 2048)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'url']);
        });
    }
};

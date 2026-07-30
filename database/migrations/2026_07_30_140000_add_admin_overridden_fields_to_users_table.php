<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Names of synced columns an Admin has manually edited — EmployeeSync
            // skips these forever after, the same way it already skips a column
            // the API sends empty, so a manual correction never gets clobbered by
            // the next sync just because the API also has a (different) value.
            $table->json('admin_overridden_fields')->nullable()->after('kode_proyek');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_overridden_fields');
        });
    }
};

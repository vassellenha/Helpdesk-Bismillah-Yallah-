<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the two meanings that used to share users.status:
 *
 *  - status          — employment, owned by the company employee API. A sync
 *                      overwrites it, because resigning must revoke access.
 *  - helpdesk_access — access to this helpdesk, owned by the Admin. The sync
 *                      never touches it, so a manual suspension survives.
 *
 * Effective access requires both (User::isActive()). Before this split an Admin
 * deactivating someone saw it silently reverted by the next sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('helpdesk_access', ['enabled', 'disabled'])
                ->default('enabled')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('helpdesk_access');
        });
    }
};

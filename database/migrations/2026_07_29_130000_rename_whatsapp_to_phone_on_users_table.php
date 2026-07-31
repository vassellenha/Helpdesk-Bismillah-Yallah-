<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column holds the employee's phone number as it will arrive from the
 * company employee API, so it is named after the source field rather than
 * after WhatsApp — which remains only the name of a teguran delivery channel
 * (see config/notifications.php and TeguranNotifier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('whatsapp', 'phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('phone', 'whatsapp');
        });
    }
};

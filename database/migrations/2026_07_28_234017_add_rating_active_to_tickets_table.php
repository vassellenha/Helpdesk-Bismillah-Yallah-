<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets Admin exclude a Requester's star rating from every average-rating
 * calculation (Support IT/BPO's own displayed score, Team Lead's agent
 * performance view, the "kirim teguran rating" threshold check) without
 * deleting the underlying satisfaction_rating/feedback_note — e.g. a rating
 * left out of spite or by mistake. Defaults to true (counted) so every
 * existing rating keeps counting exactly as it did before this column
 * existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('rating_active')->default(true)->after('satisfaction_rating');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('rating_active');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the SLA measurable rather than just a countdown:
 *
 *  - sla_started_at        when the clock started, so "start at" is a recorded
 *                          fact instead of being back-derived from a due date
 *                          that escalation is about to move
 *  - first_response_at     when Support first replied, which stops the response
 *                          clock independently of the resolution clock
 *  - sla_extension_minutes extra resolution time granted by escalations, kept
 *                          separate from the policy's own resolution_time_minutes
 *                          so the original commitment stays auditable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_started_at')->nullable()->after('warning_at');
            $table->timestamp('first_response_at')->nullable()->after('sla_started_at');
            $table->unsignedInteger('sla_extension_minutes')->default(0)->after('first_response_at');
        });

        // Existing tickets: the clock started when they were created. Portable
        // ANSI SQL — runs the same on every driver, no guard needed.
        DB::statement('UPDATE tickets SET sla_started_at = created_at WHERE sla_started_at IS NULL');

        // And they were first responded to when Support first commented, if
        // ever. `UPDATE ... JOIN` is MySQL/MariaDB-only syntax — see
        // mendukungUpdateJoin() below for why it's guarded instead of ported.
        if ($this->mendukungUpdateJoin()) {
            DB::statement("
                UPDATE tickets t
                JOIN (
                    SELECT ticket_id, MIN(created_at) AS first_at
                    FROM ticket_comments
                    WHERE author_role = 'Support'
                    GROUP BY ticket_id
                ) c ON c.ticket_id = t.id
                SET t.first_response_at = c.first_at
                WHERE t.first_response_at IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_started_at', 'first_response_at', 'sla_extension_minutes']);
        });
    }

    /*
     | `UPDATE ... JOIN ... SET` adalah sintaks MySQL/MariaDB, bukan ANSI SQL —
     | SQLite tidak mengenalinya sama sekali (beda dari kasus ENUM MODIFY yang
     | "hanya" berbeda; ini benar-benar syntax error).
     |
     | Tes repo ini berjalan di SQLite :memory:, dan backfill ini hanya berguna
     | untuk data LAMA yang sudah ada sebelum migrasi jalan. Di database tes
     | yang selalu dimulai kosong (RefreshDatabase memigrasi dulu, baru factory
     | mengisi data), tidak ada satu pun baris `ticket_comments` yang perlu
     | di-backfill — melewatinya di SQLite tidak menghilangkan cakupan tes
     | apa pun, persis seperti migrasi ENUM di sebelahnya.
    */
    private function mendukungUpdateJoin(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

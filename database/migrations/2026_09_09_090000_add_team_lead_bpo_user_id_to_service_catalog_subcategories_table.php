<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa Team Lead BPO yang mengawasi Subkategori ini.
 *
 * MENUNJUK `users`, BUKAN `support_agents` — beda dari setiap kolom lain di
 * katalog yang berbau petugas (`support_agent_id`, `it_agent_id`). Team Lead
 * tidak punya baris `support_agents` sama sekali: SupportAgentSync::
 * ROLE_TO_TYPE cuma memetakan 'Support IT' dan 'Support BPO', jadi FK ke
 * support_agents akan selalu kosong dan tidak akan pernah berbunyi. Sufiks
 * `_user_id` ada supaya orang berikutnya tidak salah membacanya.
 *
 * Dipasang di SUBKATEGORI, bukan Layanan maupun Subjek. Layanan terlalu
 * kasar — SAP sendiri memuat 63 dari 142 Subjek aktif, dan menugaskannya
 * berarti menyerahkan 44% katalog ke satu orang tanpa cara membaginya.
 * Subjek terlalu halus: 142 baris yang harus diisi ulang setiap kali Admin
 * menambah Subjek baru, dan yang terlewat jatuh ke fallback tanpa gejala.
 * Subkategori (40 baris) adalah satu-satunya level yang bisa membelah SAP
 * sekaligus membawa Subjek barunya ikut serta.
 *
 * NULL BERARTI TIDAK ADA YANG BERWENANG. Subkategori tanpa Team Lead tidak
 * terbaca Team Lead BPO manapun — bukan terbaca semuanya. Default yang
 * permisif akan membuat celah wewenang tetap terbuka di setiap baris yang
 * Admin lupa isi, tanpa satu pun tanda bahwa ia terbuka; kelalaian berubah
 * jadi izin. Dengan aturan ini kelalaian berubah jadi layar kosong yang
 * kelihatan dan bisa ditanyakan (lihat App\Support\TeamLeadScope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalog_subcategories', function (Blueprint $table) {
            $table->foreignId('team_lead_bpo_user_id')->nullable()->after('name')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_catalog_subcategories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_lead_bpo_user_id');
        });
    }
};

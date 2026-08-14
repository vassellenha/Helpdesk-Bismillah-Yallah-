<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Layanan" — covers both applications (SAP, ADELE) and non-application
 * services (VPN, Network, Printer). Deliberately not named "Application".
 */
class ServiceCatalogService extends Model
{
    protected $table = 'service_catalog_services';

    protected $fillable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(ServiceCatalogSubcategory::class, 'service_id');
    }

    public function subjects()
    {
        return $this->hasMany(ServiceCatalogSubject::class, 'service_id');
    }

    /**
     * PIC BPO unik yang sudah tertaut ke Subject-Subject aktif Layanan ini —
     * dipakai untuk broadcast tiket "Lainnya" (App\Support\TicketBroadcast).
     * Sengaja TIDAK punya daftar PIC terpisah: PIC broadcast harus selalu
     * sama dengan PIC yang sudah kelihatan di tabel Service Catalog, supaya
     * Admin tidak perlu mengisi dua tempat berbeda untuk data yang sama.
     *
     * Tidak difilter berdasarkan kolom `type` di support_agents — sama
     * seperti TicketController::resolveAssignedAgentId(), slot "BPO" itu
     * ditentukan oleh KOLOM MANA yang dipakai (support_agent_id, beda dari
     * it_agent_id), bukan oleh nilai `type` baris SupportAgent yang
     * ditautkan. Data yang ada membuktikan keduanya bisa tidak sinkron.
     *
     * TAPI difilter berdasarkan role "Support BPO" di akun user-nya —
     * terbukti di data nyata (SAP): dari 8 nama di slot support_agent_id,
     * cuma 3 yang benar-benar punya role itu. Sisanya cuma pegawai IT yang
     * kebetulan namanya ada di kolom itu — kalau tetap diikutkan, mereka
     * dapat notifikasi lalu ditolak (403) begitu coba membukanya, karena
     * setiap layar /support-bpo/* dijaga middleware role:support-bpo.
     * Tanpa role itu, orangnya memang tidak akan pernah bisa menangani
     * tiket apa pun di portal BPO — broadcast maupun rute biasa.
     */
    public function activeBpoAgents()
    {
        $agentIds = $this->subjects()->where('is_active', true)->whereNotNull('support_agent_id')->pluck('support_agent_id');

        return SupportAgent::where('is_active', true)
            ->whereIn('id', $agentIds)
            ->whereHas('user.roles', fn ($q) => $q->where('name', 'Support BPO'));
    }
}

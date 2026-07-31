<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Support\DummyData;
use Illuminate\View\View;

class AuditTrailController extends Controller
{
    public function index(): View
    {
        $logs = AuditTrail::with('actor')->latest('created_at')->limit(500)->get();

        return view('admin.audit-trail', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'logs' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'waktu' => $log->created_at->format('d M Y, H:i'),
                'timestamp' => $log->created_at->timestamp,
                'administrator' => $log->actor->name,
                'module' => $log->module,
                'module_label' => $this->moduleLabel($log->module),
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'target_type' => $log->target_type,
                'target_name' => $log->target_name,
                'description' => $log->description,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
            ]),
            'administrators' => $logs->pluck('actor.name')->unique()->sort()->values(),
        ]);
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'service_catalog' => 'Service Catalog',
            'sla_configuration' => 'Konfigurasi SLA',
            'user_role_management' => 'User & Role Management',
            'ticket_approval' => 'Approval Tiket',
            'ticket_support' => 'Penanganan Support',
            'team_lead' => 'Team Lead',
            'ticket_management' => 'Ticket Management',
            'integration' => 'Integrasi',
            default => $module,
        };
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'create' => 'Tambah',
            'update' => 'Edit',
            'activate' => 'Aktifkan',
            'deactivate' => 'Nonaktifkan',
            'assign_support' => 'Ubah Support',
            'change_level' => 'Ubah Level',
            'change_role' => 'Ubah Role',
            'approve' => 'Setujui',
            'request_revision' => 'Minta Perbaikan',
            'reject' => 'Tolak',
            'resolve' => 'Tutup Layanan',
            'escalate' => 'Eskalasi',
            'remind' => 'Kirim Teguran',
            'reassign' => 'Alihkan Tiket',
            'raise_priority' => 'Naikkan Prioritas',
            'remind_rating' => 'Teguran Rating',
            'return' => 'Dikembalikan',
            'sync' => 'Sinkronisasi',
            default => $action,
        };
    }
}

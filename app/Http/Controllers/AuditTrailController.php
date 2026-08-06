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
                'ip_address' => $log->ip_address,
                'url' => $log->url,
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

    /**
     * Stored module codes never change; only their display label is translated.
     * An unknown code falls through to itself rather than to a lang key, so a
     * new module added elsewhere still reads sensibly before it is translated.
     */
    private function moduleLabel(string $module): string
    {
        $key = match ($module) {
            'service_catalog' => 'catalog',
            'sla_configuration' => 'sla',
            'user_role_management' => 'users',
            'ticket_approval' => 'approval',
            'ticket_support' => 'support',
            'team_lead' => 'teamlead',
            'ticket_management' => 'tickets',
            'integration' => 'integration',
            'auth' => 'auth',
            default => null,
        };

        return $key === null ? $module : __('admin.audit.module_name.'.$key);
    }

    private function actionLabel(string $action): string
    {
        $key = match ($action) {
            'create' => 'create',
            'update' => 'edit',
            'activate' => 'activate',
            'deactivate' => 'deactivate',
            'assign_support' => 'update_support',
            'change_level' => 'update_level',
            'change_role' => 'update_role',
            'approve' => 'approve',
            'request_revision' => 'revision',
            'reject' => 'reject',
            'resolve' => 'resolve',
            'escalate' => 'escalate',
            'remind' => 'remind',
            'reassign' => 'reassign',
            'raise_priority' => 'raise',
            'remind_rating' => 'rating_remind',
            'return' => 'returned',
            'sync' => 'sync',
            'login' => 'login',
            'start' => 'start',
            default => null,
        };

        if ($key === null) {
            return $action;
        }

        return $key === 'edit' ? __('admin.audit.edit') : __('admin.audit.action.'.$key);
    }
}

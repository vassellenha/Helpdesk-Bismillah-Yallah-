<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Support\DummyData;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'notifications' => DummyData::notifications(),
            'auditTrail' => AuditTrail::with('actor')->latest('created_at')->take(4)->get()
                ->map(fn ($a) => [
                    'waktu' => $a->created_at->format('d M Y, H:i'),
                    'pengguna' => $a->actor->name,
                    'aktivitas' => $a->description,
                    'modul' => match ($a->module) {
                        'service_catalog' => 'Service Catalog',
                        'sla_configuration' => 'Konfigurasi SLA',
                        'user_role_management' => 'User & Role Management',
                        default => $a->module,
                    },
                ]),
            'auditLogToday' => AuditTrail::whereDate('created_at', today())->count(),
            'slaPolicyActiveCount' => SlaPolicy::active()->count(),
            'categoryDistribution' => DummyData::ticketCategoryDistribution(),
            'slaStatus' => DummyData::slaStatus(),
            'slaTrend' => DummyData::slaTrendByPriority(),
            'avgResolution' => DummyData::avgResolutionByCategory(),
            'ticketTrend' => DummyData::ticketTrendByCategory(),
            'topServiceCatalog' => DummyData::topServiceCatalog(),
            'totalUsers' => User::count(),
            'activeRoles' => Role::where('status', 'active')->count(),
        ]);
    }

    public function placeholder(string $title): View
    {
        return view('admin.placeholder', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'notifications' => DummyData::notifications(),
            'title' => $title,
        ]);
    }
}

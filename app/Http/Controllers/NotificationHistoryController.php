<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Semua Notifikasi" untuk tiap peran.
 *
 * Lonceng hanya memuat 20 pemberitahuan terbaru supaya payload halaman tetap
 * ringan. Halaman ini yang membuat sisanya tetap terjangkau: tanpa ini,
 * pemberitahuan yang lebih tua dari 20 terakhir tidak bisa dibuka sama sekali
 * dan penanda hanya bisa dikosongkan dengan menandai semua terbaca tanpa
 * pernah dibaca. Ditemukan saat UAT test case 13.
 *
 * Satu berkas untuk lima peran: yang berbeda hanya aktor, nama peran, dan
 * rute tujuan — bukan logikanya.
 */
class NotificationHistoryController extends Controller
{
    public function requester(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::requester(),
            'requester',
            'layouts.requester',
            'requester.tickets.show',
            'requester.notifications.read',
            'requester.notifications.read-all',
        );
    }

    public function approver(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::approver(),
            'approver',
            'layouts.approver',
            'approver.tickets.show',
            'approver.notifications.read',
            'approver.notifications.read-all',
        );
    }

    public function support(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::support(),
            'support',
            'layouts.support',
            'support.tickets.show',
            'support.notifications.read',
            'support.notifications.read-all',
        );
    }

    public function supportBpo(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::supportBpo(),
            'support-bpo',
            'layouts.support-bpo',
            'support-bpo.tickets.show',
            'support-bpo.notifications.read',
            'support-bpo.notifications.read-all',
        );
    }

    public function teamLead(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::teamLead(),
            'team-lead',
            'layouts.team-lead',
            'dashboard.team-lead',
            'team-lead.notifications.read',
            'team-lead.notifications.read-all',
        );
    }

    public function teamLeadBpo(Request $request): View
    {
        return $this->page(
            $request,
            CurrentActor::teamLeadBpo(),
            'team-lead-bpo',
            'layouts.team-lead',
            'dashboard.team-lead-bpo',
            'team-lead-bpo.notifications.read',
            'team-lead-bpo.notifications.read-all',
        );
    }

    private function page(
        Request $request,
        User $user,
        string $role,
        string $layout,
        string $ticketRoute,
        string $readRoute,
        string $readAllRoute,
    ): View {
        $page = max(1, (int) $request->query('page', '1'));

        return view('notifications.history', [
            'role' => $role,
            'layout' => $layout,
            'currentUser' => [
                'name' => $user->name,
                'title' => trim(($user->jabatan ?? '').' · '.($user->unit ?? ''), ' ·'),
                'initials' => $this->initials($user->name),
            ],
            // Lonceng di layout tetap butuh umpan pendeknya seperti halaman lain.
            'notifications' => NotificationService::present($user, $role, 20, $ticketRoute, $readRoute),
            'history' => NotificationService::history($user, $role, $page, NotificationService::HISTORY_PER_PAGE, $ticketRoute, $readRoute),
            'markAllReadUrl' => route($readAllRoute),
        ]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}

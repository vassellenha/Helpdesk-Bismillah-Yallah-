@extends('layouts.team-lead')

@section('title', __('teamlead.titles.operational'))

@section('content')
<div
    data-react="TeamLeadWorkspace"
    data-props="{{ json_encode([
        'user' => $currentUser,
        'period' => $period,
        'escalateUrl' => $escalateUrl,
        'notifications' => $notifications,
        'dashboardUrl' => route('dashboard.team-lead'),
        'dashboardDataUrl' => $dashboardDataUrl,
        'profileUrl' => route('team-lead.profile'),
        'markAllReadUrl' => route('team-lead.notifications.read-all'),
        'metrics' => $metrics,
        'opStats' => $opStats,
        'appTrend' => $appTrend,
        'escalationRecs' => $escalationRecs,
        'categoryTree' => $categoryTree,
        'slaDonut' => $slaDonut,
        'slaByPriority' => $slaByPriority,
        'slaTopSubjects' => $slaTopSubjects,
        'categoryBreakdown' => $categoryBreakdown,
        'workload' => $workload,
        'slaWarnings' => $slaWarnings,
        'monitorRows' => $monitorRows,
        'escalations' => $escalations,
        'breachEscalations' => $breachEscalations,
        'agentOptions' => $agentOptions,
        'reminderLog' => $reminderLog,
        'ticketTrend' => $ticketTrend,
        'topApps' => $topApps,
        'topIssues' => $topIssues,
        'servicePerformance' => $servicePerformance,
        'supportStats' => $supportStats,
        'picRows' => $picRows,
        'teguranTickets' => $teguranTickets,
        'auditRows' => $auditRows,
        'reportUnits' => $reportUnits,
        'reportTypes' => $reportTypes,
        'reports' => $reports,
        'reportExportUrl' => $reportExportUrl,
        'remindUrlBase' => $remindUrlBase,
    ]) }}"
></div>
@endsection

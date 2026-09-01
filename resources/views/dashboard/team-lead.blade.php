@extends('layouts.team-lead')

@section('title', __('teamlead.titles.operational'))

@section('content')
<div
    data-react="TeamLeadWorkspace"
    data-props="{{ json_encode([
        'user' => $currentUser,
        'role' => $role,
        'roleLabel' => $roleLabel,
        'teamLabel' => $teamLabel,
        'escalationDirection' => $escalationDirection,
        'period' => $period,
        'escalateUrl' => $escalateUrl,
        'notifications' => $notifications['items'],
        'unreadCount' => $notifications['unreadCount'],
        'dashboardUrl' => $dashboardUrl,
        'dashboardDataUrl' => $dashboardDataUrl,
        'profileUrl' => $profileUrl,
        'allNotificationsUrl' => $allNotificationsUrl,
        'markAllReadUrl' => $markAllReadUrl,
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
        'monitorFilters' => $monitorFilters,
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
        'reportDefaults' => $reportDefaults,
        'reportPreviewUrl' => $reportPreviewUrl,
        'reportExportUrl' => $reportExportUrl,
        'remindUrlBase' => $remindUrlBase,
        'remindRatingUrlBase' => $remindRatingUrlBase,
        'ratingTeguranThreshold' => $ratingTeguranThreshold,
    ]) }}"
></div>
@endsection

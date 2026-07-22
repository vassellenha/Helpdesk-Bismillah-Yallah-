@extends('layouts.approver')

@section('title', 'Approval Inbox')

@section('content')
<div
    data-react="ApprovalInbox"
    data-props="{{ json_encode([
        'metrics' => $metrics,
        'priorityDistribution' => $priorityDistribution,
        'priorityTotal' => $priorityTotal,
        'priorityHighlight' => $priorityHighlight,
        'decisionTrend' => $decisionTrend,
        'pending' => $pending,
    ]) }}"
></div>
@endsection

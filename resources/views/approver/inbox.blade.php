@extends('layouts.approver')

@section('title', __('approver.inbox.title'))

@section('content')
<div
    data-react="ApprovalInbox"
    data-props="{{ json_encode([
        'metrics' => $metrics,
        'priorityDistribution' => $priorityDistribution,
        'priorityTotal' => $priorityTotal,
        'priorityHighlight' => $priorityHighlight,
        'decisionTrend' => $decisionTrend,
        'periods' => $periods,
        'pending' => $pending,
    ]) }}"
></div>
@endsection

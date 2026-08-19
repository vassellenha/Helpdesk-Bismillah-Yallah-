@extends('layouts.requester')

@section('title', 'Dashboard')

@section('content')
<div
    data-react="RequesterDashboard"
    data-props="{{ json_encode([
        'user' => $currentUser,
        'stats' => $stats,
        'chart' => $chart,
        'periods' => $periods,
        'slaDonut' => $slaDonut,
        'slaRows' => $slaRows,
        'catalogUrl' => route('catalog.tree'),
        'approversUrl' => route('approvers.index'),
        'submitUrl' => route('tickets.store'),
        'ticketsUrl' => route('requester.tickets'),
        'evaDraft' => $evaDraft,
    ]) }}"
></div>
@endsection

@extends('layouts.support')

@section('title', 'Dashboard')

@section('content')
<div
    data-react="SupportDashboard"
    data-props="{{ json_encode([
        'stats' => $stats,
        'periods' => $periods,
        'queue' => $queue,
    ]) }}"
></div>
@endsection

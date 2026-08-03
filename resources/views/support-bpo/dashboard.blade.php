@extends('layouts.support-bpo')

@section('title', __('support.dashboard.title'))

@section('content')
<div
    data-react="SupportDashboard"
    data-props="{{ json_encode([
        'stats' => $stats,
        'periods' => $periods,
        'queue' => $queue,
        'myRating' => $myRating,
    ]) }}"
></div>
@endsection

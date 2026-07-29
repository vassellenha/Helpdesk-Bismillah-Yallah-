@extends('layouts.eva')

@section('title', 'Ticket Recommendation')

@section('content')
<div data-react="EvaTicketRecommendation" data-props="{{ json_encode([
    'rows' => $rows,
    'gaps' => $gaps,
    'stats' => $stats,
    'thresholds' => $thresholds,
    'endpoints' => $endpoints,
    'links' => $links,
]) }}"></div>
@endsection

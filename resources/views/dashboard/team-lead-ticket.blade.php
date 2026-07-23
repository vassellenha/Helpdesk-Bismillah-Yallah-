@extends('layouts.team-lead')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="TeamLeadTicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'timeline' => $timeline,
        'comments' => $comments,
        'agentOptions' => $agentOptions,
        'remindUrlBase' => $remindUrlBase,
        'dashboardUrl' => $dashboardUrl,
    ]) }}"
></div>
@endsection

@extends('layouts.support-bpo')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="SupportTicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'comments' => $comments,
        'timeline' => $timeline,
        'flow' => $flow,
        'dataUrl' => $dataUrl,
        'commentsUrl' => $commentsUrl,
        'resolveUrl' => $resolveUrl,
        'escalateUrl' => $escalateUrl,
        'returnUrl' => $returnUrl,
        'ticketsUrl' => $ticketsUrl,
    ]) }}"
></div>
@endsection

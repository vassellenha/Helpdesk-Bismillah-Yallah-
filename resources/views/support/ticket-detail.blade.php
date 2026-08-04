@extends('layouts.support')

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
        'returnUrl' => $returnUrl,
        'ticketsUrl' => $ticketsUrl,
    ]) }}"
></div>
@endsection

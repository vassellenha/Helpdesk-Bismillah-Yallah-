@extends('layouts.support')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="SupportTicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'comments' => $comments,
        'timeline' => $timeline,
        'commentsUrl' => $commentsUrl,
        'resolveUrl' => $resolveUrl,
        'ticketsUrl' => $ticketsUrl,
    ]) }}"
></div>
@endsection

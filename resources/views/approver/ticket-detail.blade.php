@extends('layouts.approver')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="ApprovalTicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'comments' => $comments,
        'timeline' => $timeline,
        'commentsUrl' => $commentsUrl,
        'decideUrl' => $decideUrl,
        'ticketsUrl' => $ticketsUrl,
    ]) }}"
></div>
@endsection

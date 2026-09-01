@extends('layouts.approver')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="ApprovalTicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'viewer' => $viewer,
        'comments' => $comments,
        'timeline' => $timeline,
        'flow' => $flow,
        'dataUrl' => $dataUrl,
        'commentsUrl' => $commentsUrl,
        'decideUrl' => $decideUrl,
        'ticketsUrl' => $ticketsUrl,
    ]) }}"
></div>
@endsection

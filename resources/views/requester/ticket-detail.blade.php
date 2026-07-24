@extends('layouts.requester')

@section('title', $ticket['id'])

@section('content')
<div
    data-react="TicketDetail"
    data-props="{{ json_encode([
        'ticket' => $ticket,
        'comments' => $comments,
        'timeline' => $timeline,
        'commentsUrl' => $commentsUrl,
        'reopenUrl' => $reopenUrl,
        'closeUrl' => $closeUrl,
        'attachmentUrl' => $attachmentUrl,
        'ticketsUrl' => $ticketsUrl,
        'editUrl' => $editUrl,
        'catalogUrl' => $catalogUrl,
        'approversUrl' => $approversUrl,
    ]) }}"
></div>
@endsection

@extends('layouts.requester')

@section('title', 'My Tickets')

@section('content')
<div
    data-react="MyTicketsPage"
    data-props="{{ json_encode([
        'tickets' => $tickets,
        'counts' => $counts,
        'catalogUrl' => route('catalog.tree'),
        'approversUrl' => route('approvers.index'),
        'submitUrl' => route('tickets.store'),
    ]) }}"
></div>
@endsection

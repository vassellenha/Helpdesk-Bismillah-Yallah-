@extends('layouts.approver')

@section('title', 'My Tickets')

@section('content')
<div
    data-react="ApprovalHistoryPage"
    data-props="{{ json_encode([
        'counts' => $counts,
        'rows' => $rows,
    ]) }}"
></div>
@endsection

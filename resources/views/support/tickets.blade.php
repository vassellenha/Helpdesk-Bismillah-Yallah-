@extends('layouts.support')

@section('title', 'My Tickets')

@section('content')
<div
    data-react="SupportHistoryPage"
    data-props="{{ json_encode([
        'counts' => $counts,
        'rows' => $rows,
    ]) }}"
></div>
@endsection

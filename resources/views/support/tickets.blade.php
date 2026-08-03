@extends('layouts.support')

@section('title', __('support.history.title'))

@section('content')
<div
    data-react="SupportHistoryPage"
    data-props="{{ json_encode([
        'counts' => $counts,
        'rows' => $rows,
    ]) }}"
></div>
@endsection

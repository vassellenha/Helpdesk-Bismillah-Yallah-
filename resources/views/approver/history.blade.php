@extends('layouts.approver')

@section('title', __('approver.history.title'))

@section('content')
<div
    data-react="ApprovalHistoryPage"
    data-props="{{ json_encode([
        'counts' => $counts,
        'rows' => $rows,
    ]) }}"
></div>
@endsection

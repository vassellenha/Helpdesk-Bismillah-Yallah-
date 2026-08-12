@extends('layouts.admin')

@section('title', __('admin.audit.title'))

@section('content')
<div
    data-react="AuditTrailConsole"
    data-props="{{ json_encode([
        'logs' => $logs,
        'logsMeta' => $logsMeta,
        'listUrl' => $listUrl,
        'administrators' => $administrators,
    ]) }}"
></div>
@endsection

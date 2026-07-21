@extends('layouts.admin')

@section('title', 'Audit Trail Viewer')

@section('content')
<div
    data-react="AuditTrailConsole"
    data-props="{{ json_encode([
        'logs' => $logs,
        'administrators' => $administrators,
    ]) }}"
></div>
@endsection

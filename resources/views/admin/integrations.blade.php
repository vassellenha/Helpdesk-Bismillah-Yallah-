@extends('layouts.admin')

@section('title', __('admin.integration.title'))

@section('content')
<div
    data-react="IntegrationConsole"
    data-props="{{ json_encode([
        'integration' => $integration,
        'history' => $history,
        'syncUrl' => $syncUrl,
        'testUrl' => $testUrl,
    ]) }}"
></div>
@endsection

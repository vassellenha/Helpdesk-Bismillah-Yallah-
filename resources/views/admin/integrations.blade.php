@extends('layouts.admin')

@section('title', 'Integrasi')

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

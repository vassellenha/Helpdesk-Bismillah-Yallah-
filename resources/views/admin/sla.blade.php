@extends('layouts.admin')

@section('title', 'Konfigurasi SLA')

@section('content')
<nav class="mb-3 text-sm text-gray-400">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-600">Dashboard Administrator</a>
    <span class="mx-1">›</span>
    <span class="font-medium text-gray-600">Konfigurasi SLA</span>
</nav>

<div data-react="SlaPolicyConsole" data-props="{{ json_encode(['policies' => $policies, 'ticketSlaBreakdown' => $ticketSlaBreakdown]) }}"></div>
@endsection

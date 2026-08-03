@extends('layouts.admin')

@section('title', __('admin.sla.title'))

@section('content')
<nav class="mb-3 text-sm text-gray-400 dark:text-ink-3">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-600 dark:hover:text-ink-2">Dashboard Administrator</a>
    <span class="mx-1">›</span>
    <span class="font-medium text-gray-600 dark:text-ink-2">Konfigurasi SLA</span>
</nav>

<div data-react="SlaPolicyConsole" data-props="{{ json_encode(['policies' => $policies, 'ticketSlaBreakdown' => $ticketSlaBreakdown]) }}"></div>
@endsection

@extends('layouts.admin')

@section('title', 'Ticket Management')

@section('content')
<nav class="mb-3 text-sm text-gray-400 dark:text-ink-3">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-600 dark:hover:text-ink-2">Dashboard Administrator</a>
    <span class="mx-1">›</span>
    <span class="font-medium text-gray-600 dark:text-ink-2">Ticket Management</span>
</nav>

<div
    data-react="TicketManagementConsole"
    data-props="{{ json_encode([
        'tickets' => $tickets,
        'stats' => $stats,
        'filterOptions' => $filterOptions,
        'exportUrl' => $exportUrl,
    ]) }}"
></div>
@endsection

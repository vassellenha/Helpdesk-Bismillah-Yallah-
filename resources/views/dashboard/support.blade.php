@extends('layouts.app')

@section('title', 'Support Workspace')

@section('content')
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Tiket Masuk</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ count($tickets) }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Open</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($tickets)->where('status', 'Open')->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">In Progress</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($tickets)->where('status', 'In Progress')->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Agent Aktif</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ count($agents) }}</p>
    </div>
</div>

<div class="space-y-6">
    <div>
        <h2 class="mb-3 text-sm font-semibold text-gray-700">Antrian Tiket</h2>
        <div data-react="TicketWorkspace" data-props="{{ json_encode(['tickets' => $tickets, 'categories' => $categories, 'showAssignee' => true]) }}"></div>
    </div>
    <div>
        <div data-react="AgentsPanel" data-props="{{ json_encode(['agents' => $agents]) }}"></div>
    </div>
</div>
@endsection

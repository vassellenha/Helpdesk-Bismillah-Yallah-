@extends('layouts.app')

@section('title', 'Dashboard Requester')

@section('content')
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Total Tiket Saya</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ count($tickets) }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Sedang Diproses</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($tickets)->whereIn('status', ['Open', 'In Progress'])->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Selesai</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($tickets)->whereIn('status', ['Resolved', 'Closed'])->count() }}</p>
    </div>
</div>

<div class="mb-4 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-700">Tiket Saya</h2>
    <div data-react="NewTicketModal" data-props="{{ json_encode(['categories' => $categories]) }}"></div>
</div>

<div data-react="TicketWorkspace" data-props="{{ json_encode(['tickets' => $tickets, 'categories' => $categories, 'showAssignee' => true]) }}"></div>
@endsection

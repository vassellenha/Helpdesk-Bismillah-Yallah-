@extends('layouts.app')

@section('title', 'Approval Workspace')

@section('content')
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Menunggu Approval</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($queue)->where('status', 'Pending')->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Disetujui Bulan Ini</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($queue)->where('status', 'Approved')->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium text-gray-400">Ditolak Bulan Ini</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ collect($queue)->where('status', 'Rejected')->count() }}</p>
    </div>
</div>

<div data-react="ApprovalQueue" data-props="{{ json_encode(['queue' => $queue]) }}"></div>
@endsection

@extends('layouts.admin')

@section('title', __('admin.dashboard.title'))

@php
    $stats = [
        ['label' => 'TOTAL USER', 'value' => $totalUsers, 'icon' => 'users', 'bg' => 'bg-blue-50 dark:bg-accent-soft', 'color' => 'text-blue-600 dark:text-accent-text'],
        ['label' => 'ROLE AKTIF', 'value' => $activeRoles, 'icon' => 'check', 'bg' => 'bg-emerald-50 dark:bg-ok-soft', 'color' => 'text-emerald-600 dark:text-ok-text'],
        ['label' => 'SERVICE CATALOG', 'value' => $serviceCatalogCount, 'icon' => 'catalog', 'bg' => 'bg-amber-50 dark:bg-warn-soft', 'color' => 'text-amber-600 dark:text-warn-text'],
        ['label' => 'SLA POLICY AKTIF', 'value' => $slaPolicyActiveCount, 'icon' => 'dot', 'bg' => 'bg-red-50 dark:bg-bad-soft', 'color' => 'text-red-600 dark:text-bad-text'],
        ['label' => 'AUDIT LOG HARI INI', 'value' => $auditLogToday, 'icon' => 'doc', 'bg' => 'bg-gray-100 dark:bg-panel-3', 'color' => 'text-gray-600 dark:text-ink-2'],
    ];
@endphp

@section('content')
<h1 class="text-3xl font-extrabold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.title')</h1>

<div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ($stats as $s)
        <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $s['bg'] }} {{ $s['color'] }}">
                <span class="h-2.5 w-2.5 rounded-full bg-current"></span>
            </span>
            <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-ink-1">{{ number_format($s['value'], 0, ',', '.') }}</p>
            <p class="text-xs font-medium text-gray-400 dark:text-ink-3">{{ $s['label'] }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm lg:col-span-2">
        <div class="border-b border-gray-100 dark:border-edge p-5">
            <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.recent_audit')</h2>
            <p class="text-sm text-gray-400 dark:text-ink-3">@lang('admin.dashboard.recent_audit_hint')</p>
        </div>
        <div class="w-full overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                    <th class="px-5 py-3">@lang('admin.common.time')</th>
                    <th class="px-5 py-3">@lang('admin.common.user')</th>
                    <th class="px-5 py-3">@lang('admin.common.activity')</th>
                    <th class="px-5 py-3">@lang('admin.common.module')</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-transparent">
                @foreach ($auditTrail as $log)
                    <tr class="dark:even:bg-white/[0.03]">
                        <td class="px-5 py-3 text-gray-500 dark:text-ink-2">{{ $log['waktu'] }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-ink-1">{{ $log['pengguna'] }}</td>
                        <td class="px-5 py-3 text-gray-600 dark:text-ink-2">{{ $log['aktivitas'] }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-ink-2">{{ $log['modul'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="border-t border-gray-100 dark:border-edge p-4 text-center">
            <a href="{{ route('admin.audit-trail') }}" class="text-sm font-semibold text-blue-700 dark:text-accent-text hover:text-blue-800">@lang('admin.dashboard.view_all_audit')</a>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.category_distribution')</h2>
            <div class="mt-4" data-react="TicketCategoryDonut" data-props="{{ json_encode(['data' => $categoryDistribution, 'total' => $totalTicketCount]) }}"></div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.sla_status')</h2>
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                @foreach ($slaStatus as $s)
                    <div style="width: {{ $s['percent'] }}%; background-color: {{ $s['color'] }}"></div>
                @endforeach
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($slaStatus as $s)
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-gray-700 dark:text-ink-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $s['color'] }}"></span>
                            {{ $s['label'] }}
                        </span>
                        <span class="font-semibold" style="color: {{ $s['color'] }}">{{ $s['percent'] }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.sla_trend')</h2>
        <p class="mb-3 text-sm text-gray-400 dark:text-ink-3">Analisis pelanggaran SLA berdasarkan prioritas tiket dalam 6 bulan terakhir.</p>
        <div data-react="SlaTrendChart" data-props="{{ json_encode(['data' => $slaTrend]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-xs text-amber-800 dark:text-warn-text">
            {{ $slaTrendInsight }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.avg_resolution')</h2>
        <p class="mb-3 text-sm text-gray-400 dark:text-ink-3">Membandingkan rata-rata waktu penyelesaian untuk melihat kategori layanan yang paling lambat ditangani.</p>
        <div data-react="AvgResolutionBar" data-props="{{ json_encode(['data' => $avgResolution]) }}"></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.ticket_trend')</h2>
        <p class="mb-3 text-sm text-gray-400 dark:text-ink-3">Perbandingan jumlah tiket Incident, Service Request, dan Access Request dalam 6 bulan terakhir.</p>
        <div data-react="TicketTrendChart" data-props="{{ json_encode(['data' => $ticketTrend]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-xs text-amber-800 dark:text-warn-text">
            {{ $ticketTrendInsight }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-ink-1">@lang('admin.dashboard.top_catalog')</h2>
        <p class="mb-3 text-sm text-gray-400 dark:text-ink-3">Analisis layanan yang paling sering diajukan pengguna pada bulan berjalan.</p>
        <div data-react="TopServiceBarChart" data-props="{{ json_encode(['data' => $topServiceCatalog]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-xs text-amber-800 dark:text-warn-text">
            {!! $topServiceInsight !!}
        </p>
    </div>
</div>
@endsection

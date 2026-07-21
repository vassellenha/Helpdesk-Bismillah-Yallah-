@extends('layouts.admin')

@section('title', 'Dashboard Administrator')

@php
    $stats = [
        ['label' => 'TOTAL USER', 'value' => $totalUsers, 'icon' => 'users', 'bg' => 'bg-blue-50', 'color' => 'text-blue-600'],
        ['label' => 'ROLE AKTIF', 'value' => $activeRoles, 'icon' => 'check', 'bg' => 'bg-emerald-50', 'color' => 'text-emerald-600'],
        ['label' => 'SERVICE CATALOG', 'value' => $serviceCatalogCount, 'icon' => 'catalog', 'bg' => 'bg-amber-50', 'color' => 'text-amber-600'],
        ['label' => 'APPROVAL MATRIX', 'value' => $approvalMatrixCount, 'icon' => 'gear', 'bg' => 'bg-blue-50', 'color' => 'text-blue-600'],
        ['label' => 'SLA POLICY AKTIF', 'value' => $slaPolicyActiveCount, 'icon' => 'dot', 'bg' => 'bg-red-50', 'color' => 'text-red-600'],
        ['label' => 'AUDIT LOG HARI INI', 'value' => $auditLogToday, 'icon' => 'doc', 'bg' => 'bg-gray-100', 'color' => 'text-gray-600'],
    ];
@endphp

@section('content')
<h1 class="text-3xl font-extrabold text-gray-900">Dashboard Administrator</h1>

<div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ($stats as $s)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $s['bg'] }} {{ $s['color'] }}">
                <span class="h-2.5 w-2.5 rounded-full bg-current"></span>
            </span>
            <p class="mt-3 text-2xl font-bold text-gray-900">{{ number_format($s['value'], 0, ',', '.') }}</p>
            <p class="text-xs font-medium text-gray-400">{{ $s['label'] }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
        <div class="border-b border-gray-100 p-5">
            <h2 class="text-base font-bold text-gray-900">Aktivitas Audit Terbaru</h2>
            <p class="text-sm text-gray-400">Perubahan konfigurasi terkini oleh administrator</p>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3">Waktu</th>
                    <th class="px-5 py-3">Pengguna</th>
                    <th class="px-5 py-3">Aktivitas</th>
                    <th class="px-5 py-3">Modul</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($auditTrail as $log)
                    <tr>
                        <td class="px-5 py-3 text-gray-500">{{ $log['waktu'] }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $log['pengguna'] }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $log['aktivitas'] }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{{ $log['modul'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-gray-100 p-4 text-center">
            <a href="{{ route('admin.audit-trail') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Lihat Semua Audit Trail →</a>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-gray-900">Distribusi Tiket per Kategori</h2>
            <div class="mt-4" data-react="TicketCategoryDonut" data-props="{{ json_encode(['data' => $categoryDistribution, 'total' => $totalTicketCount]) }}"></div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-gray-900">Status SLA</h2>
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-gray-100">
                @foreach ($slaStatus as $s)
                    <div style="width: {{ $s['percent'] }}%; background-color: {{ $s['color'] }}"></div>
                @endforeach
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($slaStatus as $s)
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-gray-700">
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
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900">Tren Pelanggaran SLA per Prioritas</h2>
        <p class="mb-3 text-sm text-gray-400">Analisis pelanggaran SLA berdasarkan prioritas tiket dalam 6 bulan terakhir.</p>
        <div data-react="SlaTrendChart" data-props="{{ json_encode(['data' => $slaTrend]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
            {{ $slaTrendInsight }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900">Waktu Rata-Rata Penyelesaian per Kategori Layanan</h2>
        <p class="mb-3 text-sm text-gray-400">Membandingkan rata-rata waktu penyelesaian untuk melihat kategori layanan yang paling lambat ditangani.</p>
        <div data-react="AvgResolutionBar" data-props="{{ json_encode(['data' => $avgResolution]) }}"></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900">Tren Tiket Berdasarkan Kategori</h2>
        <p class="mb-3 text-sm text-gray-400">Perbandingan jumlah tiket Incident, Service Request, dan Access Request dalam 6 bulan terakhir.</p>
        <div data-react="TicketTrendChart" data-props="{{ json_encode(['data' => $ticketTrend]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
            {{ $ticketTrendInsight }}
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-bold text-gray-900">Top 5 Service Catalog Paling Banyak Digunakan</h2>
        <p class="mb-3 text-sm text-gray-400">Analisis layanan yang paling sering diajukan pengguna pada bulan berjalan.</p>
        <div data-react="TopServiceBarChart" data-props="{{ json_encode(['data' => $topServiceCatalog]) }}"></div>
        <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
            {!! $topServiceInsight !!}
        </p>
    </div>
</div>
@endsection

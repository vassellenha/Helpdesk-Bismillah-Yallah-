<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #171b24; }
        .head { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { color: #2563eb; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        h1 { margin: 4px 0 2px; font-size: 18px; }
        .meta { font-size: 10px; color: #6c7486; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { background: #f3f4f9; text-align: left; padding: 7px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #6c7486; border-bottom: 1px solid #e3e6ee; }
        td { padding: 7px 8px; border-bottom: 1px solid #edeff5; }
        .r { text-align: right; }
        tr:nth-child(even) td { background: #fafbfd; }
        .foot { margin-top: 18px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">ADHI HELPDESK · ENTERPRISE ITSM</div>
        <h1>{{ $report['title'] }}</h1>
        <div class="meta">Periode: {{ $periodLabel }} &nbsp;·&nbsp; Unit: {{ $unitLabel }} &nbsp;·&nbsp; Dibuat: {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $c)
                    <th class="{{ $c['align'] === 'right' ? 'r' : '' }}">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td class="{{ ($report['columns'][$i]['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center;color:#9ca3af;padding:24px;">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Laporan dibuat otomatis oleh Team Lead Workspace · Adhi Helpdesk</div>
</body>
</html>

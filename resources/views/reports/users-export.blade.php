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
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th { background: #f3f4f9; text-align: left; padding: 6px 7px; font-size: 8px; text-transform: uppercase; letter-spacing: .4px; color: #6c7486; border-bottom: 1px solid #e3e6ee; }
        td { padding: 6px 7px; border-bottom: 1px solid #edeff5; }
        tr:nth-child(even) td { background: #fafbfd; }
        .foot { margin-top: 18px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">ADHI HELPDESK · ENTERPRISE ITSM</div>
        <h1>Ekspor Pengguna</h1>
        <div class="meta">Filter: {{ $filterLabel }} &nbsp;·&nbsp; Total: {{ $users->count() }} pengguna &nbsp;·&nbsp; Dibuat: {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>NPP</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Unit Kerja</th>
                <th>Jabatan</th>
                <th>Role</th>
                <th>Status</th>
                <th>Terakhir Login</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->nip ?: '-' }}</td>
                    <td>{{ $u->email ?: '-' }}</td>
                    <td>{{ $u->phone ?: '-' }}</td>
                    <td>{{ $u->unit ?: '-' }}</td>
                    <td>{{ $u->jabatan ?: '-' }}</td>
                    <td>{{ $u->roles->pluck('name')->implode(', ') ?: '-' }}</td>
                    <td>{{ $u->isActive() ? 'Aktif' : 'Nonaktif' }}</td>
                    <td>{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:24px;">Tidak ada pengguna yang cocok dengan filter.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">Laporan dibuat otomatis oleh User &amp; Role Management · Adhi Helpdesk</div>
</body>
</html>

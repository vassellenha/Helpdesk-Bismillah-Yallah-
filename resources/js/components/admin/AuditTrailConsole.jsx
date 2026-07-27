import { useMemo, useState } from 'react';

const ALL = 'Semua';
const PAGE_SIZE = 15;

const MODULE_LABELS = {
    service_catalog: 'Service Catalog',
    sla_configuration: 'Konfigurasi SLA',
    user_role_management: 'User & Role Management',
    ticket_approval: 'Approval Tiket',
    ticket_support: 'Penanganan Support',
    team_lead: 'Team Lead',
};

const ACTION_LABELS = {
    create: 'Tambah',
    update: 'Edit',
    activate: 'Aktifkan',
    deactivate: 'Nonaktifkan',
    assign_support: 'Ubah Support',
    change_level: 'Ubah Level',
    change_role: 'Ubah Role',
    approve: 'Setujui',
    request_revision: 'Minta Perbaikan',
    reject: 'Tolak',
    resolve: 'Tutup Layanan',
    escalate: 'Eskalasi',
    remind: 'Kirim Teguran',
    reassign: 'Alihkan Tiket',
    raise_priority: 'Naikkan Prioritas',
};

export default function AuditTrailConsole({ logs, administrators }) {
    const [search, setSearch] = useState('');
    const [moduleFilter, setModuleFilter] = useState(ALL);
    const [actionFilter, setActionFilter] = useState(ALL);
    const [adminFilter, setAdminFilter] = useState(ALL);
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [page, setPage] = useState(1);
    const [detailLog, setDetailLog] = useState(null);

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        const fromTs = dateFrom ? new Date(dateFrom + 'T00:00:00').getTime() / 1000 : null;
        const toTs = dateTo ? new Date(dateTo + 'T23:59:59').getTime() / 1000 : null;

        return logs.filter((l) => {
            const matchesSearch =
                q === '' ||
                l.target_name.toLowerCase().includes(q) ||
                l.administrator.toLowerCase().includes(q) ||
                l.description.toLowerCase().includes(q);
            const matchesModule = moduleFilter === ALL || l.module === moduleFilter;
            const matchesAction = actionFilter === ALL || l.action === actionFilter;
            const matchesAdmin = adminFilter === ALL || l.administrator === adminFilter;
            const matchesFrom = fromTs === null || l.timestamp >= fromTs;
            const matchesTo = toTs === null || l.timestamp <= toTs;
            return matchesSearch && matchesModule && matchesAction && matchesAdmin && matchesFrom && matchesTo;
        });
    }, [logs, search, moduleFilter, actionFilter, adminFilter, dateFrom, dateTo]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const page_ = Math.min(page, totalPages);
    const paginated = filtered.slice((page_ - 1) * PAGE_SIZE, page_ * PAGE_SIZE);

    function resetFilters() {
        setSearch('');
        setModuleFilter(ALL);
        setActionFilter(ALL);
        setAdminFilter(ALL);
        setDateFrom('');
        setDateTo('');
        setPage(1);
    }

    function updateFilter(setter) {
        return (value) => {
            setter(value);
            setPage(1);
        };
    }

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-3xl font-extrabold text-gray-900">Audit Trail Viewer</h1>
                <p className="mt-1 text-sm text-gray-500">Riwayat aktivitas seluruh pengguna — Service Catalog, Konfigurasi SLA, User &amp; Role Management, Approval, dan Penanganan Tiket.</p>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => updateFilter(setSearch)(e.target.value)}
                        placeholder="Cari target, pengguna, atau deskripsi"
                        className="w-full max-w-sm rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <select value={moduleFilter} onChange={(e) => updateFilter(setModuleFilter)(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option value={ALL}>Semua Modul</option>
                            {Object.entries(MODULE_LABELS).map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                        </select>
                        <select value={actionFilter} onChange={(e) => updateFilter(setActionFilter)(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option value={ALL}>Semua Aktivitas</option>
                            {Object.entries(ACTION_LABELS).map(([v, label]) => <option key={v} value={v}>{label}</option>)}
                        </select>
                        <select value={adminFilter} onChange={(e) => updateFilter(setAdminFilter)(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option value={ALL}>Semua Pengguna</option>
                            {administrators.map((a) => <option key={a}>{a}</option>)}
                        </select>
                        <input type="date" value={dateFrom} onChange={(e) => updateFilter(setDateFrom)(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none" />
                        <span className="text-sm text-gray-400">—</span>
                        <input type="date" value={dateTo} onChange={(e) => updateFilter(setDateTo)(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none" />
                        <button onClick={resetFilters} className="text-sm font-medium text-blue-700 hover:text-blue-800">Reset Filter</button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400">Menampilkan {filtered.length === 0 ? 0 : (page_ - 1) * PAGE_SIZE + 1}–{Math.min(page_ * PAGE_SIZE, filtered.length)} dari {filtered.length} aktivitas</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <th className="px-4 py-3">Waktu</th>
                                <th className="px-4 py-3">Pengguna</th>
                                <th className="px-4 py-3">Modul</th>
                                <th className="px-4 py-3">Aktivitas</th>
                                <th className="px-4 py-3">Target</th>
                                <th className="px-4 py-3 text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {paginated.map((l) => (
                                <tr key={l.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-gray-500">{l.waktu}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900">{l.administrator}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{l.module_label}</span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700">{l.description}</td>
                                    <td className="px-4 py-3 text-gray-600">{l.target_name}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={() => setDetailLog(l)} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50">
                                            Lihat Detail
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {paginated.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-14 text-center text-sm text-gray-400">
                                        {logs.length === 0 ? 'Belum ada aktivitas.' : 'Tidak ada aktivitas yang cocok dengan filter.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {filtered.length > 0 && (
                    <div className="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={page_ === 1}
                            className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            ← Sebelumnya
                        </button>
                        <span className="text-sm text-gray-500">Halaman {page_} dari {totalPages}</span>
                        <button
                            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                            disabled={page_ === totalPages}
                            className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Berikutnya →
                        </button>
                    </div>
                )}
            </div>

            {detailLog && <DetailModal log={detailLog} onClose={() => setDetailLog(null)} />}
        </div>
    );
}

function humanizeKey(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function humanizeValue(value) {
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
}

function DetailModal({ log, onClose }) {
    const keys = Array.from(new Set([...(Object.keys(log.old_value ?? {})), ...(Object.keys(log.new_value ?? {}))]));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <p className="text-xs font-medium text-gray-400">{log.module_label} · {log.action_label}</p>
                        <h2 className="text-lg font-bold text-gray-900">{log.target_name}</h2>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">✕</button>
                </div>

                <div className="px-6 py-4">
                    <p className="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{log.description}</p>
                </div>

                {keys.length > 0 ? (
                    <div className="max-h-80 overflow-y-auto px-6 pb-6">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead>
                                <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    <th className="py-2">Field</th>
                                    <th className="py-2">Sebelum</th>
                                    <th className="py-2">Sesudah</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {keys.map((key) => (
                                    <tr key={key}>
                                        <td className="py-2 pr-3 font-medium text-gray-700">{humanizeKey(key)}</td>
                                        <td className="py-2 pr-3 text-gray-500">{humanizeValue(log.old_value?.[key])}</td>
                                        <td className="py-2 font-medium text-gray-900">{humanizeValue(log.new_value?.[key])}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="px-6 pb-6 text-sm text-gray-400">Tidak ada detail nilai tambahan untuk aktivitas ini.</p>
                )}

                <div className="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">Tutup</button>
                </div>
            </div>
        </div>
    );
}

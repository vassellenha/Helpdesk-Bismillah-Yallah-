import { useMemo, useState } from 'react';
import TicketCategoryDonut from '../charts/TicketCategoryDonut';
import SlaComplianceDonut from '../charts/SlaComplianceDonut';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import SelectMenu from '../SelectMenu';

const CATEGORY_OPTIONS = [
    { value: 'Semua', label: 'Semua Kategori' },
    { value: 'Incident', label: 'Incident' },
    { value: 'Service Request', label: 'Service Request' },
    { value: 'Access Request', label: 'Access Request' },
];

const PRIORITY_OPTIONS = [
    { value: 'Semua', label: 'Semua Prioritas' },
    { value: 'Critical', label: 'Critical' },
    { value: 'High', label: 'High' },
    { value: 'Medium', label: 'Medium' },
    { value: 'Low', label: 'Low' },
];

const PERIOD_TABS = [
    { key: 'week', label: 'Minggu' },
    { key: 'month', label: 'Bulan' },
    { key: 'year', label: 'Tahun' },
];

const COLUMNS = [
    { key: 'id', label: 'No. Tiket' },
    { key: 'subject', label: 'Subject' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'status', label: 'Status' },
    { key: 'sla', label: 'SLA Penyelesaian' },
    { key: 'requester', label: 'Requester' },
    { key: 'created', label: 'Dibuat' },
];

const PRIORITY_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };

function StatCard({ label, value, icon, iconBg, iconColor }) {
    return (
        <div className="flex flex-col gap-2.5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-400">{label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${iconBg} ${iconColor}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={icon} /></svg>
                </span>
            </div>
            <div className="text-[28px] font-extrabold leading-none text-gray-900">{value}</div>
        </div>
    );
}

function PriorityBarChart({ rows = [] }) {
    const max = Math.max(1, ...rows.map((r) => r.count));

    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <h2 className="text-[15px] font-bold text-gray-900">Tiket per Prioritas</h2>
                <span className="text-xs text-gray-400">Total {rows.reduce((s, r) => s + r.count, 0)}</span>
            </div>
            <div className="flex flex-1 items-end justify-around gap-3 pt-2">
                {rows.map((r) => (
                    <div key={r.priority} className="flex flex-1 flex-col items-center gap-2">
                        <span className="text-sm font-extrabold text-gray-900">{r.count}</span>
                        <div className="flex h-24 w-full items-end justify-center">
                            <div
                                className="w-8 rounded-t-md transition-all"
                                style={{ height: `${Math.max(6, (r.count / max) * 100)}%`, backgroundColor: r.color }}
                            />
                        </div>
                        <span className="text-[11px] font-medium text-gray-500">{r.priority}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function SupportDashboard({ stats = {}, periods = {}, queue = [] }) {
    const [period, setPeriod] = useState('month');
    const [category, setCategory] = useState('Semua');
    const [priority, setPriority] = useState('Semua');
    const [sortKey, setSortKey] = useState('created');
    const [sortDir, setSortDir] = useState('asc');

    const current = periods[period] ?? { priority: [], category: [], sla: {} };
    const categoryTotal = queue.length > 0 ? queue.length : 0;

    function toggleSort(key) {
        if (key === sortKey) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    function sortValue(row, key) {
        if (key === 'priority') return PRIORITY_RANK[row.priority] ?? 0;
        if (key === 'created') return new Date(row.createdAt).getTime();
        return (row[key] ?? '').toString().toLowerCase();
    }

    const filtered = useMemo(() => {
        const rows = queue.filter((r) => {
            if (category !== 'Semua' && r.category !== category) return false;
            if (priority !== 'Semua' && r.priority !== priority) return false;
            return true;
        });

        rows.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return rows;
    }, [queue, category, priority, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">Dashboard</h1>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Ditugaskan ke Saya"
                    value={stats.assignedToMe ?? 0}
                    icon="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z M14 5v14"
                    iconBg="bg-blue-50" iconColor="text-blue-600"
                />
                <StatCard
                    label="In Progress"
                    value={stats.inProgress ?? 0}
                    icon="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3"
                    iconBg="bg-amber-50" iconColor="text-amber-600"
                />
                <StatCard
                    label="Mendekati / Lewat SLA"
                    value={stats.slaAtRisk ?? 0}
                    icon="M12 9v4 M12 17h.01 M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                    iconBg="bg-red-50" iconColor="text-red-600"
                />
                <StatCard
                    label="Selesai"
                    value={stats.resolvedThisMonth ?? 0}
                    icon="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9"
                    iconBg="bg-emerald-50" iconColor="text-emerald-600"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[15px] font-bold text-gray-900">Ringkasan · {PERIOD_TABS.find((p) => p.key === period)?.label} Ini</h2>
                <div className="flex gap-1.5 rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm">
                    {PERIOD_TABS.map((p) => (
                        <button
                            key={p.key}
                            onClick={() => setPeriod(p.key)}
                            className={`rounded-lg px-3.5 py-2 text-[13px] font-semibold ${period === p.key ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <PriorityBarChart rows={current.priority} />
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-[15px] font-bold text-gray-900">Distribusi Kategori</h2>
                    <TicketCategoryDonut data={current.category} total={categoryTotal} />
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <SlaComplianceDonut donut={current.sla} />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
                    <h2 className="text-[15px] font-bold text-gray-900">Menunggu Bantuan Anda</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Kategori</span>
                            <SelectMenu value={category} onChange={setCategory} options={CATEGORY_OPTIONS} />
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Prioritas</span>
                            <SelectMenu value={priority} onChange={setPriority} options={PRIORITY_OPTIONS} />
                        </label>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-[900px] w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                {COLUMNS.map((col) => (
                                    <th key={col.key} className="px-4 py-3.5 text-left first:pl-6 last:pr-6">
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(col.key)}
                                            className="flex items-center gap-1 uppercase tracking-wide text-gray-400 hover:text-gray-700"
                                        >
                                            {col.label}
                                            <span aria-hidden="true" className={sortKey === col.key ? 'text-gray-600' : 'text-gray-300'}>
                                                {sortKey === col.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}
                                            </span>
                                        </button>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={() => (window.location.href = row.href)}
                                    className="cursor-pointer border-b border-gray-50 last:border-0 hover:bg-blue-50/40"
                                >
                                    <td className="px-4 py-4 pl-6 font-bold text-blue-600">{row.id}</td>
                                    <td className="px-4 py-4">
                                        <p className="max-w-[220px] truncate font-semibold text-gray-900">{row.subject}</p>
                                    </td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                    <td className="px-4 py-4 text-gray-700">{row.sla}</td>
                                    <td className="px-4 py-4 text-gray-700">{row.requester}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400">{row.created}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400">Tidak ada tiket yang cocok dengan filter ini.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400">Menampilkan {filtered.length} dari {queue.length} tiket</span>
                </div>
            </div>
        </div>
    );
}

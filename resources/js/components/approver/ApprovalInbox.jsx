import { useMemo, useState } from 'react';
import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid } from 'recharts';
import { PriorityBadge } from '../StatusBadge';

const PRIORITY_COLOR = { Critical: '#dc2626', High: '#d97706', Medium: '#2563eb', Low: '#9ca3af' };

const COLUMNS = [
    { key: 'id', label: 'No. Tiket' },
    { key: 'layanan', label: 'Layanan' },
    { key: 'subCategory', label: 'Sub Category' },
    { key: 'subject', label: 'Subject' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'requester', label: 'Requester' },
    { key: 'created', label: 'Dibuat' },
];

const PRIORITY_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };

function MetricCard({ label, value, icon, iconBg, iconColor }) {
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

function PriorityDistribution({ rows = [], total = 0, highlight = '' }) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <h2 className="text-[15px] font-bold text-gray-900">Distribusi Prioritas · Bulan Ini</h2>
                <span className="text-xs text-gray-400">Total {total} menunggu</span>
            </div>
            <div className="flex flex-col gap-3.5">
                {rows.map((r) => (
                    <div key={r.priority}>
                        <div className="mb-1 flex items-center justify-between text-[13px]">
                            <span className="font-semibold text-gray-700">{r.priority}</span>
                            <span className="font-bold text-gray-900">{r.pct}%</span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div className="h-full rounded-full" style={{ width: `${r.pct}%`, backgroundColor: PRIORITY_COLOR[r.priority] }} />
                        </div>
                    </div>
                ))}
            </div>
            <p className="border-t border-gray-100 pt-3 text-[11px] leading-relaxed text-gray-400">{highlight}</p>
        </div>
    );
}

function DecisionTrendChart({ data = [] }) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900">Tren Keputusan Approval</h2>
                    <p className="text-xs text-gray-400">6 minggu terakhir</p>
                </div>
                <div className="flex gap-3.5 text-[11px] font-medium text-gray-400">
                    <span className="flex items-center gap-1.5"><span className="h-0.5 w-3.5 rounded bg-blue-600" />Disetujui</span>
                    <span className="flex items-center gap-1.5"><span className="h-0.5 w-3.5 rounded border-t-2 border-dashed border-red-600" />Ditolak &amp; Revisi</span>
                </div>
            </div>
            <div className="h-[190px]">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="#eef0f3" />
                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} allowDecimals={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: '#e5e7eb', fontSize: 12 }} />
                        <Line type="monotone" dataKey="approved" name="Disetujui" stroke="#2563eb" strokeWidth={2.5} dot={{ r: 3 }} />
                        <Line type="monotone" dataKey="rejected" name="Ditolak & Revisi" stroke="#dc2626" strokeWidth={2} strokeDasharray="5 4" dot={{ r: 3 }} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export default function ApprovalInbox({ metrics, priorityDistribution = [], priorityTotal = 0, priorityHighlight = '', decisionTrend = [], pending = [] }) {
    const [category, setCategory] = useState('Semua');
    const [priority, setPriority] = useState('Semua');
    const [sortKey, setSortKey] = useState('created');
    const [sortDir, setSortDir] = useState('asc');

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
        const rows = pending.filter((r) => {
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
    }, [pending, category, priority, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">Approval Inbox</h1>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    label="Menunggu Persetujuan"
                    value={metrics.waitingApproval}
                    icon="M9 12h6M9 16h6M9 8h6M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                    iconBg="bg-blue-50" iconColor="text-blue-600"
                />
                <MetricCard
                    label="Menunggu Terlama"
                    value={metrics.waitingLongest}
                    icon="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3"
                    iconBg="bg-amber-50" iconColor="text-amber-600"
                />
                <MetricCard
                    label="Disetujui Bulan Ini"
                    value={metrics.approvedThisMonth}
                    icon="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9"
                    iconBg="bg-emerald-50" iconColor="text-emerald-600"
                />
                <MetricCard
                    label="Ditolak Bulan Ini"
                    value={metrics.rejectedThisMonth}
                    icon="M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z"
                    iconBg="bg-gray-100" iconColor="text-gray-500"
                />
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <PriorityDistribution rows={priorityDistribution} total={priorityTotal} highlight={priorityHighlight} />
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <DecisionTrendChart data={decisionTrend} />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
                    <h2 className="text-[15px] font-bold text-gray-900">Menunggu Persetujuan Anda</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Kategori</span>
                            <select
                                value={category}
                                onChange={(e) => setCategory(e.target.value)}
                                className="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-700 outline-none"
                            >
                                <option value="Semua">Semua Kategori</option>
                                <option value="Incident">Incident</option>
                                <option value="Service Request">Service</option>
                                <option value="Access Request">Role Access</option>
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Prioritas</span>
                            <select
                                value={priority}
                                onChange={(e) => setPriority(e.target.value)}
                                className="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-700 outline-none"
                            >
                                <option value="Semua">Semua Prioritas</option>
                                <option value="Critical">Critical</option>
                                <option value="High">High</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                            </select>
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
                                    <td className="px-4 py-4 text-gray-700">{row.layanan}</td>
                                    <td className="px-4 py-4 text-gray-700">{row.subCategory}</td>
                                    <td className="px-4 py-4">
                                        <p className="max-w-[220px] truncate font-semibold text-gray-900">{row.subject}</p>
                                    </td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4 text-gray-700">{row.requester}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400">{row.created}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400">Tidak ada tiket menunggu persetujuan dengan filter ini.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400">Menampilkan {filtered.length} dari {pending.length} tiket</span>
                </div>
            </div>
        </div>
    );
}

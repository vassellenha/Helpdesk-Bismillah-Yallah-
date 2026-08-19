import { useMemo, useState } from 'react';
import { t as trans } from '../../lib/i18n';
import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid } from 'recharts';
import { PriorityBadge } from '../StatusBadge';
import SelectMenu from '../SelectMenu';
import PeriodTabs from '../PeriodTabs';

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
        <div className="flex flex-col gap-2.5 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-400 dark:text-ink-3">{label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${iconBg} ${iconColor}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={icon} /></svg>
                </span>
            </div>
            <div className="text-[28px] font-extrabold leading-none text-gray-900 dark:text-ink-1">{value}</div>
        </div>
    );
}

function PriorityDistribution({ rows = [], total = 0, highlight = '', period = 'month' }) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('approver.inbox.priority_distribution', { period: trans(`approver.inbox.periods.${period}`) })}</h2>
                <span className="text-xs text-gray-400 dark:text-ink-3">Total {total} menunggu</span>
            </div>
            <div className="flex flex-col gap-3.5">
                {rows.map((r) => (
                    <div key={r.priority}>
                        <div className="mb-1 flex items-center justify-between text-[13px]">
                            <span className="font-semibold text-gray-700 dark:text-ink-2">{r.priority}</span>
                            <span className="font-bold text-gray-900 dark:text-ink-1">{r.pct}%</span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                            <div className="h-full rounded-full" style={{ width: `${r.pct}%`, backgroundColor: PRIORITY_COLOR[r.priority] }} />
                        </div>
                    </div>
                ))}
            </div>
            <p className="border-t border-gray-100 dark:border-edge pt-3 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">{highlight}</p>
        </div>
    );
}

function DecisionTrendChart({ data = [], period = 'month' }) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('approver.inbox.trend')}</h2>
                    <p className="text-xs text-gray-400 dark:text-ink-3">{trans('approver.inbox.trend_sub', { period: trans(`approver.inbox.periods.${period}`) })}</p>
                </div>
                <div className="flex gap-3.5 text-[11px] font-medium text-gray-400 dark:text-ink-3">
                    <span className="flex items-center gap-1.5"><span className="h-0.5 w-3.5 rounded bg-blue-600 dark:bg-blue-500" />{trans('approver.inbox.approved')}</span>
                    <span className="flex items-center gap-1.5"><span className="h-0.5 w-3.5 rounded border-t-2 border-dashed border-red-600" />Ditolak &amp; Revisi</span>
                </div>
            </div>
            <div className="h-[190px]">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="var(--chart-grid)" />
                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} allowDecimals={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: 'var(--chart-tooltip-border)', backgroundColor: 'var(--chart-tooltip-bg)', color: 'var(--chart-tooltip-text)', fontSize: 12 }} />
                        <Line type="monotone" dataKey="approved" name="Disetujui" stroke="var(--chart-blue)" strokeWidth={2.5} dot={{ r: 3 }} />
                        <Line type="monotone" dataKey="rejected" name="Ditolak & Revisi" stroke="var(--chart-red)" strokeWidth={2} strokeDasharray="5 4" dot={{ r: 3 }} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export default function ApprovalInbox({ metrics, priorityDistribution = [], priorityTotal = 0, priorityHighlight = '', decisionTrend = [], periods = {}, pending = [] }) {
    const [period, setPeriod] = useState('month');
    // Cadangan ke prop lama bila `periods` belum terkirim.
    const current = periods[period] ?? { priorityDistribution, priorityTotal, priorityHighlight, decisionTrend };
    const [search, setSearch] = useState('');
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
        const q = search.trim().toLowerCase();
        const rows = pending.filter((r) => {
            if (category !== 'Semua' && r.category !== category) return false;
            if (priority !== 'Semua' && r.priority !== priority) return false;
            if (q !== '' && !`${r.id} ${r.subject} ${r.layanan} ${r.requester}`.toLowerCase().includes(q)) return false;
            return true;
        });

        rows.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return rows;
    }, [pending, search, category, priority, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{trans('approver.inbox.title')}</h1>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    label={trans('approver.inbox.stat_pending')}
                    value={metrics.waitingApproval}
                    icon="M9 12h6M9 16h6M9 8h6M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                    iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text"
                />
                <MetricCard
                    label={trans('approver.inbox.stat_oldest')}
                    value={metrics.waitingLongest}
                    icon="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3"
                    iconBg="bg-amber-50 dark:bg-warn-soft" iconColor="text-amber-600 dark:text-warn-text"
                />
                <MetricCard
                    label={trans('approver.inbox.stat_approved_month')}
                    value={metrics.approvedThisMonth}
                    icon="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9"
                    iconBg="bg-emerald-50 dark:bg-ok-soft" iconColor="text-emerald-600 dark:text-ok-text"
                />
                <MetricCard
                    label={trans('approver.inbox.stat_rejected_month')}
                    value={metrics.rejectedThisMonth}
                    icon="M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z"
                    iconBg="bg-gray-100 dark:bg-panel-3" iconColor="text-gray-500 dark:text-ink-2"
                />
            </div>

            {/* Kartu metrik di atas tidak ikut tersaring — antrean yang
                menunggu tetap menunggu, berapa pun rentang yang dipilih.
                Periode hanya menyaring blok ringkasan ini. */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">
                    {trans('approver.inbox.summary', { period: trans(`approver.inbox.periods.${period}`) })}
                </h2>
                <PeriodTabs value={period} onChange={setPeriod} labelPath="approver.inbox.periods" />
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                    <PriorityDistribution rows={current.priorityDistribution} total={current.priorityTotal} highlight={current.priorityHighlight} period={period} />
                </div>
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                    <DecisionTrendChart data={current.decisionTrend} period={period} />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-edge p-5">
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('approver.inbox.pending')}</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('approver.inbox.search')}</span>
                            <div className="flex items-center gap-2 rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2.5">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    type="text"
                                    placeholder={trans('approver.inbox.search_placeholder')}
                                    className="w-36 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400 dark:placeholder:text-ink-3"
                                />
                            </div>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('approver.inbox.category')}</span>
                            <SelectMenu
                                value={category}
                                onChange={setCategory}
                                options={[
                                    { value: 'Semua', label: 'Semua Kategori' },
                                    { value: 'Incident', label: 'Incident' },
                                    { value: 'Service Request', label: 'Service' },
                                    { value: 'Access Request', label: 'Role Access' },
                                ]}
                            />
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('approver.inbox.priority')}</span>
                            <SelectMenu
                                value={priority}
                                onChange={setPriority}
                                options={[
                                    { value: 'Semua', label: 'Semua Prioritas' },
                                    { value: 'Critical', label: 'Critical' },
                                    { value: 'High', label: 'High' },
                                    { value: 'Medium', label: 'Medium' },
                                    { value: 'Low', label: 'Low' },
                                ]}
                            />
                        </label>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-[900px] w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                {COLUMNS.map((col) => (
                                    <th key={col.key} className="px-4 py-3.5 text-left first:pl-6 last:pr-6">
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(col.key)}
                                            className="flex items-center gap-1 uppercase tracking-wide text-gray-400 dark:text-ink-3 hover:text-gray-700 dark:hover:text-ink-1"
                                        >
                                            {col.label}
                                            <span aria-hidden="true" className={sortKey === col.key ? 'text-gray-600 dark:text-ink-2' : 'text-gray-300'}>
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
                                    className="cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/40 dark:hover:bg-panel-hover"
                                >
                                    <td className="px-4 py-4 pl-6 font-bold text-blue-600 dark:text-accent-text">{row.id}</td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.layanan}</td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.subCategory}</td>
                                    <td className="px-4 py-4">
                                        <p className="max-w-[220px] truncate font-semibold text-gray-900 dark:text-ink-1">{row.subject}</p>
                                    </td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.requester}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400 dark:text-ink-3">{row.created}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada tiket menunggu persetujuan dengan filter ini.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">Menampilkan {filtered.length} dari {pending.length} tiket</span>
                </div>
            </div>
        </div>
    );
}

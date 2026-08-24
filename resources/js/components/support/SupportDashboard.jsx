import { useMemo, useState } from 'react';
import { priorityNames, priorityRank } from '../../lib/priority';
import TicketCategoryDonut from '../charts/TicketCategoryDonut';
import SlaComplianceDonut from '../charts/SlaComplianceDonut';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import SelectMenu from '../SelectMenu';
import { t as trans } from '../../lib/i18n';

// Sentinel for "no filter". Kept language-independent so switching locale
// never silently reads as a real category/priority value.
const ALL = 'all';

const CATEGORY_OPTIONS = [
    { value: ALL, labelKey: 'support.filters.all_category' },
    { value: 'Incident', label: 'Incident' },
    { value: 'Service Request', label: 'Service Request' },
    { value: 'Access Request', label: 'Access Request' },
];

const PRIORITY_OPTIONS = [
    { value: ALL, labelKey: 'support.filters.all_priority' },
    ...priorityNames().map((name) => ({ value: name, label: name })),
];

const PERIOD_TABS = ['week', 'month', 'year'];

const COLUMNS = [
    { key: 'id', labelKey: 'support.columns.id' },
    { key: 'subject', labelKey: 'support.columns.subject' },
    { key: 'priority', labelKey: 'support.columns.priority' },
    { key: 'status', labelKey: 'support.columns.status' },
    { key: 'sla', labelKey: 'support.columns.sla' },
    { key: 'requester', labelKey: 'support.columns.requester' },
    { key: 'created', labelKey: 'support.columns.created' },
];

function options(list) {
    return list.map((o) => ({ value: o.value, label: o.labelKey ? trans(o.labelKey) : o.label }));
}


function StatCard({ label, value, icon, iconBg, iconColor, href }) {
    const Tag = href ? 'a' : 'div';
    return (
        <Tag
            {...(href ? { href } : {})}
            className={`flex flex-col gap-2.5 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm ${href ? 'transition-shadow hover:shadow-md hover:border-blue-200 dark:hover:border-accent-text' : ''}`}
        >
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-400 dark:text-ink-3">{label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${iconBg} ${iconColor}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={icon} /></svg>
                </span>
            </div>
            <div className="text-[28px] font-extrabold leading-none text-gray-900 dark:text-ink-1">{value}</div>
        </Tag>
    );
}

// Mirrors SupportHistoryPage's CARDS keys (Total/Open/In Progress/Resolved/
// Closed) for `status`, and its separate `slaRisk` overlay for the one card
// that isn't a status bucket at all (SLA risk cuts across every status).
function cardHref(ticketsUrl, { status, slaRisk }, label) {
    if (!ticketsUrl) return undefined;
    const params = new URLSearchParams({ label });
    if (status) params.set('status', status);
    if (slaRisk) params.set('slaRisk', '1');
    return `${ticketsUrl}?${params.toString()}`;
}

function PriorityBarChart({ rows = [] }) {
    const max = Math.max(1, ...rows.map((r) => r.count));

    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('support.dashboard.by_priority')}</h2>
                <span className="text-xs text-gray-400 dark:text-ink-3">{trans('support.dashboard.total', { count: rows.reduce((s, r) => s + r.count, 0) })}</span>
            </div>
            <div className="flex flex-1 items-end justify-around gap-3 pt-2">
                {rows.map((r) => (
                    <div key={r.priority} className="flex flex-1 flex-col items-center gap-2">
                        <span className="text-sm font-extrabold text-gray-900 dark:text-ink-1">{r.count}</span>
                        <div className="flex h-24 w-full items-end justify-center">
                            <div
                                className="w-8 rounded-t-md transition-all"
                                style={{ height: `${Math.max(6, (r.count / max) * 100)}%`, backgroundColor: r.color }}
                            />
                        </div>
                        <span className="text-[11px] font-medium text-gray-500 dark:text-ink-2">{r.priority}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function StarRow({ rating = 0, size = 15 }) {
    const pct = Math.max(0, Math.min(100, (rating / 5) * 100));
    const stars = (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <svg key={n} width={size} height={size} viewBox="0 0 24 24" fill="currentColor">
                    <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z" />
                </svg>
            ))}
        </div>
    );

    return (
        <div className="relative inline-flex text-gray-200">
            {stars}
            <div className="absolute inset-0 overflow-hidden text-amber-500" style={{ width: `${pct}%` }}>
                {stars}
            </div>
        </div>
    );
}

/**
 * Personal rating badge — like a Gojek driver's own star rating on their
 * home screen: one number for yourself, not a leaderboard of everyone else.
 */
function MyRatingBadge({ rating = {} }) {
    const { average, count } = rating;

    if (average === null || average === undefined) {
        return (
            <div className="flex items-center gap-2 rounded-full border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 shadow-sm">
                <StarRow rating={0} size={15} />
                <span className="text-xs font-semibold text-gray-400 dark:text-ink-3">{trans('support.dashboard.no_rating')}</span>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2.5 rounded-full border border-amber-200 bg-amber-50 dark:bg-warn-soft px-4 py-2 shadow-sm">
            <StarRow rating={average} size={16} />
            <span className="text-sm font-extrabold text-gray-900 dark:text-ink-1">{average.toFixed(1)}</span>
            <span className="text-xs font-semibold text-gray-500 dark:text-ink-2">({trans('support.dashboard.reviews', { count })})</span>
        </div>
    );
}

export default function SupportDashboard({ stats = {}, periods = {}, queue = [], myRating = {}, ticketsUrl }) {
    const [period, setPeriod] = useState('month');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState(ALL);
    const [priority, setPriority] = useState(ALL);
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
        if (key === 'priority') return priorityRank(row.priority);
        if (key === 'created') return new Date(row.createdAt).getTime();
        return (row[key] ?? '').toString().toLowerCase();
    }

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        const rows = queue.filter((r) => {
            if (category !== ALL && r.category !== category) return false;
            if (priority !== ALL && r.priority !== priority) return false;
            if (q !== '' && !`${r.id} ${r.subject} ${r.requester}`.toLowerCase().includes(q)) return false;
            return true;
        });

        rows.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return rows;
    }, [queue, search, category, priority, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{trans('support.dashboard.title')}</h1>
                <MyRatingBadge rating={myRating} />
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label={trans('support.dashboard.assigned_to_me')}
                    value={stats.assignedToMe ?? 0}
                    icon="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z M14 5v14"
                    iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text"
                    href={cardHref(ticketsUrl, { status: 'Open' }, trans('support.dashboard.assigned_to_me'))}
                />
                <StatCard
                    label={trans('support.dashboard.in_progress')}
                    value={stats.inProgress ?? 0}
                    icon="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3"
                    iconBg="bg-amber-50 dark:bg-warn-soft" iconColor="text-amber-600 dark:text-warn-text"
                    href={cardHref(ticketsUrl, { status: 'In Progress' }, trans('support.dashboard.in_progress'))}
                />
                <StatCard
                    label={trans('support.dashboard.near_sla')}
                    value={stats.slaAtRisk ?? 0}
                    icon="M12 9v4 M12 17h.01 M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                    iconBg="bg-red-50 dark:bg-bad-soft" iconColor="text-red-600 dark:text-bad-text"
                    href={cardHref(ticketsUrl, { slaRisk: true }, trans('support.dashboard.near_sla'))}
                />
                <StatCard
                    label={trans('support.dashboard.resolved')}
                    value={stats.resolvedThisMonth ?? 0}
                    icon="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9"
                    iconBg="bg-emerald-50 dark:bg-ok-soft" iconColor="text-emerald-600 dark:text-ok-text"
                    href={cardHref(ticketsUrl, { status: 'Resolved' }, trans('support.dashboard.resolved'))}
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('support.dashboard.summary', { period: trans(`support.periods.${period}`) })}</h2>
                <div className="flex gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-sm">
                    {PERIOD_TABS.map((p) => (
                        <button
                            key={p}
                            onClick={() => setPeriod(p)}
                            className={`rounded-lg px-3.5 py-2 text-[13px] font-semibold ${period === p ? 'bg-blue-600 dark:bg-blue-500 text-white' : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                        >
                            {trans(`support.periods.${p}`)}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                    <PriorityBarChart rows={current.priority} />
                </div>
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                    <h2 className="mb-3 text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('support.dashboard.category_distribution')}</h2>
                    <TicketCategoryDonut data={current.category} total={categoryTotal} />
                </div>
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                    <SlaComplianceDonut donut={current.sla} />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-edge p-5">
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('support.dashboard.queue')}</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('support.dashboard.search')}</span>
                            <div className="flex items-center gap-2 rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2.5">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    type="text"
                                    placeholder={trans('support.dashboard.search_placeholder')}
                                    className="w-36 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400 dark:placeholder:text-ink-3"
                                />
                            </div>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('support.dashboard.category')}</span>
                            <SelectMenu value={category} onChange={setCategory} options={options(CATEGORY_OPTIONS)} />
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('support.dashboard.priority')}</span>
                            <SelectMenu value={priority} onChange={setPriority} options={options(PRIORITY_OPTIONS)} />
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
                                            {trans(col.labelKey)}
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
                                    <td className="px-4 py-4">
                                        <p className="max-w-[220px] truncate font-semibold text-gray-900 dark:text-ink-1">{row.subject}</p>
                                    </td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.sla}</td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.requester}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400 dark:text-ink-3">{row.created}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('support.dashboard.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">{trans('support.dashboard.showing', { shown: filtered.length, total: queue.length })}</span>
                </div>
            </div>
        </div>
    );
}

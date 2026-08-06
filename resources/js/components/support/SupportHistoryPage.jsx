import { useMemo, useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import SelectMenu from '../SelectMenu';
import { t as trans } from '../../lib/i18n';

// Sentinel for "no service filter" — must not be a translated label, or
// switching language would turn it into a service name that matches nothing.
const ALL_SERVICE = 'all';

const CARDS = [
    { key: 'Total', labelKey: 'support.cards.total', icon: 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z', bg: 'bg-gray-100 dark:bg-panel-3', color: 'text-gray-500 dark:text-ink-2' },
    { key: 'Open', labelKey: 'support.cards.open', icon: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 7v5l3 3', bg: 'bg-gray-100 dark:bg-panel-3', color: 'text-gray-600 dark:text-ink-2' },
    { key: 'In Progress', labelKey: 'support.cards.in_progress', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3', bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-600 dark:text-accent-text' },
    { key: 'Resolved', labelKey: 'support.cards.resolved', icon: 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9', bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    { key: 'Closed', labelKey: 'support.cards.closed', icon: 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z', bg: 'bg-gray-100 dark:bg-panel-3', color: 'text-gray-500 dark:text-ink-2' },
];

const STATUS_PILLS = [
    { key: 'Total', labelKey: 'support.cards.all' },
    { key: 'Open', labelKey: 'support.cards.open' },
    { key: 'In Progress', labelKey: 'support.cards.in_progress' },
    { key: 'Resolved', labelKey: 'support.cards.resolved' },
    { key: 'Closed', labelKey: 'support.cards.closed' },
];

const STATUS_BUCKET = {
    Open: (s) => s === 'Open',
    'In Progress': (s) => ['Assigned', 'In Progress', 'Waiting for Response'].includes(s),
    Resolved: (s) => s === 'Resolved',
    Closed: (s) => ['Closed', 'Completed'].includes(s),
};

const PERIOD_DAYS = { last_30_days: 30, last_3_months: 90, last_6_months: 183, this_year: 366 };

const COLUMNS = [
    { key: 'id', labelKey: 'support.columns.ticket' },
    { key: 'service', labelKey: 'support.columns.service' },
    { key: 'priority', labelKey: 'support.columns.priority' },
    { key: 'status', labelKey: 'support.columns.status' },
    { key: 'requester', labelKey: 'support.columns.requester' },
    { key: 'createdAt', labelKey: 'support.columns.created' },
];

function statusMatches(status, filterKey) {
    if (filterKey === 'Total') return true;
    const test = STATUS_BUCKET[filterKey];
    return test ? test(status) : true;
}

export default function SupportHistoryPage({ counts = {}, rows = [] }) {
    const [activeStatus, setActiveStatus] = useState('Total');
    const [search, setSearch] = useState('');
    const [layanan, setLayanan] = useState(ALL_SERVICE);
    const [period, setPeriod] = useState('this_year');
    const [sortKey, setSortKey] = useState('createdAt');
    const [sortDir, setSortDir] = useState('desc');

    function toggleSort(key) {
        if (key === sortKey) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    const layananOptions = useMemo(() => [
        { value: ALL_SERVICE, label: trans('support.history.all_service') },
        ...[...new Set(rows.map((r) => r.layanan).filter((v) => v && v !== '—'))].map((o) => ({ value: o, label: o })),
    ], [rows]);

    const filtered = useMemo(() => {
        const cutoff = Date.now() - PERIOD_DAYS[period] * 24 * 60 * 60 * 1000;

        const list = rows.filter((r) => {
            if (!statusMatches(r.status, activeStatus)) return false;
            if (layanan !== ALL_SERVICE && r.layanan !== layanan) return false;
            if (new Date(r.createdAt).getTime() < cutoff) return false;
            if (search.trim() !== '') {
                const q = search.trim().toLowerCase();
                const haystack = `${r.id} ${r.title} ${r.layanan}`.toLowerCase();
                if (!haystack.includes(q)) return false;
            }
            return true;
        });

        list.sort((a, b) => {
            const av = sortKey === 'createdAt' ? new Date(a.createdAt).getTime() : (a[sortKey] ?? '').toString().toLowerCase();
            const bv = sortKey === 'createdAt' ? new Date(b.createdAt).getTime() : (b[sortKey] ?? '').toString().toLowerCase();
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return list;
    }, [rows, activeStatus, layanan, period, search, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{trans('support.history.title')}</h1>
                <p className="mt-1 text-[13px] text-gray-400 dark:text-ink-3">{trans('support.history.subtitle', { count: rows.length })}</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
                {CARDS.map((c) => (
                    <button
                        key={c.key}
                        onClick={() => setActiveStatus(c.key)}
                        className={`flex flex-col gap-2.5 rounded-2xl border bg-white dark:bg-panel-2 p-4 text-left shadow-sm transition ${activeStatus === c.key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 dark:border-edge-strong hover:border-gray-300'}`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-gray-400 dark:text-ink-3">{trans(c.labelKey)}</span>
                            <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${c.bg} ${c.color}`}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={c.icon} /></svg>
                            </span>
                        </div>
                        <div className="text-[28px] font-extrabold leading-none text-gray-900 dark:text-ink-1">{counts[c.key] ?? 0}</div>
                    </button>
                ))}
            </div>

            <div className="flex items-center gap-2 rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-3 shadow-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    type="text"
                    placeholder={trans('support.history.search_placeholder')}
                    className="flex-1 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-nowrap gap-1.5 overflow-x-auto rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-sm">
                    {STATUS_PILLS.map((p) => {
                        const active = activeStatus === p.key;
                        return (
                            <button
                                key={p.key}
                                onClick={() => setActiveStatus(p.key)}
                                className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-[13px] font-semibold ${active ? 'bg-blue-600 dark:bg-blue-500 text-white' : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                            >
                                {trans(p.labelKey)}
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <SelectMenu
                        value={layanan}
                        onChange={setLayanan}
                        options={layananOptions}
                    />
                    <SelectMenu
                        value={period}
                        onChange={setPeriod}
                        options={Object.keys(PERIOD_DAYS).map((p) => ({ value: p, label: trans(`support.history.periods.${p}`) }))}
                    />
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
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
                                    <td className="px-4 py-4 pl-6">
                                        <p className="font-bold text-blue-600 dark:text-accent-text">{row.id}</p>
                                        <p className="max-w-[200px] truncate text-xs text-gray-400 dark:text-ink-3">{row.title}</p>
                                    </td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.service}</td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.requester}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400 dark:text-ink-3">{row.at}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('support.history.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">{trans('support.history.showing', { shown: filtered.length, total: rows.length })}</span>
                </div>
            </div>
        </div>
    );
}

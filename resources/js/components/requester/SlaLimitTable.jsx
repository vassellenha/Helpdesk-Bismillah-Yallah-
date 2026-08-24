import { useMemo, useState } from 'react';
import { priorityRank } from '../../lib/priority';
import { t as trans } from '../../lib/i18n';
import { PriorityBadge, StatusBadge } from '../StatusBadge';

const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', none: '#9ca3af' };

const COLUMNS = [
    { key: 'id', label: 'Ticket No.' },
    { key: 'title', label: 'Subject' },
    { key: 'category', label: 'Category' },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status' },
    { key: 'slaMinutes', label: 'SLA Remaining' },
];

function sortValue(row, key) {
    if (key === 'slaMinutes') return row.slaMinutes === null ? Infinity : row.slaMinutes;
    if (key === 'priority') return priorityRank(row.priority);
    return (row[key] ?? '').toString().toLowerCase();
}

export default function SlaLimitTable({ rows = [], ticketsUrl = '/' }) {
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState('slaMinutes');
    const [dir, setDir] = useState('asc');

    function toggleSort(key) {
        if (key === sortKey) {
            setDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setDir('asc');
        }
    }

    const sorted = useMemo(() => {
        const q = search.trim().toLowerCase();
        const copy = rows.filter((r) => q === '' || `${r.id} ${r.title} ${r.category}`.toLowerCase().includes(q));
        copy.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return dir === 'asc' ? cmp : -cmp;
        });
        return copy;
    }, [rows, search, sortKey, dir]);

    return (
        <div className="overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 pb-3.5 pt-4">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.sla_table.title')}</h2>
                    <p className="text-xs text-gray-400 dark:text-ink-3">{trans('requester.sla_table.subtitle')}</p>
                </div>
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            type="text"
                            placeholder={trans('requester.sla_table.search')}
                            className="w-36 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400 dark:placeholder:text-ink-3"
                        />
                    </div>
                    <a href={ticketsUrl} className="shrink-0 text-xs font-semibold text-blue-600 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">{trans('requester.sla_table.view_all')}</a>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-[720px] w-full text-sm">
                    <thead>
                        <tr className="border-y border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            {COLUMNS.map((col) => (
                                <th key={col.key} className="px-4 py-3 text-left first:pl-6 last:pr-6">
                                    <button
                                        type="button"
                                        onClick={() => toggleSort(col.key)}
                                        className="flex items-center gap-1 uppercase tracking-wide text-gray-400 dark:text-ink-3 hover:text-gray-700 dark:hover:text-ink-1"
                                    >
                                        {col.label}
                                        <span aria-hidden="true" className={sortKey === col.key ? 'text-gray-600 dark:text-ink-2' : 'text-gray-300'}>
                                            {sortKey === col.key ? (dir === 'asc' ? '↑' : '↓') : '↕'}
                                        </span>
                                    </button>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((row) => (
                            <tr
                                key={row.id}
                                onClick={() => row.href && (window.location.href = row.href)}
                                className="cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/40 dark:hover:bg-panel-hover"
                            >
                                <td className="px-4 py-4 pl-6 font-bold text-blue-600 dark:text-accent-text">{row.id}</td>
                                <td className="px-4 py-4">
                                    <p className="max-w-[220px] truncate font-semibold text-gray-900 dark:text-ink-1">{row.title}</p>
                                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{row.app}</p>
                                </td>
                                <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.category}</td>
                                <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                <td className="px-4 py-4 pr-6">
                                    <div className="flex flex-col gap-1.5">
                                        <span className="text-xs font-bold" style={{ color: SLA_COLOR[row.slaKind] }}>{row.sla}</span>
                                        <div className="h-1 w-full max-w-[110px] overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                                            <div className="h-full rounded-full" style={{ width: `${row.slaPct}%`, backgroundColor: SLA_COLOR[row.slaKind] }} />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sorted.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-5 py-10 text-center text-sm text-gray-400 dark:text-ink-3">{trans('requester.sla_table.empty')}</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

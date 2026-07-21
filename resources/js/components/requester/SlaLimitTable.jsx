import { useMemo, useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';

const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', none: '#9ca3af' };
const PRIORITY_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };

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
    if (key === 'priority') return PRIORITY_RANK[row.priority] ?? 0;
    return (row[key] ?? '').toString().toLowerCase();
}

export default function SlaLimitTable({ rows = [], ticketsUrl = '/' }) {
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
        const copy = [...rows];
        copy.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return dir === 'asc' ? cmp : -cmp;
        });
        return copy;
    }, [rows, sortKey, dir]);

    return (
        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex items-center justify-between px-5 pb-3.5 pt-4">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900">Tickets Approaching SLA Limit</h2>
                    <p className="text-xs text-gray-400">Sorted by least time remaining</p>
                </div>
                <a href={ticketsUrl} className="text-xs font-semibold text-blue-600 hover:text-blue-800">View all tickets →</a>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-[720px] w-full text-sm">
                    <thead>
                        <tr className="border-y border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                            {COLUMNS.map((col) => (
                                <th key={col.key} className="px-4 py-3 text-left first:pl-6 last:pr-6">
                                    <button
                                        type="button"
                                        onClick={() => toggleSort(col.key)}
                                        className="flex items-center gap-1 uppercase tracking-wide text-gray-400 hover:text-gray-700"
                                    >
                                        {col.label}
                                        <span aria-hidden="true" className={sortKey === col.key ? 'text-gray-600' : 'text-gray-300'}>
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
                                className="cursor-pointer border-b border-gray-50 last:border-0 hover:bg-blue-50/40"
                            >
                                <td className="px-4 py-4 pl-6 font-bold text-blue-600">{row.id}</td>
                                <td className="px-4 py-4">
                                    <p className="max-w-[220px] truncate font-semibold text-gray-900">{row.title}</p>
                                    <p className="mt-0.5 text-xs text-gray-400">{row.app}</p>
                                </td>
                                <td className="px-4 py-4 text-gray-700">{row.category}</td>
                                <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                <td className="px-4 py-4 pr-6">
                                    <div className="flex flex-col gap-1.5">
                                        <span className="text-xs font-bold" style={{ color: SLA_COLOR[row.slaKind] }}>{row.sla}</span>
                                        <div className="h-1 w-full max-w-[110px] overflow-hidden rounded-full bg-gray-100">
                                            <div className="h-full rounded-full" style={{ width: `${row.slaPct}%`, backgroundColor: SLA_COLOR[row.slaKind] }} />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sorted.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-5 py-10 text-center text-sm text-gray-400">No tickets are approaching their SLA limit.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

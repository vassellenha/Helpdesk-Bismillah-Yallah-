import { useMemo, useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import NewTicketModal from '../NewTicketModal';
import SelectMenu from '../SelectMenu';

const ACTIVE_STATUSES = ['Waiting for Approval', 'Open', 'Assigned', 'In Progress', 'Waiting for Response'];
const DONE_STATUSES = ['Resolved', 'Completed', 'Closed'];
const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', none: '#9ca3af' };
const PERIOD_DAYS = { 'Last 30 days': 30, 'Last 3 months': 92, 'Last 6 months': 183, 'This year': 366 };
const PRIORITY_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };

const COLUMNS = [
    { key: 'id', label: 'Ticket No.' },
    { key: 'title', label: 'Subject' },
    { key: 'category', label: 'Category' },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status' },
    { key: 'slaMinutes', label: 'SLA' },
    { key: 'createdAt', label: 'Created' },
];

function inTab(tab, status) {
    if (tab === 'All') return true;
    if (tab === 'Draft') return status === 'Draft' || status === 'Returned';
    if (tab === 'Active') return ACTIVE_STATUSES.includes(status);
    if (tab === 'Completed') return DONE_STATUSES.includes(status);
    return status === 'Rejected';
}

function sortValue(row, key) {
    if (key === 'slaMinutes') return row.slaMinutes === null ? Infinity : row.slaMinutes;
    if (key === 'priority') return PRIORITY_RANK[row.priority] ?? 0;
    if (key === 'createdAt') return new Date(row.createdAt).getTime();
    return (row[key] ?? '').toString().toLowerCase();
}

export default function MyTicketsPage({ tickets = [], counts = {}, catalogUrl, approversUrl, submitUrl }) {
    const [tab, setTab] = useState('All');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('All Issue Categories');
    const [service, setService] = useState('All Layanan');
    const [subcategory, setSubcategory] = useState('All Sub Category');
    const [priority, setPriority] = useState('All Priorities');
    const [period, setPeriod] = useState('Last 6 months');
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

    const categories = useMemo(() => ['All Issue Categories', ...new Set(tickets.map((t) => t.category).filter(Boolean))], [tickets]);
    const services = useMemo(() => ['All Layanan', ...new Set(tickets.map((t) => t.service).filter(Boolean))], [tickets]);
    const subcategories = useMemo(() => ['All Sub Category', ...new Set(tickets.map((t) => t.subcategory).filter(Boolean))], [tickets]);

    const filtered = useMemo(() => {
        const cutoff = Date.now() - PERIOD_DAYS[period] * 24 * 60 * 60 * 1000;

        const rows = tickets.filter((t) => {
            if (!inTab(tab, t.status)) return false;
            if (category !== 'All Issue Categories' && t.category !== category) return false;
            if (service !== 'All Layanan' && t.service !== service) return false;
            if (subcategory !== 'All Sub Category' && t.subcategory !== subcategory) return false;
            if (priority !== 'All Priorities' && t.priority !== priority) return false;
            if (new Date(t.createdAt).getTime() < cutoff) return false;
            if (search.trim() !== '') {
                const q = search.trim().toLowerCase();
                if (!t.id.toLowerCase().includes(q) && !t.title.toLowerCase().includes(q)) return false;
            }
            return true;
        });

        rows.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });
        return rows;
    }, [tickets, tab, category, service, subcategory, priority, period, search, sortKey, sortDir]);

    const tabs = ['All', 'Draft', 'Active', 'Completed', 'Rejected'];

    return (
        <div className="flex flex-col gap-7">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">My Tickets</h1>
                    <p className="mt-1 text-[13px] text-gray-400 dark:text-ink-3">Track the history and progress of every ticket you have submitted.</p>
                </div>
                <NewTicketModal catalogUrl={catalogUrl} approversUrl={approversUrl} submitUrl={submitUrl} />
            </div>

            <div className="flex w-fit gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-sm">
                {tabs.map((t) => {
                    const active = tab === t;
                    return (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-semibold ${active ? 'bg-blue-600 dark:bg-blue-500 text-white' : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                        >
                            {t}
                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${active ? 'bg-white/25 text-white' : 'bg-gray-100 dark:bg-panel-3 text-gray-400 dark:text-ink-3'}`}>
                                {counts[t] ?? 0}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <div className="flex min-w-[220px] flex-1 items-center gap-2 rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2.5">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        type="text"
                        placeholder="Search by ticket number or title…"
                        className="flex-1 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400"
                    />
                </div>
                <SelectMenu value={service} onChange={setService} options={services.map((s) => ({ value: s, label: s }))} />
                <SelectMenu value={subcategory} onChange={setSubcategory} options={subcategories.map((s) => ({ value: s, label: s }))} />
                <SelectMenu value={category} onChange={setCategory} options={categories.map((c) => ({ value: c, label: c }))} />
                <SelectMenu value={priority} onChange={setPriority} options={['All Priorities', 'Critical', 'High', 'Medium', 'Low'].map((p) => ({ value: p, label: p }))} />
                <SelectMenu value={period} onChange={setPeriod} options={Object.keys(PERIOD_DAYS).map((p) => ({ value: p, label: p }))} />
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[860px] w-full text-sm">
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
                                    onClick={() => row.href && (window.location.href = row.href)}
                                    className="cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/40 dark:hover:bg-panel-hover"
                                >
                                    <td className="px-4 py-4 pl-6 font-bold text-blue-600 dark:text-accent-text">{row.id}</td>
                                    <td className="px-4 py-4">
                                        <p className="max-w-[240px] truncate font-semibold text-gray-900 dark:text-ink-1">{row.title}</p>
                                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{row.app}</p>
                                    </td>
                                    <td className="px-4 py-4 text-gray-700 dark:text-ink-2">{row.category}</td>
                                    <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                    <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                    <td className="px-4 py-4 font-semibold" style={{ color: SLA_COLOR[row.slaKind] }}>{row.sla}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400 dark:text-ink-3">{row.created}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">No tickets match these filters.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">Showing {filtered.length} of {tickets.length} tickets</span>
                </div>
            </div>
        </div>
    );
}

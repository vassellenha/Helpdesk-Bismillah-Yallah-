import { useEffect, useMemo, useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import NewTicketModal from '../NewTicketModal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
// Imported as `trans`, not `t`: this file already uses `t` for a ticket in half
// a dozen callbacks, and the shadowing would be silent — the translation call
// would just read a property off a ticket and render nothing.
import { t as trans } from '../../lib/i18n';

const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', none: '#9ca3af' };

// Stable keys, not labels: a filter or period the user picked must survive a
// language switch, so state holds the key and only its label is translated.
const PERIOD_DAYS = { last_30_days: 30, last_3_months: 92, last_6_months: 183, this_year: 366 };
const ALL = 'all';
const PRIORITY_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };

const COLUMNS = [
    { key: 'id', labelKey: 'requester.columns.id' },
    { key: 'title', labelKey: 'requester.columns.title' },
    { key: 'category', labelKey: 'requester.columns.category' },
    { key: 'priority', labelKey: 'requester.columns.priority' },
    { key: 'status', labelKey: 'requester.columns.status' },
    { key: 'slaMinutes', labelKey: 'requester.columns.sla' },
    { key: 'createdAt', labelKey: 'requester.columns.created' },
];

// Same status pills the Approver's My Tickets uses, so a requester and an
// approver reading the same ticket describe its state with the same word.
// "Draft" is the one addition — only a requester has unsent tickets.
const STATUS_PILLS = ['Semua', 'Draft', 'Returned', 'Waiting for Approval', 'Open', 'In Progress', 'Resolved', 'Closed', 'Rejected'];

const STATUS_BUCKET = {
    Draft: (s) => s === 'Draft',
    Returned: (s) => s === 'Returned',
    'Waiting for Approval': (s) => s === 'Waiting for Approval',
    Open: (s) => s === 'Open',
    'In Progress': (s) => ['Assigned', 'In Progress', 'Waiting for Response'].includes(s),
    Resolved: (s) => s === 'Resolved',
    Closed: (s) => ['Closed', 'Completed'].includes(s),
    Rejected: (s) => s === 'Rejected',
};

// Draft and Returned are the only tickets still purely the requester's to
// discard — TicketController::destroy() enforces the same boundary.
const BULK_DELETABLE = ['Draft', 'Returned'];

function inTab(tab, status) {
    if (tab === 'Semua') return true;
    const test = STATUS_BUCKET[tab];
    return test ? test(status) : true;
}

function sortValue(row, key) {
    if (key === 'slaMinutes') return row.slaMinutes === null ? Infinity : row.slaMinutes;
    if (key === 'priority') return PRIORITY_RANK[row.priority] ?? 0;
    if (key === 'createdAt') return new Date(row.createdAt).getTime();
    return (row[key] ?? '').toString().toLowerCase();
}

function DeleteConfirmModal({ count, label, deleting, onCancel, onConfirm }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={() => !deleting && onCancel()}>
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white dark:bg-panel-2 p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18 M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2 M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6 M10 11v6 M14 11v6" /></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">Hapus {count} tiket {label}?</h2>
                        <p className="mt-1 text-[13px] leading-relaxed text-gray-500 dark:text-ink-2">
                            Tiket yang dipilih akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.
                        </p>
                    </div>
                </div>
                <div className="mt-5 flex justify-end gap-2.5">
                    <button
                        onClick={onCancel}
                        disabled={deleting}
                        className="rounded-full border border-gray-200 dark:border-edge-strong px-4 py-2.5 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Tidak
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={deleting}
                        className="rounded-full bg-red-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {deleting ? 'Menghapus…' : 'Ya, Hapus'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function MyTicketsPage({ tickets: initialTickets = [], catalogUrl, approversUrl, submitUrl }) {
    const [tickets, setTickets] = useState(initialTickets);
    const [tab, setTab] = useState('Semua');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState(ALL);
    const [service, setService] = useState(ALL);
    const [subcategory, setSubcategory] = useState(ALL);
    const [priority, setPriority] = useState(ALL);
    const [period, setPeriod] = useState('last_6_months');
    const [sortKey, setSortKey] = useState('createdAt');
    const [sortDir, setSortDir] = useState('desc');
    const [selectedIds, setSelectedIds] = useState(new Set());
    const [deleting, setDeleting] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleteError, setDeleteError] = useState('');

    const bulkDeletable = BULK_DELETABLE.includes(tab);

    // Selecting tickets to delete is only meaningful on a bulk-deletable tab —
    // leaving it clears the checkboxes instead of letting a stale selection
    // carry over and silently delete the wrong tickets later.
    useEffect(() => {
        if (! bulkDeletable) setSelectedIds(new Set());
    }, [bulkDeletable]);

    // Derived live from the ticket list (not a server-supplied prop) so it stays
    // correct right after a client-side delete, with no page reload.
    const returnedCount = useMemo(() => tickets.filter((t) => t.status === 'Returned').length, [tickets]);

    function toggleSort(key) {
        if (key === sortKey) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    // Each option is { value, label }: value is the raw data (or ALL) used for
    // comparison, label is what the user reads.
    const options = (values, allLabelKey) => [
        { value: ALL, label: trans(allLabelKey) },
        ...values.map((v) => ({ value: v, label: v })),
    ];

    const categories = useMemo(() => [...new Set(tickets.map((t) => t.category).filter(Boolean))], [tickets]);
    const services = useMemo(() => [...new Set(tickets.map((t) => t.service).filter(Boolean))], [tickets]);
    const subcategories = useMemo(() => [...new Set(tickets.map((t) => t.subcategory).filter(Boolean))], [tickets]);

    const filtered = useMemo(() => {
        const cutoff = Date.now() - PERIOD_DAYS[period] * 24 * 60 * 60 * 1000;

        const rows = tickets.filter((t) => {
            if (!inTab(tab, t.status)) return false;
            if (category !== ALL && t.category !== category) return false;
            if (service !== ALL && t.service !== service) return false;
            if (subcategory !== ALL && t.subcategory !== subcategory) return false;
            if (priority !== ALL && t.priority !== priority) return false;
            if (new Date(t.createdAt).getTime() < cutoff) return false;
            if (search.trim() !== '') {
                const q = search.trim().toLowerCase();
                const haystack = `${t.id} ${t.title} ${t.service ?? ''}`.toLowerCase();
                if (!haystack.includes(q)) return false;
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

    const allFilteredSelected = filtered.length > 0 && filtered.every((row) => selectedIds.has(row.id));

    function toggleRow(id) {
        setSelectedIds((current) => {
            const next = new Set(current);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    function toggleSelectAll() {
        setSelectedIds((current) => {
            if (allFilteredSelected) return new Set();
            return new Set(filtered.map((row) => row.id));
        });
    }

    async function deleteSelected() {
        if (selectedIds.size === 0) return;

        setDeleting(true);
        setDeleteError('');
        const ids = [...selectedIds];
        const results = await Promise.allSettled(ids.map((id) => apiFetch(`/requester/tickets/${id}`, { method: 'DELETE' })));
        const deletedIds = ids.filter((_, i) => results[i].status === 'fulfilled');

        setTickets((current) => current.filter((t) => !deletedIds.includes(t.id)));
        setSelectedIds(new Set());
        setDeleting(false);
        setShowDeleteConfirm(false);

        const failedCount = results.length - deletedIds.length;
        if (failedCount > 0) {
            setDeleteError(`${failedCount} tiket gagal dihapus. Coba lagi.`);
        }
    }

    return (
        <div className="flex flex-col gap-7">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{trans('requester.my_tickets')}</h1>
                    <p className="mt-1 text-[13px] text-gray-400 dark:text-ink-3">Track the history and progress of every ticket you have submitted.</p>
                </div>
                <NewTicketModal catalogUrl={catalogUrl} approversUrl={approversUrl} submitUrl={submitUrl} />
            </div>

            {returnedCount > 0 && tab !== 'Returned' && (
                <button
                    type="button"
                    onClick={() => setTab('Returned')}
                    className="flex items-center gap-3 rounded-xl border border-amber-200 dark:border-transparent bg-amber-50 dark:bg-warn-soft px-4 py-3 text-left text-[13px] text-amber-800 dark:text-warn-text hover:bg-amber-100 dark:hover:bg-panel-hover"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0"><path d="M9 14 4 9l5-5" /><path d="M20 20v-7a4 4 0 0 0-4-4H4" /></svg>
                    <span>
                        <span className="font-bold">{trans('requester.returned_banner', { count: returnedCount })}</span>{' '}
                        Buka tiketnya, baca catatan Support, lalu tekan “Edit &amp; Resubmit” untuk mengirim ulang.
                    </span>
                </button>
            )}

            <div className="flex items-center gap-2 rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-3 shadow-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    type="text"
                    placeholder={trans('requester.search_placeholder')}
                    className="flex-1 border-none bg-transparent text-[13px] text-gray-900 dark:text-ink-1 outline-none placeholder:text-gray-400"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-sm">
                    {STATUS_PILLS.map((p) => {
                        const active = tab === p;
                        return (
                            <button
                                key={p}
                                onClick={() => setTab(p)}
                                className={`rounded-lg px-3.5 py-2 text-[13px] font-semibold ${active ? 'bg-blue-600 dark:bg-blue-500 text-white' : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                            >
                                {p}
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <SelectMenu value={service} onChange={setService} options={options(services, 'requester.filters.all_service')} />
                    <SelectMenu value={subcategory} onChange={setSubcategory} options={options(subcategories, 'requester.filters.all_subcategory')} />
                    <SelectMenu value={category} onChange={setCategory} options={options(categories, 'requester.filters.all_category')} />
                    <SelectMenu value={priority} onChange={setPriority} options={options(['Critical', 'High', 'Medium', 'Low'], 'requester.filters.all_priority')} />
                    <SelectMenu value={period} onChange={setPeriod} options={Object.keys(PERIOD_DAYS).map((p) => ({ value: p, label: trans(`requester.periods.${p}`) }))} />
                </div>
            </div>

            {bulkDeletable && selectedIds.size > 0 && (
                <div className="flex items-center justify-between rounded-xl border border-blue-200 dark:border-edge-strong bg-blue-50 dark:bg-accent-soft px-4 py-3">
                    <span className="text-[13px] font-semibold text-blue-800 dark:text-accent-text">{selectedIds.size} tiket dipilih</span>
                    <button
                        type="button"
                        onClick={() => setShowDeleteConfirm(true)}
                        disabled={deleting}
                        className="rounded-lg bg-red-600 px-4 py-2 text-[13px] font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {deleting ? 'Menghapus…' : 'Hapus Terpilih'}
                    </button>
                </div>
            )}

            {deleteError && (
                <p className="rounded-xl border border-red-200 bg-red-50 dark:bg-bad-soft dark:border-transparent px-4 py-3 text-[13px] font-medium text-red-700 dark:text-bad-text">
                    {deleteError}
                </p>
            )}

            {showDeleteConfirm && (
                <DeleteConfirmModal
                    count={selectedIds.size}
                    label={tab === 'Returned' ? 'yang dikembalikan' : 'draft'}
                    deleting={deleting}
                    onCancel={() => setShowDeleteConfirm(false)}
                    onConfirm={deleteSelected}
                />
            )}

            <div className="overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[860px] w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                {bulkDeletable && (
                                    <th className="w-10 px-4 py-3.5 pl-6">
                                        <input
                                            type="checkbox"
                                            checked={allFilteredSelected}
                                            onChange={toggleSelectAll}
                                            className="h-4 w-4 rounded border-gray-300 dark:border-edge-strong"
                                        />
                                    </th>
                                )}
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
                                    onClick={() => row.href && (window.location.href = row.href)}
                                    className="cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/40 dark:hover:bg-panel-hover"
                                >
                                    {bulkDeletable && (
                                        <td className="px-4 py-4 pl-6" onClick={(e) => e.stopPropagation()}>
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.has(row.id)}
                                                onChange={() => toggleRow(row.id)}
                                                className="h-4 w-4 rounded border-gray-300 dark:border-edge-strong"
                                            />
                                        </td>
                                    )}
                                    <td className={`px-4 py-4 font-bold text-blue-600 dark:text-accent-text ${bulkDeletable ? '' : 'pl-6'}`}>{row.id}</td>
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
                                    <td colSpan={bulkDeletable ? 8 : 7} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('requester.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">{trans('requester.showing', { shown: filtered.length, total: tickets.length })}</span>
                </div>
            </div>
        </div>
    );
}

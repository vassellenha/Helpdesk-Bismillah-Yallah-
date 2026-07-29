import { useMemo, useState } from 'react';
import { StatusBadge, PriorityBadge } from './StatusBadge';
import SelectMenu from './SelectMenu';
import useLockBodyScroll from '../lib/useLockBodyScroll';

const STATUS_OPTIONS = ['Semua Status', 'Open', 'In Progress', 'Waiting Approval', 'Resolved', 'Closed'];

export default function TicketWorkspace({ tickets = [], categories = [], showAssignee = true }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('Semua Status');
    const [category, setCategory] = useState('Semua Kategori');
    const [selected, setSelected] = useState(null);

    const filtered = useMemo(() => {
        return tickets.filter((t) => {
            const matchesSearch =
                search.trim() === '' ||
                t.title.toLowerCase().includes(search.toLowerCase()) ||
                t.id.toLowerCase().includes(search.toLowerCase()) ||
                t.requester.toLowerCase().includes(search.toLowerCase());
            const matchesStatus = status === 'Semua Status' || t.status === status;
            const matchesCategory = category === 'Semua Kategori' || t.category === category;
            return matchesSearch && matchesStatus && matchesCategory;
        });
    }, [tickets, search, status, category]);

    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                    <svg viewBox="0 0 24 24" fill="none" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-ink-3">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
                        <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
                    </svg>
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        type="text"
                        placeholder="Cari tiket, ID, atau requester..."
                        className="w-full rounded-lg border border-gray-200 dark:border-edge-strong py-2 pl-9 pr-3 text-sm text-gray-700 dark:text-ink-2 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    <SelectMenu
                        value={status}
                        onChange={setStatus}
                        options={STATUS_OPTIONS.map((s) => ({ value: s, label: s }))}
                    />
                    <SelectMenu
                        value={category}
                        onChange={setCategory}
                        options={[{ value: 'Semua Kategori', label: 'Semua Kategori' }, ...categories.map((c) => ({ value: c, label: c }))]}
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                    <thead>
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-4 py-3">Tiket</th>
                            <th className="px-4 py-3">Kategori</th>
                            <th className="px-4 py-3">Prioritas</th>
                            <th className="px-4 py-3">Status</th>
                            {showAssignee && <th className="px-4 py-3">PIC</th>}
                            <th className="px-4 py-3">SLA Due</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                        {filtered.map((t) => (
                            <tr
                                key={t.id}
                                onClick={() => setSelected(t)}
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-medium text-gray-900 dark:text-ink-1">{t.title}</p>
                                    <p className="text-xs text-gray-400 dark:text-ink-3">{t.id} · {t.requester}</p>
                                </td>
                                <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{t.category}</td>
                                <td className="px-4 py-3"><PriorityBadge priority={t.priority} /></td>
                                <td className="px-4 py-3"><StatusBadge status={t.status} /></td>
                                {showAssignee && <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{t.assignee}</td>}
                                <td className="px-4 py-3 text-gray-500 dark:text-ink-2">{t.sla_due}</td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={showAssignee ? 6 : 5} className="px-4 py-10 text-center text-sm text-gray-400 dark:text-ink-3">
                                    Tidak ada tiket yang cocok dengan filter.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="border-t border-gray-100 dark:border-edge px-4 py-3 text-xs text-gray-400 dark:text-ink-3">
                Menampilkan {filtered.length} dari {tickets.length} tiket
            </div>

            {selected && <TicketDetailModal ticket={selected} onClose={() => setSelected(null)} />}
        </div>
    );
}

function TicketDetailModal({ ticket, onClose }) {
    useLockBodyScroll();
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div
                className="w-full max-w-lg overflow-hidden rounded-xl bg-white dark:bg-panel-2 shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-5 py-4">
                    <div>
                        <p className="text-xs font-medium text-gray-400 dark:text-ink-3">{ticket.id}</p>
                        <h3 className="text-base font-semibold text-gray-900 dark:text-ink-1">{ticket.title}</h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-full p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600"
                        aria-label="Tutup"
                    >
                        ✕
                    </button>
                </div>
                <div className="space-y-3 px-5 py-4 text-sm">
                    <div className="flex justify-between"><span className="text-gray-400 dark:text-ink-3">Requester</span><span className="font-medium text-gray-800 dark:text-ink-1">{ticket.requester}</span></div>
                    <div className="flex justify-between"><span className="text-gray-400 dark:text-ink-3">Kategori</span><span className="font-medium text-gray-800 dark:text-ink-1">{ticket.category}</span></div>
                    <div className="flex justify-between items-center"><span className="text-gray-400 dark:text-ink-3">Prioritas</span><PriorityBadge priority={ticket.priority} /></div>
                    <div className="flex justify-between items-center"><span className="text-gray-400 dark:text-ink-3">Status</span><StatusBadge status={ticket.status} /></div>
                    <div className="flex justify-between"><span className="text-gray-400 dark:text-ink-3">PIC</span><span className="font-medium text-gray-800 dark:text-ink-1">{ticket.assignee}</span></div>
                    <div className="flex justify-between"><span className="text-gray-400 dark:text-ink-3">Dibuat</span><span className="font-medium text-gray-800 dark:text-ink-1">{ticket.created_at}</span></div>
                    <div className="flex justify-between"><span className="text-gray-400 dark:text-ink-3">SLA Due</span><span className="font-medium text-gray-800 dark:text-ink-1">{ticket.sla_due}</span></div>
                </div>
                <div className="flex justify-end gap-2 border-t border-gray-100 dark:border-edge px-5 py-3">
                    <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-4 py-2 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        Tutup
                    </button>
                    <button className="rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                        Buka Tiket
                    </button>
                </div>
            </div>
        </div>
    );
}

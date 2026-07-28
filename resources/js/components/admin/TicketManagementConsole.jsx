import { useEffect, useMemo, useRef, useState } from 'react';
import useLockBodyScroll from '../../lib/useLockBodyScroll';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';

const STATUS_STYLES = {
    Draft: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20',
    Returned: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text ring-amber-600/20',
    Open: 'bg-gray-100 dark:bg-panel-3 text-gray-700 dark:text-ink-2 ring-gray-500/20',
    Assigned: 'bg-sky-50 dark:bg-accent-soft text-sky-700 dark:text-accent-text ring-sky-600/20',
    'In Progress': 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-blue-600/20',
    'Waiting for Approval': 'bg-violet-50 dark:bg-violet-soft text-violet-700 dark:text-violet-text ring-violet-600/20',
    'Waiting for Response': 'bg-purple-50 dark:bg-violet-soft text-purple-700 dark:text-violet-text ring-purple-600/20',
    Resolved: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text ring-emerald-600/20',
    Completed: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text ring-emerald-600/20',
    Closed: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20',
    Rejected: 'bg-red-50 dark:bg-bad-soft text-red-700 dark:text-bad-text ring-red-600/20',
};

const PRIORITY_STYLES = {
    Low: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2',
    Medium: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text',
    High: 'bg-orange-50 dark:bg-warn-soft text-orange-700 dark:text-warn-text',
    Critical: 'bg-red-50 dark:bg-bad-soft text-red-700 dark:text-bad-text',
};

const SLA_TEXT_STYLES = {
    ontrack: 'text-gray-700 dark:text-ink-2',
    warning: 'text-amber-600 dark:text-warn-text font-medium',
    breach: 'text-red-600 dark:text-bad-text font-medium',
    met: 'text-emerald-600 dark:text-ok-text',
    'met-late': 'text-amber-600 dark:text-warn-text',
    none: 'text-gray-400 dark:text-ink-3',
};

const PERIODS = ['Semua', 'Hari Ini', '7 Hari', '30 Hari', 'Bulan Ini'];

export default function TicketManagementConsole({ tickets: initialTickets, stats, filterOptions, exportUrl }) {
    const [tickets, setTickets] = useState(initialTickets);
    const [ratingBusy, setRatingBusy] = useState(null); // ticketNo currently saving
    const [search, setSearch] = useState('');
    const [period, setPeriod] = useState('Semua');
    const [statusFilter, setStatusFilter] = useState('Semua Status');
    const [priorityFilter, setPriorityFilter] = useState('Semua Priority');
    const [categoryFilter, setCategoryFilter] = useState('Semua Kategori');
    const [requesterFilter, setRequesterFilter] = useState('Semua Requester');
    const [picFilter, setPicFilter] = useState('Semua PIC');
    const [menu, setMenu] = useState(null); // { ticket, top, left }
    const [detailTicket, setDetailTicket] = useState(null);
    const menuRef = useRef(null);

    useEffect(() => {
        if (!menu) return;
        function onClickOutside(e) {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenu(null);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [menu]);

    const filtered = useMemo(() => {
        const now = new Date();
        return tickets.filter((t) => {
            const q = search.trim().toLowerCase();
            const matchesSearch =
                q === '' ||
                [t.ticketNo, t.service, t.subcategory, t.subject, t.requesterName, t.pic, t.issueCategory]
                    .filter(Boolean)
                    .some((v) => v.toLowerCase().includes(q));

            const matchesStatus = statusFilter === 'Semua Status' || t.status === statusFilter;
            const matchesPriority = priorityFilter === 'Semua Priority' || t.priority === priorityFilter;
            const matchesCategory = categoryFilter === 'Semua Kategori' || t.issueCategory === categoryFilter;
            const matchesRequester = requesterFilter === 'Semua Requester' || t.requesterName === requesterFilter;
            const matchesPic =
                picFilter === 'Semua PIC' || (picFilter === 'Menunggu Assignment' ? !t.pic : t.pic === picFilter);

            const matchesPeriod = period === 'Semua' || withinPeriod(new Date(t.createdAtIso), now, period);

            return matchesSearch && matchesStatus && matchesPriority && matchesCategory && matchesRequester && matchesPic && matchesPeriod;
        });
    }, [tickets, search, statusFilter, priorityFilter, categoryFilter, requesterFilter, picFilter, period]);

    function resetFilters() {
        setSearch('');
        setPeriod('Semua');
        setStatusFilter('Semua Status');
        setPriorityFilter('Semua Priority');
        setCategoryFilter('Semua Kategori');
        setRequesterFilter('Semua Requester');
        setPicFilter('Semua PIC');
    }

    function openMenu(e, ticket) {
        if (menu?.ticket.ticketNo === ticket.ticketNo) {
            setMenu(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        setMenu({ ticket, top: rect.bottom + 4, left: rect.right - 160 });
    }

    function handleExport() {
        const ids = filtered.map((t) => t.ticketNo).join(',');
        window.location.href = `${exportUrl}?ids=${encodeURIComponent(ids)}`;
    }

    // Excludes/re-includes a ticket's rating from every average-rating
    // calculation (Support's own score, Team Lead's agent view, teguran
    // threshold) without deleting it — see toggleRating() on the backend.
    async function toggleRating(ticket) {
        setRatingBusy(ticket.ticketNo);
        try {
            const res = await apiFetch(`/admin/ticket-management/${ticket.ticketNo}/rating-toggle`, { method: 'PATCH' });
            setTickets((prev) => prev.map((t) => (t.ticketNo === ticket.ticketNo ? { ...t, ratingActive: res.ratingActive } : t)));
        } catch (e) {
            alert(e.message || 'Gagal mengubah status rating.');
        } finally {
            setRatingBusy(null);
        }
    }

    return (
        <div>
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900 dark:text-ink-1">Ticket Management</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">Seluruh transaksi tiket Incident, Service Request, dan Role Access.</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <div className="flex items-center rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1 text-sm">
                        <span className="rounded-md bg-blue-50 dark:bg-accent-soft px-3 py-1.5 font-medium text-blue-700 dark:text-accent-text">Administrator</span>
                        <span title="Belum tersedia pada scope saat ini" className="cursor-not-allowed rounded-md px-3 py-1.5 text-gray-300">Team Lead</span>
                        <span title="Belum tersedia pada scope saat ini" className="cursor-not-allowed rounded-md px-3 py-1.5 text-gray-300">Support</span>
                    </div>
                    <button onClick={handleExport} className="rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        ⬇ Export CSV
                    </button>
                </div>
            </div>

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
                <Stat icon={<TicketIcon />} value={stats.total} label="TOTAL TICKET" bg="bg-blue-50 dark:bg-accent-soft" color="text-blue-600 dark:text-accent-text" />
                <Stat icon={<ArchiveIcon />} value={stats.open} label="OPEN" bg="bg-gray-100 dark:bg-panel-3" color="text-gray-600 dark:text-ink-2" />
                <Stat icon={<DotIcon />} value={stats.waitingApproval} label="WAITING APPROVAL" bg="bg-amber-50 dark:bg-warn-soft" color="text-amber-600 dark:text-warn-text" />
                <Stat icon={<GearIcon />} value={stats.inProgress} label="IN PROGRESS" bg="bg-blue-50 dark:bg-accent-soft" color="text-blue-600 dark:text-accent-text" />
                <Stat icon={<CheckIcon />} value={stats.resolvedToday} label="RESOLVED TODAY" bg="bg-emerald-50 dark:bg-ok-soft" color="text-emerald-600 dark:text-ok-text" />
                <Stat icon={<WarningIcon />} value={stats.overSla} label="OVER SLA" bg="bg-red-50 dark:bg-bad-soft" color="text-red-600 dark:text-bad-text" />
            </div>

            <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari Ticket ID, layanan, requester, atau kategori"
                        className="w-full max-w-md rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex items-center rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1 text-sm">
                        {PERIODS.map((p) => (
                            <button
                                key={p}
                                onClick={() => setPeriod(p)}
                                className={`rounded-md px-3 py-1.5 font-medium ${period === p ? 'bg-gray-900 dark:bg-panel-selected text-white' : 'text-gray-500 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                            >
                                {p}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 border-b border-gray-100 dark:border-edge px-4 py-3">
                    <FilterSelect value={statusFilter} onChange={setStatusFilter} allLabel="Semua Status" options={filterOptions.statuses} />
                    <FilterSelect value={priorityFilter} onChange={setPriorityFilter} allLabel="Semua Priority" options={filterOptions.priorities} />
                    <FilterSelect value={categoryFilter} onChange={setCategoryFilter} allLabel="Semua Kategori" options={filterOptions.issueCategories} />
                    <FilterSelect value={requesterFilter} onChange={setRequesterFilter} allLabel="Semua Requester" options={filterOptions.requesters} />
                    <FilterSelect value={picFilter} onChange={setPicFilter} allLabel="Semua PIC" options={[...filterOptions.pics, 'Menunggu Assignment']} />
                    <button onClick={resetFilters} className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">
                        Reset Filter
                    </button>
                </div>

                <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">Menampilkan {filtered.length} dari {tickets.length} ticket</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                        <thead>
                            <tr className="bg-gray-50 dark:bg-panel-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-5 py-3">Ticket ID</th>
                                <th className="px-5 py-3">Layanan</th>
                                <th className="px-5 py-3">Requester</th>
                                <th className="px-5 py-3">Priority</th>
                                <th className="px-5 py-3">PIC</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3">SLA</th>
                                <th className="px-5 py-3">Rating</th>
                                <th className="px-5 py-3">Tanggal</th>
                                <th className="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                            {filtered.map((t) => (
                                <tr key={t.ticketNo} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    <td className="px-5 py-3">
                                        <button onClick={() => setDetailTicket(t)} className="font-semibold text-blue-700 dark:text-accent-text hover:underline">
                                            {t.ticketNo}
                                        </button>
                                    </td>
                                    <td className="px-5 py-3 text-gray-700 dark:text-ink-2">
                                        <p className="font-semibold text-gray-900 dark:text-ink-1">{t.service}</p>
                                        <p>{t.subcategory}</p>
                                        <p className="text-gray-500 dark:text-ink-2">{t.subject}</p>
                                    </td>
                                    <td className="px-5 py-3 text-gray-700 dark:text-ink-2">{t.requesterName}</td>
                                    <td className="px-5 py-3">
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${PRIORITY_STYLES[t.priority] ?? 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2'}`}>
                                            {t.priority}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-gray-700 dark:text-ink-2">
                                        {t.pic ?? <span className="font-medium text-amber-600 dark:text-warn-text">Menunggu Assignment</span>}
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[t.status] ?? 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20'}`}>
                                            {t.status}
                                        </span>
                                    </td>
                                    <td className={`px-5 py-3 ${SLA_TEXT_STYLES[t.sla.kind] ?? 'text-gray-700 dark:text-ink-2'}`}>{t.sla.label}</td>
                                    <td className="px-5 py-3">
                                        <RatingCell ticket={t} busy={ratingBusy === t.ticketNo} onToggle={toggleRating} />
                                    </td>
                                    <td className="px-5 py-3 text-gray-500 dark:text-ink-2">{t.createdAt}</td>
                                    <td className="px-5 py-3 text-right">
                                        <button onClick={(e) => openMenu(e, t)} className="rounded-full border border-gray-200 dark:border-edge-strong px-2.5 py-1 text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-5 py-8 text-center text-gray-400 dark:text-ink-3">Tidak ada ticket yang cocok dengan filter ini.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {menu && (
                <div ref={menuRef} style={{ top: menu.top, left: menu.left }} className="fixed z-50 w-44 overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 text-left shadow-lg">
                    <button
                        onClick={() => { setDetailTicket(menu.ticket); setMenu(null); }}
                        className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                    >
                        <SearchIcon /> Lihat Detail
                    </button>
                </div>
            )}

            {detailTicket && <TicketDetailModal ticket={detailTicket} onClose={() => setDetailTicket(null)} />}
        </div>
    );
}

function withinPeriod(date, now, period) {
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    switch (period) {
        case 'Hari Ini':
            return date >= startOfToday;
        case '7 Hari':
            return date >= new Date(startOfToday.getTime() - 6 * 86400000);
        case '30 Hari':
            return date >= new Date(startOfToday.getTime() - 29 * 86400000);
        case 'Bulan Ini':
            return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
        default:
            return true;
    }
}

function FilterSelect({ value, onChange, allLabel, options }) {
    const opts = useMemo(() => [
        { value: allLabel, label: allLabel },
        ...options.map((o) => ({ value: o, label: o })),
    ], [allLabel, options]);

    return <SelectMenu value={value} onChange={onChange} options={opts} />;
}

// Compact star display + the activate/deactivate toggle for whether this
// ticket's rating counts toward Support/Team Lead's average. A ticket with
// no rating yet (not Closed, or Closed without feedback) shows a dash and
// no toggle — there's nothing to include/exclude.
function RatingCell({ ticket, busy, onToggle }) {
    if (ticket.rating === null) {
        return <span className="text-gray-300 dark:text-ink-3">—</span>;
    }

    return (
        <div className="flex items-center gap-2">
            <span className={`flex items-center gap-0.5 ${ticket.ratingActive ? '' : 'opacity-40 grayscale'}`}>
                {[1, 2, 3, 4, 5].map((n) => (
                    <svg key={n} width="12" height="12" viewBox="0 0 24 24" fill={n <= ticket.rating ? '#f59e0b' : 'none'} stroke={n <= ticket.rating ? '#f59e0b' : '#d1d5db'} strokeWidth="1.6" strokeLinejoin="round">
                        <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z" />
                    </svg>
                ))}
            </span>
            <button
                type="button"
                onClick={(e) => { e.stopPropagation(); onToggle(ticket); }}
                disabled={busy}
                title={ticket.ratingActive ? 'Nonaktifkan dari rata-rata rating' : 'Aktifkan kembali ke rata-rata rating'}
                className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold disabled:cursor-not-allowed disabled:opacity-50 ${ticket.ratingActive ? 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2 hover:bg-gray-200 dark:hover:bg-panel-hover' : 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text'}`}
            >
                {busy ? '…' : ticket.ratingActive ? 'Aktif' : 'Nonaktif'}
            </button>
        </div>
    );
}

function Stat({ icon, value, label, bg, color }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${bg} ${color}`}>{icon}</span>
            <p className="mt-3 text-2xl font-bold text-gray-900 dark:text-ink-1">{value}</p>
            <p className="text-xs font-medium text-gray-400 dark:text-ink-3">{label}</p>
        </div>
    );
}

function TicketDetailModal({ ticket: t, onClose }) {
    useLockBodyScroll();
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-6 py-4">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900 dark:text-ink-1">{t.ticketNo}</h2>
                        <p className="mt-0.5 text-sm text-gray-500 dark:text-ink-2">{[t.service, t.subcategory, t.subject].filter(Boolean).join(' • ')}</p>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600">✕</button>
                </div>

                <div className="overflow-y-auto px-6 py-5">
                    <h3 className="mb-3 text-sm font-bold text-gray-900 dark:text-ink-1">Informasi Ticket</h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid grid-cols-2 gap-3">
                            <Detail label="Ticket ID" value={t.ticketNo} />
                            <Detail label="Kategori" value={t.issueCategory} />
                            <Detail label="Layanan" value={[t.service, t.subcategory, t.subject].filter(Boolean).join(' • ')} span />
                            <Detail label="Requester" value={t.requesterName} />
                            <Detail label="Tanggal" value={t.createdAt} />
                            <Detail label="Priority" value={<PriorityBadge priority={t.priority} />} />
                            <Detail label="SLA" value={<span className={SLA_TEXT_STYLES[t.sla.kind]}>{t.sla.label}</span>} />
                            <Detail label="Status" value={<StatusBadge status={t.status} />} />
                        </div>
                        <div className="rounded-lg bg-gray-50 dark:bg-panel-3 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Status Tiket</p>
                            <div className="mt-2"><StatusBadge status={t.status} /></div>
                            <p className="mt-2 text-xs text-gray-500 dark:text-ink-2">
                                Assignment PIC dilakukan oleh Team Lead domain terkait — Administrator hanya dapat memantau.
                            </p>

                            <p className="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Timeline</p>
                            <ol className="mt-2 flex flex-col">
                                {t.timeline.map((step, i) => (
                                    <li key={i} className="flex gap-3">
                                        <div className="flex flex-col items-center">
                                            <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${step.state === 'done' ? 'bg-emerald-500' : step.state === 'current' ? 'bg-blue-500' : step.state === 'rejected' ? 'bg-red-500' : 'bg-gray-300'}`} />
                                            {i < t.timeline.length - 1 && <span className="w-px flex-1 bg-gray-200" />}
                                        </div>
                                        <div className={`pb-4 text-sm ${step.state === 'pending' ? 'opacity-50' : ''}`}>
                                            <p className="font-semibold text-gray-800 dark:text-ink-1">{step.label}</p>
                                            {step.who && <p className="text-[11px] text-gray-400 dark:text-ink-3">{step.who}</p>}
                                            {step.at && <p className="text-[11px] text-gray-400 dark:text-ink-3">{step.at}</p>}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>

                    <h3 className="mb-3 mt-6 text-sm font-bold text-gray-900 dark:text-ink-1">Informasi Requester</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <Detail label="Nama" value={t.requester?.name ?? '—'} />
                        <Detail label="NIK" value={t.requester?.nik ?? '—'} />
                        <Detail label="Email" value={t.requester?.email ?? '—'} />
                        <Detail label="Jabatan" value={t.requester?.jabatan ?? '—'} />
                        <Detail label="Unit Kerja" value={t.requester?.unit ?? '—'} span />
                    </div>

                    <h3 className="mb-2 mt-6 text-sm font-bold text-gray-900 dark:text-ink-1">Deskripsi</h3>
                    <p className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3 text-sm text-gray-700 dark:text-ink-2">{t.description || '—'}</p>

                    {t.attachmentName && (
                        <>
                            <h3 className="mb-2 mt-6 text-sm font-bold text-gray-900 dark:text-ink-1">Lampiran</h3>
                            {t.attachmentUrl ? (
                                <a href={t.attachmentUrl} className="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-edge-strong p-3 text-sm text-blue-700 dark:text-accent-text hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    📎 {t.attachmentName}
                                </a>
                            ) : (
                                <p className="rounded-lg border border-gray-200 dark:border-edge-strong p-3 text-sm text-gray-500 dark:text-ink-2">📎 {t.attachmentName}</p>
                            )}
                        </>
                    )}

                    <h3 className="mb-2 mt-6 text-sm font-bold text-gray-900 dark:text-ink-1">Informasi Approval</h3>
                    <p className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3 text-sm text-gray-700 dark:text-ink-2">{t.approvalInfo}</p>

                    <h3 className="mb-3 mt-6 text-sm font-bold text-gray-900 dark:text-ink-1">Penanganan</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <Detail label="PIC Utama" value={t.pic ?? 'Menunggu Assignment'} />
                        <Detail label="Status" value={<StatusBadge status={t.status} />} />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">Tutup</button>
                </div>
            </div>
        </div>
    );
}

function Detail({ label, value, span }) {
    return (
        <div className={`rounded-lg bg-gray-50 dark:bg-panel-3 p-3 ${span ? 'col-span-2' : ''}`}>
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-ink-1">{value}</p>
        </div>
    );
}

function StatusBadge({ status }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[status] ?? 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20'}`}>{status}</span>;
}

function PriorityBadge({ priority }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${PRIORITY_STYLES[priority] ?? 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2'}`}>{priority}</span>;
}

function SearchIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400 dark:text-ink-3">
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function TicketIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4">
            <path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a1.5 1.5 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a1.5 1.5 0 0 0 0-4Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
        </svg>
    );
}

function ArchiveIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4">
            <rect x="3.5" y="4" width="17" height="4" rx="1" stroke="currentColor" strokeWidth="1.6" />
            <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8M10 12h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function DotIcon() {
    return (
        <svg viewBox="0 0 24 24" className="h-4 w-4">
            <circle cx="12" cy="12" r="6" fill="currentColor" />
        </svg>
    );
}

function GearIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4">
            <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.6" />
            <path d="M12 3v2m0 14v2m9-9h-2M5 12H3m14.95-6.95-1.41 1.41M7.46 16.54l-1.41 1.41m0-11.9 1.41 1.41m9.08 9.08 1.41 1.41" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.6" />
            <path d="m8 12 3 3 5-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function WarningIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4">
            <path d="M12 4 3 20h18Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
            <path d="M12 10v4m0 3h.01" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

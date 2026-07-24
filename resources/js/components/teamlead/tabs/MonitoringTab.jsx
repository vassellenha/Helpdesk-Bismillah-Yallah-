import { useEffect, useMemo, useRef, useState } from 'react';
import { StatusBadge, PriorityBadge } from '../../StatusBadge';

const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];
const TYPES = ['Incident', 'Service Request', 'Access Request'];
const PAGE_SIZE = 10;

const TYPE_BADGE = {
    Incident: 'bg-red-50 text-red-600',
    'Service Request': 'bg-blue-50 text-blue-700',
    'Access Request': 'bg-amber-50 text-amber-700',
};

function fmtAge(iso) {
    const mins = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
    if (mins >= 1440) return `${Math.floor(mins / 1440)}h ${Math.floor((mins % 1440) / 60)}j lalu`;
    if (mins >= 60) return `${Math.floor(mins / 60)}j ${mins % 60}m lalu`;
    return `${mins}m lalu`;
}

// Live per-second SLA countdown from the server's minutes-remaining snapshot.
function liveSla(slaMinutes, elapsedSec) {
    if (slaMinutes === null || slaMinutes === undefined) return null;
    const total = Math.round(slaMinutes * 60) - elapsedSec;
    const a = Math.abs(total);
    const h = Math.floor(a / 3600);
    const m = Math.floor((a % 3600) / 60);
    const s = a % 60;
    const pad = (n) => (n < 10 ? '0' : '') + n;
    const body = h > 0 ? `${h}j ${pad(m)}m` : `${m}m ${pad(s)}s`;
    return { overdue: total < 0, text: total < 0 ? `Terlambat ${body}` : `${body} lagi` };
}

function Chip({ active, onClick, children }) {
    return (
        <button onClick={onClick} className={`rounded-full px-3 py-1.5 text-[12px] font-semibold transition ${active ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-300' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'}`}>
            {children}
        </button>
    );
}

export default function MonitoringTab({ monitorRows = [], actions = {}, remindUrlBase }) {
    const [rows, setRows] = useState(monitorRows);
    const [query, setQuery] = useState('');
    const [sortNB, setSortNB] = useState(false);
    const [live, setLive] = useState(true);
    const [warnOpen, setWarnOpen] = useState(true);
    const [filterOpen, setFilterOpen] = useState(false);
    const [f, setF] = useState({ priority: 'Semua', status: 'Semua', type: 'Semua', subcat: 'Semua', app: 'Semua', unit: 'Semua' });
    const [appQuery, setAppQuery] = useState('');
    const [page, setPage] = useState(1);
    const mount = useRef(Date.now());
    const [, setTick] = useState(0);
    const filterRef = useRef(null);

    useEffect(() => {
        if (!live) return undefined;
        const id = setInterval(() => setTick((t) => t + 1), 1000);
        return () => clearInterval(id);
    }, [live]);

    useEffect(() => {
        function onOutside(e) { if (filterRef.current && !filterRef.current.contains(e.target)) setFilterOpen(false); }
        document.addEventListener('mousedown', onOutside);
        return () => document.removeEventListener('mousedown', onOutside);
    }, []);

    const elapsed = live ? Math.floor((Date.now() - mount.current) / 1000) : 0;

    const subcats = useMemo(() => [...new Set(rows.map((r) => r.subcategory).filter((x) => x && x !== '—'))].sort(), [rows]);
    const apps = useMemo(() => [...new Set(rows.map((r) => r.service).filter((x) => x && x !== '—'))].sort(), [rows]);
    const units = useMemo(() => [...new Set(rows.map((r) => r.unit).filter((x) => x && x !== '—'))].sort(), [rows]);
    const statuses = useMemo(() => [...new Set(rows.map((r) => r.status))].sort(), [rows]);

    const warnTickets = useMemo(
        () => rows.filter((r) => ['Critical', 'High'].includes(r.priority) && r.slaMinutes !== null && r.slaMinutes < 30),
        [rows],
    );

    const activeFilters = Object.values(f).filter((v) => v !== 'Semua').length;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        let out = rows.filter((r) => {
            if (f.priority !== 'Semua' && r.priority !== f.priority) return false;
            if (f.status !== 'Semua' && r.status !== f.status) return false;
            if (f.type !== 'Semua' && r.type !== f.type) return false;
            if (f.subcat !== 'Semua' && r.subcategory !== f.subcat) return false;
            if (f.app !== 'Semua' && r.service !== f.app) return false;
            if (f.unit !== 'Semua' && r.unit !== f.unit) return false;
            if (q && !`${r.id} ${r.subject} ${r.service} ${r.agent}`.toLowerCase().includes(q)) return false;
            return true;
        });
        if (sortNB) out = [...out].sort((a, b) => (a.slaMinutes ?? 1e9) - (b.slaMinutes ?? 1e9));
        return out;
    }, [rows, f, query, sortNB]);

    // Kembali ke halaman 1 setiap kali hasil filter berubah, biar tidak nyangkut di halaman kosong.
    useEffect(() => { setPage(1); }, [f, query, sortNB]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const safePage = Math.min(page, totalPages);
    const paged = filtered.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE);
    const startIdx = filtered.length === 0 ? 0 : (safePage - 1) * PAGE_SIZE + 1;
    const endIdx = Math.min(safePage * PAGE_SIZE, filtered.length);

    function patch(id, fields) { setRows((prev) => prev.map((r) => (r.id === id ? { ...r, ...fields } : r))); }
    const setFilter = (key, val) => setF((prev) => ({ ...prev, [key]: val }));

    return (
        <div className="flex flex-col gap-5">
            {warnTickets.length > 0 && warnOpen && (
                <div className="flex items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg>
                    </span>
                    <div className="min-w-[200px]">
                        <p className="text-sm font-bold text-amber-800">SLA Warning — {warnTickets.length} tiket kritis mendekati batas waktu</p>
                        <p className="mt-0.5 text-[12.5px] text-amber-700">Tiket Critical / High dengan sisa waktu &lt; 30 menit. Segera tinjau atau alihkan tugas.</p>
                    </div>
                    <div className="flex flex-1 flex-wrap items-center gap-1.5">
                        {warnTickets.slice(0, 12).map((t) => (
                            <button key={t.id} onClick={() => actions.openTicket?.(t.id)} className="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-600 hover:text-white">{t.id}</button>
                        ))}
                    </div>
                    <button onClick={() => setWarnOpen(false)} className="shrink-0 rounded-lg p-1 text-amber-600 hover:bg-amber-100"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12 M18 6 6 18"/></svg></button>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="relative flex-1 min-w-[240px] max-w-md" ref={filterRef}>
                    <button onClick={() => setFilterOpen((v) => !v)} className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-[13px] font-bold transition ${activeFilters || filterOpen ? 'bg-blue-50 text-blue-700' : 'bg-white text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50'}`}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 6h16 M7 12h10 M10 18h4"/></svg>
                        {activeFilters ? `Filter · ${activeFilters}` : 'Filter'}
                    </button>
                    {filterOpen && (
                        <div className="absolute left-0 top-12 z-30 flex w-[400px] max-w-[92vw] flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-xl">
                            <FilterGroup label="Priority" value={f.priority} options={PRIORITIES} onSelect={(v) => setFilter('priority', v)} />
                            <FilterGroup label="Status" value={f.status} options={statuses} onSelect={(v) => setFilter('status', v)} />
                            <FilterGroup label="Jenis Tiket" value={f.type} options={TYPES} onSelect={(v) => setFilter('type', v)} />
                            <FilterGroup label="Sub-Kategori" value={f.subcat} options={subcats} onSelect={(v) => setFilter('subcat', v)} scroll />
                            <div className="flex flex-col gap-2">
                                <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">Aplikasi · {apps.length}</p>
                                <input value={appQuery} onChange={(e) => setAppQuery(e.target.value)} placeholder="Ketik nama aplikasi…" className="rounded-lg border border-gray-200 px-3 py-2 text-[12.5px] outline-none focus:border-blue-400" />
                                <div className="flex max-h-[150px] flex-col gap-0.5 overflow-y-auto">
                                    {['Semua', ...apps.filter((a) => a.toLowerCase().includes(appQuery.toLowerCase()))].map((a) => (
                                        <button key={a} onClick={() => setFilter('app', a)} className={`flex items-center justify-between rounded-lg px-3 py-2 text-left text-[12.5px] ${f.app === a ? 'bg-blue-50 font-bold text-blue-700' : 'font-medium text-gray-700 hover:bg-gray-50'}`}>
                                            {a === 'Semua' ? 'Semua Aplikasi' : a}
                                            {f.app === a && <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l4 4 10-11"/></svg>}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <FilterGroup label="Unit Kerja" value={f.unit} options={units} onSelect={(v) => setFilter('unit', v)} scroll />
                            <button onClick={() => { setF({ priority: 'Semua', status: 'Semua', type: 'Semua', subcat: 'Semua', app: 'Semua', unit: 'Semua' }); setAppQuery(''); }} className="self-start text-[12.5px] font-bold text-blue-600 hover:text-blue-800">Reset semua filter</button>
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2.5">
                    <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari ID, subjek, PIC…" className="w-52 rounded-xl border border-gray-200 px-3.5 py-2.5 text-[13px] text-gray-700 outline-none focus:border-blue-400" />
                    <button onClick={() => setLive((v) => !v)} className={`flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12.5px] font-bold ${live ? 'bg-emerald-50 text-emerald-600' : 'bg-white text-gray-500 shadow-sm ring-1 ring-gray-200'}`}>
                        <span className={`h-2 w-2 rounded-full ${live ? 'bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.2)]' : 'bg-gray-400'}`} />
                        {live ? 'Live · tiap detik' : 'Dijeda'}
                    </button>
                    <button onClick={() => setSortNB((v) => !v)} className={`flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12.5px] font-bold ${sortNB ? 'bg-blue-50 text-blue-700' : 'bg-white text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50'}`}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h16 M6 12h12 M9 17h6"/></svg>
                        {sortNB ? 'Sorted: Nearest Breach' : 'Sort by Nearest Breach'}
                    </button>
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1040px] text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                <th className="px-4 py-3.5 pl-6 text-left">ID Tiket</th>
                                <th className="px-4 py-3.5 text-left">Layanan</th>
                                <th className="px-4 py-3.5 text-left">Sub-Kategori</th>
                                <th className="px-4 py-3.5 text-left">Subjek</th>
                                <th className="px-4 py-3.5 text-left">Prioritas</th>
                                <th className="px-4 py-3.5 text-left">Waktu Buat</th>
                                <th className="px-4 py-3.5 text-left">Sisa SLA</th>
                                <th className="px-4 py-3.5 text-left">Status · PIC</th>
                                <th className="px-4 py-3.5 pr-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paged.map((row) => {
                                const sla = liveSla(row.slaMinutes, elapsed);
                                const slaColor = !sla ? 'text-gray-400' : sla.overdue ? 'text-red-600' : row.slaKind === 'warning' || (row.slaMinutes ?? 99) < 30 ? 'text-amber-600' : 'text-emerald-600';
                                const dot = !sla ? 'bg-gray-300' : sla.overdue ? 'bg-red-500' : row.slaKind === 'warning' || (row.slaMinutes ?? 99) < 30 ? 'bg-amber-500' : 'bg-emerald-500';
                                return (
                                    <tr key={row.id} className="border-b border-gray-50 last:border-0 hover:bg-blue-50/30">
                                        <td className="px-4 py-4 pl-6"><button onClick={() => actions.openTicket?.(row.id)} className="font-bold text-blue-600 hover:underline">{row.id}</button></td>
                                        <td className="px-4 py-4"><span className="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-bold text-gray-600">{row.service}</span></td>
                                        <td className="px-4 py-4 text-[11.5px] text-gray-400">{row.subcategory}</td>
                                        <td className="px-4 py-4">
                                            <p className="max-w-[190px] truncate text-[13px] font-semibold text-gray-900">{row.subject}</p>
                                            <span className={`mt-1 inline-block rounded-full px-1.5 py-0.5 text-[9px] font-bold ${TYPE_BADGE[row.type] ?? TYPE_BADGE.Incident}`}>{row.type}</span>
                                        </td>
                                        <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                        <td className="px-4 py-4"><span className="flex items-center gap-1.5 text-[12px] text-gray-500"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2"/></svg>{fmtAge(row.createdAt)}</span></td>
                                        <td className="px-4 py-4">
                                            {sla ? (
                                                <span className={`flex items-center gap-1.5 text-[12.5px] font-bold tabular-nums ${slaColor}`}><span className={`h-1.5 w-1.5 rounded-full ${dot}`} />{sla.text}</span>
                                            ) : <span className="text-[12px] text-gray-400">{row.sla}</span>}
                                        </td>
                                        <td className="px-4 py-4">
                                            <StatusBadge status={row.status} />
                                            <p className="mt-1 text-[11px] text-gray-400">{row.agent}</p>
                                        </td>
                                        <td className="px-4 py-4 pr-6 text-right">
                                            <button onClick={() => actions.reassign?.(row, (res) => patch(row.id, { agent: res.agent.name, agentId: res.agent.id }))} className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12px] font-bold text-blue-600 ring-1 ring-blue-300 hover:bg-blue-50">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 8h13 M16 5l4 3-4 3 M17 16H4 M8 13l-4 3 4 3"/></svg>
                                                Alihkan
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {filtered.length === 0 && <tr><td colSpan={9} className="px-5 py-12 text-center text-sm text-gray-400">Tidak ada tiket dengan filter ini.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-3">
                    <p className="text-xs text-gray-500">{startIdx}–{endIdx} dari {filtered.length} tiket</p>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={safePage <= 1} className="flex items-center gap-1.5 rounded-lg bg-gray-100 px-3.5 py-2 text-[12.5px] font-bold text-gray-700 transition hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-40">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                            Sebelumnya
                        </button>
                        <span className="px-1 text-[12.5px] font-semibold text-gray-500">Hal {safePage} / {totalPages}</span>
                        <button onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={safePage >= totalPages} className="flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-[12.5px] font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                            Berikutnya
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function FilterGroup({ label, value, options, onSelect, scroll }) {
    return (
        <div className="flex flex-col gap-2">
            <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{label}</p>
            <div className={`flex flex-wrap gap-2 ${scroll ? 'max-h-[110px] overflow-y-auto' : ''}`}>
                <Chip active={value === 'Semua'} onClick={() => onSelect('Semua')}>Semua</Chip>
                {options.map((o) => <Chip key={o} active={value === o} onClick={() => onSelect(o)}>{o}</Chip>)}
            </div>
        </div>
    );
}

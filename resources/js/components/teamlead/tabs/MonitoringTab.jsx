import { useEffect, useMemo, useRef, useState } from 'react';
import { StatusBadge, PriorityBadge } from '../../StatusBadge';
import SelectMenu from '../../SelectMenu';
import { t as trans } from '../../../lib/i18n';

// Language-independent sentinel for 'no filter' — a translated word here would
// stop matching real data values the moment the locale changes.
const ALL = '__all';

const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];
const TYPES = ['Incident', 'Service Request', 'Access Request'];
const PAGE_SIZE = 10;

const TYPE_BADGE = {
    Incident: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text',
    'Service Request': 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text',
    'Access Request': 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text',
};

function fmtAge(iso) {
    const mins = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
    if (mins >= 1440) return trans('teamlead.monitoring.ago_days', { d: Math.floor(mins / 1440), h: Math.floor((mins % 1440) / 60) });
    if (mins >= 60) return trans('teamlead.monitoring.ago_hours', { h: Math.floor(mins / 60), m: mins % 60 });
    return trans('teamlead.monitoring.ago_mins', { m: mins });
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
    return { overdue: total < 0, text: total < 0 ? trans('teamlead.monitoring.overdue', { time: body }) : trans('teamlead.monitoring.remaining', { time: body }) };
}

export default function MonitoringTab({ monitorRows = [], monitorFilters = {}, actions = {}, remindUrlBase, monitorFilter = null }) {
    const [rows, setRows] = useState(monitorRows);
    const [query, setQuery] = useState('');
    const [sortNB, setSortNB] = useState(false);
    const [live, setLive] = useState(true);
    const [warnOpen, setWarnOpen] = useState(true);
    const [f, setF] = useState({ priority: ALL, status: ALL, type: ALL, subcat: ALL, app: ALL, unit: ALL, pic: ALL });
    const [page, setPage] = useState(1);
    const mount = useRef(Date.now());
    const [, setTick] = useState(0);

    // Sync when the parent refetches (e.g. after a corrective action) so the
    // list reflects fresh priorities/PICs without a page reload.
    useEffect(() => { setRows(monitorRows); }, [monitorRows]);

    useEffect(() => {
        if (!live) return undefined;
        const id = setInterval(() => setTick((t) => t + 1), 1000);
        return () => clearInterval(id);
    }, [live]);

    function resetFilters() {
        setF({ priority: ALL, status: ALL, type: ALL, subcat: ALL, app: ALL, unit: ALL, pic: ALL });
        actions.clearMonitorFilter?.();
    }

    const elapsed = live ? Math.floor((Date.now() - mount.current) / 1000) : 0;

    // Option lists come from the server (every scoped ticket in the period), not
    // from the rows on screen — otherwise a filter value disappears the moment no
    // visible ticket happens to carry it. Falls back to deriving from rows so the
    // tab still works if an older payload arrives without monitorFilters.
    const derive = (key) => [...new Set(rows.map((r) => r[key]).filter((x) => x && x !== '—'))].sort();
    const subcats = useMemo(() => monitorFilters.subcats ?? derive('subcategory'), [monitorFilters, rows]);
    const apps = useMemo(() => monitorFilters.apps ?? derive('service'), [monitorFilters, rows]);
    const units = useMemo(() => monitorFilters.units ?? derive('unit'), [monitorFilters, rows]);
    const statuses = useMemo(() => monitorFilters.statuses ?? derive('status'), [monitorFilters, rows]);
    const priorities = monitorFilters.priorities ?? PRIORITIES;
    const types = monitorFilters.types ?? TYPES;
    const pics = useMemo(() => monitorFilters.pics ?? derive('agent'), [monitorFilters, rows]);

    const warnTickets = useMemo(
        () => rows.filter((r) => ['Critical', 'High'].includes(r.priority) && r.slaMinutes !== null && r.slaMinutes < 30),
        [rows],
    );

    const activeFilters = Object.values(f).filter((v) => v !== ALL).length;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        let out = rows.filter((r) => {
            if (f.priority !== ALL && r.priority !== f.priority) return false;
            if (f.status !== ALL && r.status !== f.status) return false;
            if (f.type !== ALL && r.type !== f.type) return false;
            if (f.subcat !== ALL && r.subcategory !== f.subcat) return false;
            if (f.app !== ALL && r.service !== f.app) return false;
            if (f.unit !== ALL && r.unit !== f.unit) return false;
            if (f.pic !== ALL && r.agent !== f.pic) return false;
            if (monitorFilter && !monitorFilter.statuses.includes(r.status)) return false;
            if (q && !`${r.id} ${r.subject} ${r.service} ${r.agent}`.toLowerCase().includes(q)) return false;
            return true;
        });
        if (sortNB) out = [...out].sort((a, b) => (a.slaMinutes ?? 1e9) - (b.slaMinutes ?? 1e9));
        return out;
    }, [rows, f, monitorFilter, query, sortNB]);

    // Kembali ke halaman 1 setiap kali hasil filter berubah, biar tidak nyangkut di halaman kosong.
    useEffect(() => { setPage(1); }, [f, query, sortNB]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const safePage = Math.min(page, totalPages);
    const paged = filtered.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE);
    const startIdx = filtered.length === 0 ? 0 : (safePage - 1) * PAGE_SIZE + 1;
    const endIdx = Math.min(safePage * PAGE_SIZE, filtered.length);

    function patch(id, fields) { setRows((prev) => prev.map((r) => (r.id === id ? { ...r, ...fields } : r))); }
    // Picking a filter by hand overrides the dashboard card's filter rather
    // than stacking with it — same rule as every other list page here.
    const setFilter = (key, val) => {
        setF((prev) => ({ ...prev, [key]: val }));
        if (monitorFilter) actions.clearMonitorFilter?.();
    };

    return (
        <div className="flex flex-col gap-5">
            {monitorFilter && (
                <div className="flex items-center justify-between gap-3 rounded-2xl border border-blue-200 dark:border-edge-strong bg-blue-50 dark:bg-accent-soft px-5 py-3 text-[13px]">
                    <span className="font-semibold text-blue-800 dark:text-accent-text">
                        Menampilkan tiket dari kartu dashboard{monitorFilter.label ? `: ${monitorFilter.label}` : ''}
                    </span>
                    <button type="button" onClick={() => actions.clearMonitorFilter?.()} className="font-bold text-blue-700 dark:text-accent-text hover:underline">
                        Tampilkan semua
                    </button>
                </div>
            )}

            {warnTickets.length > 0 && warnOpen && (
                <div className="flex items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 dark:bg-warn-soft px-5 py-4">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-panel-2 text-amber-600 dark:text-warn-text shadow-sm">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg>
                    </span>
                    <div className="min-w-[200px]">
                        <p className="text-sm font-bold text-amber-800">{trans('teamlead.monitoring.warn_title', { count: warnTickets.length })}</p>
                        <p className="mt-0.5 text-[12.5px] text-amber-700 dark:text-warn-text">{trans('teamlead.monitoring.warn_body')}</p>
                    </div>
                    <div className="flex flex-1 flex-wrap items-center gap-1.5">
                        {warnTickets.slice(0, 12).map((t) => (
                            <button key={t.id} onClick={() => actions.openTicket?.(t.id)} className="rounded-full bg-white dark:bg-panel-2 px-2.5 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-600 hover:text-white">{t.id}</button>
                        ))}
                    </div>
                    <button onClick={() => setWarnOpen(false)} className="shrink-0 rounded-lg p-1 text-amber-600 dark:text-warn-text hover:bg-amber-100 dark:hover:bg-warn-soft"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12 M18 6 6 18"/></svg></button>
                </div>
            )}

            {/* Filter bar sejajar dengan Admin Ticket Management: satu baris dropdown
                yang selalu terlihat, bukan popover — jumlah filter aktif dan
                pilihannya bisa dibaca tanpa membuka apa pun dulu. */}
            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={trans('teamlead.monitoring.search')}
                        className="w-full max-w-md rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400"
                    />
                    <div className="flex shrink-0 items-center gap-2.5">
                        <button onClick={() => setLive((v) => !v)} className={`flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12.5px] font-bold ${live ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text' : 'bg-white dark:bg-panel-2 text-gray-500 dark:text-ink-2 shadow-sm ring-1 ring-gray-200 dark:ring-edge-strong'}`}>
                            <span className={`h-2 w-2 rounded-full ${live ? 'bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.2)]' : 'bg-gray-400'}`} />
                            {live ? trans('teamlead.monitoring.live') : trans('teamlead.monitoring.paused')}
                        </button>
                        <button onClick={() => setSortNB((v) => !v)} className={`flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[12.5px] font-bold ${sortNB ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'bg-white dark:bg-panel-2 text-gray-700 dark:text-ink-2 shadow-sm ring-1 ring-gray-200 dark:ring-edge-strong hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h16 M6 12h12 M9 17h6"/></svg>
                            {sortNB ? trans('teamlead.monitoring.sorted_nearest') : trans('teamlead.monitoring.sort_nearest')}
                        </button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 px-4 py-3">
                    <FilterSelect value={f.priority} onChange={(v) => setFilter('priority', v)} allLabel={trans('teamlead.monitoring.all_priority')} options={priorities} />
                    <FilterSelect value={f.status} onChange={(v) => setFilter('status', v)} allLabel={trans('teamlead.monitoring.all_status')} options={statuses} />
                    <FilterSelect value={f.type} onChange={(v) => setFilter('type', v)} allLabel={trans('teamlead.monitoring.all_type')} options={types} />
                    <FilterSelect value={f.subcat} onChange={(v) => setFilter('subcat', v)} allLabel={trans('teamlead.monitoring.all_subcategory')} options={subcats} />
                    <FilterSelect value={f.app} onChange={(v) => setFilter('app', v)} allLabel={trans('teamlead.common.all_app')} options={apps} />
                    <FilterSelect value={f.unit} onChange={(v) => setFilter('unit', v)} allLabel={trans('teamlead.monitoring.all_unit')} options={units} />
                    <FilterSelect value={f.pic} onChange={(v) => setFilter('pic', v)} allLabel={trans('teamlead.monitoring.all_pic')} options={pics} />
                    {activeFilters > 0 && (
                        <button onClick={resetFilters} className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">
                            {trans('teamlead.monitoring.reset_all')}
                        </button>
                    )}
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1040px] text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3.5 pl-6 text-left">{trans('teamlead.columns.ticket_id')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.service')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.subcategory')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.subject')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.priority')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.monitoring.created_at')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.monitoring.sla_left')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.monitoring.status_pic')}</th>
                                <th className="px-4 py-3.5 pr-6 text-right">{trans('teamlead.common.action')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paged.map((row) => {
                                const sla = liveSla(row.slaMinutes, elapsed);
                                const slaColor = !sla ? 'text-gray-400 dark:text-ink-3' : sla.overdue ? 'text-red-600 dark:text-bad-text' : row.slaKind === 'warning' || (row.slaMinutes ?? 99) < 30 ? 'text-amber-600 dark:text-warn-text' : 'text-emerald-600 dark:text-ok-text';
                                const dot = !sla ? 'bg-gray-300' : sla.overdue ? 'bg-red-500' : row.slaKind === 'warning' || (row.slaMinutes ?? 99) < 30 ? 'bg-amber-500' : 'bg-emerald-500';
                                return (
                                    <tr key={row.id} onClick={() => actions.openTicket?.(row.id)} className="group cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/30 dark:hover:bg-panel-hover">
                                        <td className="px-4 py-4 pl-6"><span className="font-bold text-blue-600 dark:text-accent-text group-hover:underline">{row.id}</span></td>
                                        <td className="px-4 py-4"><span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-1 text-[11px] font-bold text-gray-600 dark:text-ink-2">{row.service}</span></td>
                                        <td className="px-4 py-4 text-[11.5px] text-gray-400 dark:text-ink-3">{row.subcategory}</td>
                                        <td className="px-4 py-4">
                                            <p className="max-w-[190px] truncate text-[13px] font-semibold text-gray-900 dark:text-ink-1">{row.subject}</p>
                                            <span className={`mt-1 inline-block rounded-full px-1.5 py-0.5 text-[9px] font-bold ${TYPE_BADGE[row.type] ?? TYPE_BADGE.Incident}`}>{row.type}</span>
                                        </td>
                                        <td className="px-4 py-4"><PriorityBadge priority={row.priority} /></td>
                                        <td className="px-4 py-4"><span className="flex items-center gap-1.5 text-[12px] text-gray-500 dark:text-ink-2"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2"/></svg>{fmtAge(row.createdAt)}</span></td>
                                        <td className="px-4 py-4">
                                            {sla ? (
                                                <span className={`flex items-center gap-1.5 text-[12.5px] font-bold tabular-nums ${slaColor}`}><span className={`h-1.5 w-1.5 rounded-full ${dot}`} />{sla.text}</span>
                                            ) : <span className="text-[12px] text-gray-400 dark:text-ink-3">{row.sla}</span>}
                                        </td>
                                        <td className="px-4 py-4">
                                            <StatusBadge status={row.status} />
                                            <p className="mt-1 text-[11px] text-gray-400 dark:text-ink-3">{row.agent ?? trans('teamlead.monitoring.unassigned')}</p>
                                        </td>
                                        <td className="px-4 py-4 pr-6 text-right">
                                            <button onClick={(e) => { e.stopPropagation(); actions.reassign?.(row, (res) => patch(row.id, { agent: res.agent.name, agentId: res.agent.id })); }} className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12px] font-bold text-blue-600 dark:text-accent-text ring-1 ring-blue-300 hover:bg-blue-50 dark:hover:bg-panel-hover">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 8h13 M16 5l4 3-4 3 M17 16H4 M8 13l-4 3 4 3"/></svg>
                                                {trans('teamlead.monitoring.reassign')}
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {filtered.length === 0 && <tr><td colSpan={9} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.monitoring.empty')}</td></tr>}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-5 py-3">
                    <p className="text-xs text-gray-500 dark:text-ink-2">{trans('teamlead.monitoring.showing', { from: startIdx, to: endIdx, total: filtered.length })}</p>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={safePage <= 1} className="flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-panel-3 px-3.5 py-2 text-[12.5px] font-bold text-gray-700 dark:text-ink-2 transition hover:bg-gray-200 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-40">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                            {trans('teamlead.monitoring.prev')}
                        </button>
                        <span className="px-1 text-[12.5px] font-semibold text-gray-500 dark:text-ink-2">{trans('teamlead.monitoring.page', { page: safePage, total: totalPages })}</span>
                        <button onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={safePage >= totalPages} className="flex items-center gap-1.5 rounded-lg bg-blue-600 dark:bg-blue-500 px-3.5 py-2 text-[12.5px] font-bold text-white transition hover:bg-blue-700 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-40">
                            {trans('teamlead.monitoring.next')}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Dropdown filter dengan opsi "Semua ..." di posisi pertama. Nilai sentinel
 * ALL sengaja tidak diterjemahkan — ia dibandingkan dengan nilai asli dari
 * server, bukan ditampilkan.
 */
function FilterSelect({ value, onChange, allLabel, options }) {
    const opts = useMemo(
        () => [{ value: ALL, label: allLabel }, ...options.map((o) => ({ value: o, label: o }))],
        [allLabel, options],
    );

    return <SelectMenu value={value} onChange={onChange} options={opts} />;
}

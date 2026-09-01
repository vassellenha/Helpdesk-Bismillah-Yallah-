import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import useLockBodyScroll from '../../lib/useLockBodyScroll';
import { t as trans } from '../../lib/i18n';

const TABS = [['sub', 'teamlead.reassign.same_subcategory'], ['app', 'teamlead.reassign.same_app'], ['all', 'teamlead.reassign.all_app']];

const STAR_PATH = 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z';

function RatingBadge({ rating, count, size = 12 }) {
    if (rating === null || rating === undefined) {
        return <span className="text-[15px] font-extrabold text-gray-300">—</span>;
    }
    return (
        <span className="flex items-center gap-1" title={`${count} ulasan`}>
            <svg width={size} height={size} viewBox="0 0 24 24" fill="#f59e0b"><path d={STAR_PATH} /></svg>
            <span className="text-[15px] font-extrabold text-gray-900 dark:text-ink-1">{rating.toFixed(1)}</span>
        </span>
    );
}

function loadState(load) {
    if (load <= 5) return { labelKey: 'teamlead.reassign.load_light', text: 'text-emerald-600 dark:text-ok-text', bar: 'bg-emerald-500', pill: 'bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text' };
    if (load <= 9) return { labelKey: 'teamlead.reassign.load_medium', text: 'text-amber-600 dark:text-warn-text', bar: 'bg-amber-500', pill: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text' };
    return { labelKey: 'teamlead.reassign.load_heavy', text: 'text-red-600 dark:text-bad-text', bar: 'bg-red-500', pill: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' };
}

function AgentCard({ a, onPick, saving, disabled, recommended }) {
    const ls = loadState(a.load);
    return (
        <div className={`rounded-2xl border bg-white dark:bg-panel-2 p-4 ${recommended ? 'border-emerald-400' : 'border-gray-200 dark:border-edge-strong'}`}>
            <div className="flex items-center gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-accent-soft text-xs font-bold text-blue-700 dark:text-accent-text">{a.initials}</span>
                <div className="min-w-0 flex-1">
                    <p className="text-[14px] font-bold text-gray-900 dark:text-ink-1">{a.name}</p>
                    <p className="text-[11.5px] text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.agent_meta', { load: a.load, type: a.type })}</p>
                </div>
                <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold ${ls.pill}`}>{recommended ? trans('teamlead.reassign.lightest') : trans(ls.labelKey)}</span>
            </div>
            <div className="mt-3 flex items-center gap-3">
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3"><div className={`h-full rounded-full ${ls.bar}`} style={{ width: `${Math.min(100, Math.round((a.load / 20) * 100))}%` }} /></div>
                <span className={`text-[11px] font-bold ${ls.text}`}>{trans(ls.labelKey)}</span>
            </div>
            <div className="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-2">
                {[[trans('teamlead.reassign.resolved'), a.resolved], [trans('teamlead.reassign.sla'), a.slaPct === null ? '—' : `${a.slaPct}%`], [trans('teamlead.reassign.avg_resolution'), a.avgResolution]].map(([k, v]) => (
                    <div key={k} className="rounded-xl bg-gray-50 dark:bg-panel-3 px-2.5 py-2">
                        <p className="text-[9px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{k}</p>
                        <p className={`mt-0.5 text-[15px] font-extrabold ${k === trans('teamlead.reassign.sla') && a.slaPct !== null ? (a.slaPct >= 90 ? 'text-emerald-600 dark:text-ok-text' : 'text-amber-600 dark:text-warn-text') : 'text-gray-900 dark:text-ink-1'}`}>{v}</p>
                    </div>
                ))}
                <div className="rounded-xl bg-gray-50 dark:bg-panel-3 px-2.5 py-2">
                    <p className="text-[9px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.rating')}</p>
                    <div className="mt-0.5"><RatingBadge rating={a.rating} count={a.ratingCount} /></div>
                </div>
            </div>
            {a.subjects.length > 0 && (
                <p className="mt-3 text-[11px] leading-relaxed text-gray-500 dark:text-ink-2">
                    <span className="font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.subjects', { count: a.subjects.length })}</span>
                    {a.subjects.slice(0, 4).join(', ')}{a.subjects.length > 4 ? ', …' : ''}
                </p>
            )}
            <button onClick={() => onPick(a.id)} disabled={saving || disabled} className="mt-3 w-full rounded-xl bg-blue-600 dark:bg-blue-500 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400 disabled:opacity-60">{trans('teamlead.reassign.assign_to', { name: a.name.split(' ')[0] })}</button>
        </div>
    );
}

export default function ReassignModal({ ticket, agents = [], remindUrlBase, onClose, onReassigned }) {
    useLockBodyScroll();
    const [tab, setTab] = useState('sub');
    const [search, setSearch] = useState('');
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    // Server menolak pemindahan tanpa alasan; tombolnya dimatikan lebih dulu
    // supaya Team Lead tahu syaratnya sebelum memilih agen, bukan sesudahnya.
    const reasonFilled = reason.trim().length > 0;

    const pool = useMemo(() => agents.filter((a) => a.id !== ticket.agentId), [agents, ticket.agentId]);

    const recommended = useMemo(() => {
        const inSub = pool.filter((a) => ticket.subcategory && a.subcats.includes(ticket.subcategory));
        const inApp = pool.filter((a) => ticket.service && a.apps.includes(ticket.service));
        const base = inSub.length ? inSub : inApp.length ? inApp : pool;
        return [...base].sort((x, y) => x.load - y.load)[0] ?? null;
    }, [pool, ticket]);

    const list = useMemo(() => {
        let out = pool;
        if (tab === 'sub' && ticket.subcategory) out = out.filter((a) => a.subcats.includes(ticket.subcategory));
        else if (tab === 'app' && ticket.service) out = out.filter((a) => a.apps.includes(ticket.service));
        const q = search.trim().toLowerCase();
        if (q) out = out.filter((a) => (a.name + ' ' + a.subjects.join(' ')).toLowerCase().includes(q));
        return [...out].sort((x, y) => x.load - y.load);
    }, [pool, tab, ticket, search]);

    async function pick(agentId) {
        if (!reasonFilled) return;
        setSaving(true);
        setError('');
        try {
            const res = await apiFetch(`${remindUrlBase}/${ticket.id}/reassign`, { method: 'POST', body: JSON.stringify({ agent_id: agentId, reason: reason.trim() }) });
            onReassigned?.(res);
        } catch (e) {
            setError(e.message || trans('teamlead.reassign.failed'));
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[55] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onMouseDown={onClose}>
            <div className="liquid-glass-dense flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-6 py-4">
                    <div>
                        <h2 className="text-[17px] font-extrabold text-gray-900 dark:text-ink-1">{trans('teamlead.reassign.title')}</h2>
                        <p className="mt-0.5 text-[12.5px] text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.subtitle', { id: ticket.id })}</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>

                <div className="border-b border-gray-100 dark:border-edge px-6 py-3">
                    <label className="block text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3" htmlFor="reassign-reason">{trans('teamlead.reassign.reason_label')}</label>
                    <textarea
                        id="reassign-reason"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        rows={2}
                        maxLength={1000}
                        placeholder={trans('teamlead.reassign.reason_placeholder')}
                        className="mt-1.5 w-full resize-y rounded-xl border border-gray-200 dark:border-edge-strong px-3 py-2 text-[13px] outline-none focus:border-blue-400"
                    />
                    <p className="mt-1 text-[11.5px] text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.reason_hint')}</p>
                </div>

                <div className="border-b border-gray-100 dark:border-edge px-6 py-3">
                    <div className="relative">
                        <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-ink-3"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg></span>
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={trans('teamlead.reassign.search')} className="w-full rounded-xl border border-gray-200 dark:border-edge-strong py-2.5 pl-10 pr-4 text-[13px] outline-none focus:border-blue-400" />
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-4">
                    {/* Latar hijau muda kartu rekomendasi ini tadinya tanpa pasangan
                        `dark:`, jadi di mode gelap ia tetap terang: kotaknya menyala
                        putih dan baris "Sub-kategori terdekat" hilang di dalamnya. */}
                    {recommended && (
                        <div className="mb-5 rounded-2xl border-[1.5px] border-emerald-400 dark:border-ok-text/40 bg-emerald-50/60 dark:bg-ok-soft p-4">
                            <p className="flex items-center gap-2 text-[12px] font-extrabold uppercase tracking-wide text-emerald-600 dark:text-ok-text">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2l2.4 5 5.6.5-4.2 3.7 1.3 5.5L12 19l-5.1 2.7 1.3-5.5L4 12.5l5.6-.5Z"/></svg>
                                {trans('teamlead.reassign.recommendation')}
                            </p>
                            <p className="mt-2 text-[11.5px] text-gray-600 dark:text-ink-2">{trans('teamlead.reassign.nearest_subcategory')}<b>{ticket.subcategory || '—'}</b> · {ticket.service || '—'}</p>
                            <div className="mt-3 flex items-center gap-3 rounded-xl border border-emerald-300 dark:border-ok-text/35 bg-white dark:bg-panel-2 p-3">
                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 dark:bg-ok-soft text-xs font-bold text-emerald-600 dark:text-ok-text">{recommended.initials}</span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[13.5px] font-bold text-gray-900 dark:text-ink-1">{recommended.name}</p>
                                    <p className="text-[11px] text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.rec_meta', { subjects: recommended.subjects.length, load: recommended.load })}</p>
                                </div>
                                <RatingBadge rating={recommended.rating} count={recommended.ratingCount} size={13} />
                                <button onClick={() => pick(recommended.id)} disabled={saving || !reasonFilled} className="rounded-lg bg-emerald-600 px-4 py-2 text-[12px] font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{trans('teamlead.reassign.submit')}</button>
                            </div>
                        </div>
                    )}

                    <div className="mb-3 flex items-center justify-between gap-2">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.target')}</p>
                        <div className="flex flex-wrap gap-1.5">
                            {TABS.map(([k, l]) => (
                                <button key={k} onClick={() => setTab(k)} className={`rounded-full px-3 py-1.5 text-[11.5px] font-semibold transition ${tab === k ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-1 ring-blue-300' : 'bg-white dark:bg-panel-2 text-gray-600 dark:text-ink-2 ring-1 ring-gray-200 dark:ring-edge-strong hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}>{trans(l)}</button>
                            ))}
                        </div>
                    </div>

                    {error && <p className="mb-3 rounded-lg bg-red-50 dark:bg-bad-soft px-3 py-2 text-[12px] font-medium text-red-600 dark:text-bad-text">{error}</p>}

                    <div className="flex flex-col gap-3">
                        {list.map((a) => <AgentCard key={a.id} a={a} onPick={pick} saving={saving} disabled={!reasonFilled} recommended={recommended && a.id === recommended.id} />)}
                        {list.length === 0 && <p className="rounded-xl bg-gray-50 dark:bg-panel-3 py-8 text-center text-[12.5px] text-gray-400 dark:text-ink-3">{trans('teamlead.reassign.empty')}</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

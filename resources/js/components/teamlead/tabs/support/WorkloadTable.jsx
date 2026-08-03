import { useMemo, useState } from 'react';
import { PriorityBadge } from '../../../StatusBadge';
import { apiFetch } from '../../../../lib/api';
import useLockBodyScroll from '../../../../lib/useLockBodyScroll';
import { t as trans } from '../../../../lib/i18n';

const AVAIL = {
    Online: { labelKey: 'teamlead.support.online', dot: 'bg-emerald-500', text: 'text-emerald-600 dark:text-ok-text', bg: 'bg-emerald-50 dark:bg-ok-soft' },
    Sibuk: { labelKey: 'teamlead.support.busy', dot: 'bg-amber-500', text: 'text-amber-700 dark:text-warn-text', bg: 'bg-amber-50 dark:bg-warn-soft' },
    Cuti: { labelKey: 'teamlead.support.leave', dot: 'bg-gray-400', text: 'text-gray-500 dark:text-ink-2', bg: 'bg-gray-100 dark:bg-panel-3' },
};

function loadBar(load) {
    if (load >= 6) return 'bg-red-500';
    if (load >= 3) return 'bg-amber-500';
    return 'bg-blue-500';
}

function SlaPill({ kind, label }) {
    const style = kind === 'breach' ? 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' : kind === 'warning' ? 'bg-amber-50 dark:bg-warn-soft text-amber-600 dark:text-warn-text' : kind === 'none' ? 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2' : 'bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text';
    return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ${style}`}>{label}</span>;
}

const STAR_PATH = 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z';

/** Compact single-star + number, for tight table cells. Turns red under the teguran threshold. */
function RatingCell({ rating, count, threshold = 4 }) {
    if (rating === null || rating === undefined) {
        return <span className="text-right text-[12.5px] text-gray-300">—</span>;
    }
    const low = rating < threshold;
    return (
        <span className="flex items-center justify-end gap-1" title={`${count} ulasan${low ? ' · di bawah ambang teguran' : ''}`}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill={low ? '#dc2626' : '#f59e0b'}><path d={STAR_PATH} /></svg>
            <span className={`text-[13px] font-bold ${low ? 'text-red-600 dark:text-bad-text' : 'text-gray-900 dark:text-ink-1'}`}>{rating.toFixed(1)}</span>
        </span>
    );
}

/** Full 5-star row (fractional fill via clip overlay) — used in the agent detail slide-over. */
function StarRow({ rating = 0, size = 18 }) {
    const pct = Math.max(0, Math.min(100, (rating / 5) * 100));
    const stars = (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <svg key={n} width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><path d={STAR_PATH} /></svg>
            ))}
        </div>
    );
    return (
        <div className="relative inline-flex text-gray-200">
            {stars}
            <div className="absolute inset-0 overflow-hidden text-amber-500" style={{ width: `${pct}%` }}>{stars}</div>
        </div>
    );
}

/** Inline reprimand form shown when an agent's rating drops below the threshold. */
function RatingTeguranBox({ agent, remindRatingUrlBase, onSent }) {
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState(
        trans('teamlead.support.rating_msg', { name: agent.name, rating: agent.rating.toFixed(1), count: agent.ratingCount })
    );
    const [sending, setSending] = useState(null); // channel being sent
    const [error, setError] = useState('');
    const [sent, setSent] = useState([]);

    async function send(channel) {
        setSending(channel);
        setError('');
        try {
            const res = await apiFetch(`${remindRatingUrlBase}/${agent.id}/remind-rating`, {
                method: 'POST',
                body: JSON.stringify({ channels: ['inapp', channel], message }),
            });
            setSent((prev) => Array.from(new Set([...prev, channel])));
            onSent?.(res);
        } catch (e) {
            setError(e.message || trans('teamlead.support.warn_failed'));
        } finally {
            setSending(null);
        }
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-2.5 text-[12.5px] font-bold text-white hover:bg-amber-700"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg>
                {trans('teamlead.support.give_rating_teguran')}
            </button>
        );
    }

    return (
        <div className="mt-3 rounded-2xl border border-amber-200 bg-white dark:bg-panel-2 p-3.5">
            <p className="text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.support.teguran_rating')}</p>
            <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={3}
                className="mt-2 w-full resize-none rounded-lg border border-gray-200 dark:border-edge-strong p-2.5 text-[12.5px] text-gray-700 dark:text-ink-2 outline-none focus:border-amber-400"
            />
            {error && <p className="mt-1.5 text-[11.5px] font-medium text-red-600 dark:text-bad-text">{error}</p>}
            <div className="mt-2.5 flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => send('email')}
                    disabled={!!sending}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-blue-50 dark:bg-accent-soft py-2 text-[12px] font-bold text-blue-700 dark:text-accent-text hover:bg-blue-100 dark:hover:bg-panel-hover disabled:opacity-50"
                >
                    {sent.includes('email') ? trans('teamlead.support.sent') : sending === 'email' ? trans('teamlead.common.sending') : trans('teamlead.support.send_email')}
                </button>
                <button
                    type="button"
                    onClick={() => send('whatsapp')}
                    disabled={!!sending}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-emerald-50 dark:bg-ok-soft py-2 text-[12px] font-bold text-emerald-700 dark:text-ok-text hover:bg-emerald-100 dark:hover:bg-ok-soft disabled:opacity-50"
                >
                    {sent.includes('whatsapp') ? trans('teamlead.support.sent') : sending === 'whatsapp' ? trans('teamlead.common.sending') : trans('teamlead.support.send_whatsapp')}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="rounded-lg p-2 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    );
}

function AgentDetail({ agent, onClose, onOpenTicket, remindRatingUrlBase, ratingTeguranThreshold, onRatingReminded }) {
    useLockBodyScroll();
    const a = AVAIL[agent.availability] ?? AVAIL.Online;
    return (
        <div className="fixed inset-0 z-50 flex justify-end bg-gray-900/40" onMouseDown={onClose}>
            <div className="flex h-full w-[440px] max-w-full flex-col bg-gray-50 dark:bg-panel-3 shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-center gap-3.5 border-b border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-6 py-5">
                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-base font-extrabold text-blue-700 dark:text-accent-text">{agent.initials}</span>
                    <div className="min-w-0 flex-1">
                        <p className="text-lg font-extrabold text-gray-900 dark:text-ink-1">{agent.name}</p>
                        <span className={`mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${a.bg} ${a.text}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${a.dot}`} />{trans('teamlead.support.agent_type', { availability: trans(a.labelKey), type: agent.type })}
                        </span>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6">
                    <div className="grid grid-cols-2 gap-3">
                        {[[trans('teamlead.support.active_load'), agent.load, agent.load >= 6 ? 'text-red-600 dark:text-bad-text' : agent.load >= 3 ? 'text-amber-600 dark:text-warn-text' : 'text-emerald-600 dark:text-ok-text'], [trans('teamlead.support.resolved'), agent.resolved, 'text-gray-900 dark:text-ink-1'], [trans('teamlead.support.productivity'), agent.productivity === null ? '—' : `${agent.productivity}%`, 'text-gray-900 dark:text-ink-1'], [trans('teamlead.support.avg_resolution'), agent.avgResolution, 'text-gray-900 dark:text-ink-1']].map(([label, val, color]) => (
                            <div key={label} className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm">
                                <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
                                <p className={`mt-1 text-2xl font-extrabold ${color}`}>{val}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 dark:bg-warn-soft p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.support.rating_satisfaction')}</p>
                                <p className="mt-0.5 text-xs text-gray-500 dark:text-ink-2">
                                    {agent.ratingCount > 0 ? trans('teamlead.support.from_reviews', { count: agent.ratingCount }) : trans('teamlead.support.no_reviews')}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <StarRow rating={agent.rating ?? 0} size={17} />
                                <span className="text-lg font-extrabold text-gray-900 dark:text-ink-1">{agent.rating !== null && agent.rating !== undefined ? agent.rating.toFixed(1) : '—'}</span>
                            </div>
                        </div>
                        {agent.rating !== null && agent.rating !== undefined && agent.rating < ratingTeguranThreshold && (
                            <RatingTeguranBox agent={agent} remindRatingUrlBase={remindRatingUrlBase} onSent={onRatingReminded} />
                        )}
                    </div>

                    <div className="mt-5 flex items-center justify-between">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-ink-2">{trans('teamlead.support.held_tickets')}</p>
                        <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-0.5 text-[11px] font-bold text-gray-500 dark:text-ink-2">{trans('teamlead.support.ticket_count', { count: agent.tickets.length })}</span>
                    </div>
                    <div className="mt-3 flex flex-col gap-2.5">
                        {agent.tickets.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => onOpenTicket?.(t.id)}
                                className="block w-full cursor-pointer rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-3.5 text-left shadow-sm transition hover:border-blue-300 hover:bg-blue-50/40 dark:hover:bg-panel-hover"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="text-[12px] font-bold text-blue-600 dark:text-accent-text">{t.id}</span>
                                    <span className="ml-auto"><SlaPill kind={t.slaKind} label={t.sla} /></span>
                                </div>
                                <p className="mt-1.5 text-[13px] font-semibold text-gray-900 dark:text-ink-1">{t.subject}</p>
                                <div className="mt-2 flex items-center gap-2">
                                    <span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:text-ink-2">{t.service}</span>
                                    <PriorityBadge priority={t.priority} />
                                </div>
                            </button>
                        ))}
                        {agent.tickets.length === 0 && <p className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 text-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.support.no_active_tickets')}</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function WorkloadTable({ rows = [], onOpenTicket, remindRatingUrlBase, ratingTeguranThreshold = 4, onRatingReminded }) {
    const [query, setQuery] = useState('');
    const [detail, setDetail] = useState(null);

    const summary = useMemo(() => {
        const totalLoad = rows.reduce((s, a) => s + a.load, 0);
        return {
            totalLoad,
            avg: rows.length ? (totalLoad / rows.length).toFixed(1) : '0',
            padat: rows.filter((a) => a.load >= 6).length,
            sedang: rows.filter((a) => a.load >= 3 && a.load < 6).length,
            ringan: rows.filter((a) => a.load < 3).length,
            online: rows.filter((a) => a.availability === 'Online').length,
            sibuk: rows.filter((a) => a.availability === 'Sibuk').length,
        };
    }, [rows]);

    const maxLoad = Math.max(1, ...rows.map((a) => a.load));
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? rows.filter((a) => a.name.toLowerCase().includes(q)) : rows;
    }, [rows, query]);

    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.support.workload')}</h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.support.workload_hint')}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <div className="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-panel-3 px-3.5 py-2">
                        <div><p className="text-base font-extrabold leading-none text-gray-900 dark:text-ink-1">{summary.totalLoad}</p><p className="text-[10px] font-semibold text-gray-400 dark:text-ink-3">{trans('teamlead.support.total_active')}</p></div>
                        <span className="h-6 w-px bg-gray-200" />
                        <div><p className="text-base font-extrabold leading-none text-gray-900 dark:text-ink-1">{summary.avg}</p><p className="text-[10px] font-semibold text-gray-400 dark:text-ink-3">{trans('teamlead.support.per_agent')}</p></div>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="flex items-center gap-1.5 rounded-full bg-red-50 dark:bg-bad-soft px-2.5 py-1 text-[11px] font-bold text-red-600 dark:text-bad-text"><span className="h-1.5 w-1.5 rounded-full bg-red-500" />{trans('teamlead.support.load_heavy', { count: summary.padat })}</span>
                        <span className="flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-warn-soft px-2.5 py-1 text-[11px] font-bold text-amber-700 dark:text-warn-text"><span className="h-1.5 w-1.5 rounded-full bg-amber-500" />{trans('teamlead.support.load_medium', { count: summary.sedang })}</span>
                        <span className="flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-ok-soft px-2.5 py-1 text-[11px] font-bold text-emerald-600 dark:text-ok-text"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />{trans('teamlead.support.load_light', { count: summary.ringan })}</span>
                    </div>
                </div>
            </div>

            <div className="px-5 pb-3">
                <div className="relative max-w-md">
                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-ink-3">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg>
                    </span>
                    <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={trans('teamlead.support.workload_search')} className="w-full rounded-xl border border-gray-200 dark:border-edge-strong py-2.5 pl-10 pr-4 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400" />
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[930px]">
                    <div className="grid grid-cols-[190px_1fr_70px_100px_90px_74px_70px_50px] gap-3 border-y border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                        <span>{trans('teamlead.support.agent')}</span><span>{trans('teamlead.support.active_load')}</span><span className="text-right">{trans('teamlead.support.resolved')}</span><span className="text-right">{trans('teamlead.support.avg_response')}</span><span className="text-right">{trans('teamlead.support.avg_resolution')}</span><span className="text-right">{trans('teamlead.support.productive')}</span><span className="text-right">{trans('teamlead.columns.rating')}</span><span className="text-right">{trans('teamlead.common.detail')}</span>
                    </div>
                    {filtered.map((a) => {
                        const av = AVAIL[a.availability] ?? AVAIL.Online;
                        return (
                            <div key={a.id} onClick={() => setDetail(a)} className="grid cursor-pointer grid-cols-[190px_1fr_70px_100px_90px_74px_70px_50px] items-center gap-3 border-b border-gray-50 dark:border-transparent dark:even:bg-white/[0.03] px-6 py-3.5 last:border-0 hover:bg-blue-50/30 dark:hover:bg-panel-hover">
                                <div className="flex items-center gap-2.5">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700 dark:text-accent-text">{a.initials}</span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-900 dark:text-ink-1">{a.name}</p>
                                        <span className={`inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold ${av.bg} ${av.text}`}><span className={`h-1 w-1 rounded-full ${av.dot}`} />{trans(av.labelKey)}</span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 pr-4">
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3"><div className={`h-full rounded-full ${loadBar(a.load)}`} style={{ width: `${Math.round((a.load / maxLoad) * 100)}%` }} /></div>
                                    <span className="w-5 text-[13px] font-bold text-gray-900 dark:text-ink-1">{a.load}</span>
                                </div>
                                <span className="text-right text-[13px] font-semibold text-gray-700 dark:text-ink-2">{a.resolved}</span>
                                <span className="text-right text-[12.5px] text-gray-500 dark:text-ink-2">{a.avgResponse}</span>
                                <span className="text-right text-[12.5px] text-gray-700 dark:text-ink-2">{a.avgResolution}</span>
                                <span className={`text-right text-[13px] font-bold ${a.productivity === null ? 'text-gray-400 dark:text-ink-3' : a.productivity >= 90 ? 'text-emerald-600 dark:text-ok-text' : a.productivity >= 60 ? 'text-amber-600 dark:text-warn-text' : 'text-red-600 dark:text-bad-text'}`}>{a.productivity === null ? '—' : `${a.productivity}%`}</span>
                                <RatingCell rating={a.rating} count={a.ratingCount} threshold={ratingTeguranThreshold} />
                                <span className="flex justify-end text-blue-500"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
                            </div>
                        );
                    })}
                    {filtered.length === 0 && <div className="px-6 py-10 text-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.support.workload_empty')}</div>}
                </div>
            </div>

            {detail && (
                <AgentDetail
                    agent={detail}
                    onClose={() => setDetail(null)}
                    onOpenTicket={onOpenTicket}
                    remindRatingUrlBase={remindRatingUrlBase}
                    ratingTeguranThreshold={ratingTeguranThreshold}
                    onRatingReminded={onRatingReminded}
                />
            )}
        </div>
    );
}

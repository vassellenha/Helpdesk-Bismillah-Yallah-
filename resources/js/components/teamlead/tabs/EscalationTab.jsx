import { useMemo, useState } from 'react';
import { StatusBadge, PriorityBadge } from '../../StatusBadge';
import { MetricCard, ICON } from '../ui';
import { t as trans } from '../../../lib/i18n';

const STAR_PATH = 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z';

/**
 * Compact rating for the escalation table. Three distinct outcomes, because
 * collapsing them would hide real information: the ticket is still running,
 * it closed but nobody rated it, or it closed with a score. A greyed star
 * marks a rating the Admin excluded from the agent's average.
 */
function RatingCell({ done, rating, ratingActive }) {
    if (!done) {
        return <span className="text-[11.5px] text-gray-300 dark:text-ink-3">—</span>;
    }
    if (rating === null || rating === undefined) {
        return <span className="text-[11px] text-gray-400 dark:text-ink-3">{trans('teamlead.support.no_reviews')}</span>;
    }

    return (
        <span
            className={`inline-flex items-center gap-1 text-[12.5px] font-bold ${ratingActive ? 'text-amber-600 dark:text-warn-text' : 'text-gray-400 dark:text-ink-3'}`}
            title={trans(ratingActive ? 'teamlead.flow.rating_included' : 'teamlead.flow.rating_excluded')}
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d={STAR_PATH} /></svg>
            {Number(rating).toFixed(1)}
        </span>
    );
}

function BpoTable({ rows, actions }) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? rows.filter((e) => `${e.id} ${e.subject} ${e.from} ${e.to} ${e.service}`.toLowerCase().includes(q)) : rows;
    }, [rows, query]);

    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-accent-soft text-blue-600 dark:text-accent-text"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20V9 M8 12l4-4 4 4 M8 4h8"/></svg></span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.escalation.bpo_table')} <span className="ml-1 rounded-full bg-gray-100 dark:bg-panel-3 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:text-ink-2">{trans('teamlead.escalation.monitored')}</span></h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.escalation.bpo_desc')}</p>
                    </div>
                </div>
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={trans('teamlead.escalation.search')} className="w-56 rounded-xl border border-gray-200 dark:border-edge-strong px-3.5 py-2.5 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400" />
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1010px] text-sm">
                    <thead>
                        <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-4 py-3.5 pl-6 text-left">{trans('teamlead.columns.ticket_no')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.escalation.subject_reason')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.priority')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.escalation.from_to')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.escalation.escalated_at')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.status')}</th>
                            <th className="px-4 py-3.5 pr-6 text-left">{trans('teamlead.columns.rating')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((e) => (
                            <tr key={e.id} onClick={() => actions.openTicket?.(e.id)} className="group cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/30 dark:hover:bg-panel-hover">
                                <td className="px-4 py-4 pl-6"><span className="font-bold text-blue-600 dark:text-accent-text group-hover:underline">{e.id}</span></td>
                                <td className="px-4 py-4">
                                    <p className="max-w-[280px] truncate text-[13px] font-semibold text-gray-900 dark:text-ink-1">{e.subject}</p>
                                    {e.note && <p className="mt-0.5 max-w-[280px] truncate text-[11.5px] text-gray-400 dark:text-ink-3">{e.note}</p>}
                                </td>
                                <td className="px-4 py-4"><PriorityBadge priority={e.priority} /></td>
                                <td className="px-4 py-4">
                                    <span className="flex items-center gap-1.5 text-[12px] text-gray-700 dark:text-ink-2">
                                        <span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-0.5 font-semibold">{e.from}</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14 M13 6l6 6-6 6"/></svg>
                                        <span className="rounded-md bg-blue-50 dark:bg-accent-soft px-2 py-0.5 font-semibold text-blue-700 dark:text-accent-text">{e.to}</span>
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-[12px] text-gray-500 dark:text-ink-2">{e.escalatedAt}</td>
                                <td className="px-4 py-4"><StatusBadge status={e.status} /></td>
                                <td className="px-4 py-4 pr-6"><RatingCell done={e.done} rating={e.rating} ratingActive={e.ratingActive} /></td>
                            </tr>
                        ))}
                        {filtered.length === 0 && <tr><td colSpan={7} className="px-5 py-12 text-center text-sm text-emerald-600 dark:text-ok-text">{trans('teamlead.escalation.no_bpo')}</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function BreachTable({ rows, actions }) {
    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex items-center gap-3 p-5">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.escalation.breach_table')} <span className="ml-1 rounded-full bg-red-50 dark:bg-bad-soft px-2 py-0.5 text-[11px] font-semibold text-red-600 dark:text-bad-text">{trans('teamlead.escalation.monitored_count', { count: rows.length })}</span></h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.escalation.breach_desc')}</p>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-4 py-3.5 pl-6 text-left">{trans('teamlead.columns.ticket_no')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.subject')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.app')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.priority')}</th>
                            <th className="px-4 py-3.5 text-left">{trans('teamlead.escalation.delay')}</th>
                            <th className="px-4 py-3.5 pr-6 text-left">{trans('teamlead.columns.pic')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.id} onClick={() => actions.openTicket?.(r.id)} className="group cursor-pointer border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-blue-50/30 dark:hover:bg-panel-hover">
                                <td className="px-4 py-4 pl-6"><span className="font-bold text-blue-600 dark:text-accent-text group-hover:underline">{r.id}</span></td>
                                <td className="px-4 py-4"><p className="max-w-[240px] truncate text-gray-800 dark:text-ink-1">{r.subject}</p></td>
                                <td className="px-4 py-4"><span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:text-ink-2">{r.service}</span></td>
                                <td className="px-4 py-4"><PriorityBadge priority={r.priority} /></td>
                                <td className="px-4 py-4"><span className="rounded-full bg-red-50 dark:bg-bad-soft px-2.5 py-1 text-[11px] font-bold text-red-600 dark:text-bad-text">{r.overdue}</span></td>
                                <td className="px-4 py-4 pr-6 text-gray-700 dark:text-ink-2">{r.agent}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-emerald-600 dark:text-ok-text">{trans('teamlead.escalation.no_breach')}</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function EscalationTab({ escalations = [], breachEscalations = [], actions = {} }) {
    const [view, setView] = useState('bpo');

    const stats = useMemo(() => ({
        total: escalations.length,
        active: escalations.filter((e) => !e.done).length,
        breach: breachEscalations.length,
    }), [escalations, breachEscalations]);

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <MetricCard label={trans('teamlead.escalation.bpo_to_it')} value={stats.total} icon={ICON.escalate} iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text" sub={trans('teamlead.escalation.handling', { count: stats.active })} />
                <MetricCard label={trans('teamlead.escalation.breach_active')} value={stats.breach} icon={ICON.warning} iconBg="bg-red-50 dark:bg-bad-soft" iconColor="text-red-600 dark:text-bad-text" sub={trans('teamlead.escalation.breach_hint')} />
                <MetricCard label={trans('teamlead.escalation.completed')} value={escalations.filter((e) => e.done).length} icon={ICON.check} iconBg="bg-emerald-50 dark:bg-ok-soft" iconColor="text-emerald-600 dark:text-ok-text" />
            </div>

            <div className="flex gap-1.5 self-start rounded-xl bg-gray-100 dark:bg-panel-3 p-1">
                {[['bpo', trans('teamlead.escalation.tab_bpo', { count: stats.total })], ['breach', trans('teamlead.escalation.tab_breach', { count: stats.breach })]].map(([k, l]) => (
                    <button key={k} onClick={() => setView(k)} className={`rounded-lg px-4 py-2 text-[13px] font-bold transition ${view === k ? 'bg-white dark:bg-panel-2 text-gray-900 dark:text-ink-1 shadow-sm' : 'text-gray-500 dark:text-ink-2'}`}>{l}</button>
                ))}
            </div>

            {view === 'bpo' ? <BpoTable rows={escalations} actions={actions} /> : <BreachTable rows={breachEscalations} actions={actions} />}
        </div>
    );
}

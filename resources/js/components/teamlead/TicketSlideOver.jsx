import { useEffect, useState } from 'react';
import { apiFetch } from '../../lib/api';
import { StatusBadge, PriorityBadge } from '../StatusBadge';
import RemindModal from './RemindModal';
import ReassignModal from './ReassignModal';
import RaisePriorityModal from './RaisePriorityModal';
import { t as trans } from '../../lib/i18n';
import TicketFlow from '../TicketFlow';
import useLockBodyScroll from '../../lib/useLockBodyScroll';
import { SkeletonBar } from '../Spinner';

const STEP_STYLE = {
    done: { dot: 'bg-emerald-500', line: 'bg-emerald-500', text: 'text-gray-900 dark:text-ink-1' },
    current: { dot: 'bg-blue-500 ring-4 ring-blue-100', line: 'bg-gray-200', text: 'text-blue-700 dark:text-accent-text' },
    pending: { dot: 'bg-gray-300', line: 'bg-gray-200', text: 'text-gray-400 dark:text-ink-3' },
    // A ticket can stop at an approval step instead of passing through it:
    // rejected ends the flow, returned bounces it back to the requester.
    rejected: { dot: 'bg-red-500 ring-4 ring-red-100', line: 'bg-gray-200', text: 'text-red-600 dark:text-bad-text' },
    returned: { dot: 'bg-amber-500 ring-4 ring-amber-100', line: 'bg-gray-200', text: 'text-amber-700 dark:text-warn-text' },
};

const NOTE_STYLE = {
    done: 'text-emerald-600 dark:text-ok-text',
    current: 'text-blue-600 dark:text-accent-text',
    rejected: 'text-red-600 dark:text-bad-text',
    returned: 'text-amber-700 dark:text-warn-text',
    pending: 'text-gray-400 dark:text-ink-3',
};

const NOTE_ICON = {
    done: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M8.5 12l2.5 2.5 4.5-5',
    current: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2',
    rejected: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M15 9l-6 6 M9 9l6 6',
    returned: 'M9 14 4 9l5-5 M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
    pending: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 8v5 M12 16h.01',
};

const STAR_PATH = 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z';

function Stars({ rating = 0, size = 16 }) {
    const pct = Math.max(0, Math.min(100, (rating / 5) * 100));
    const row = (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <svg key={n} width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><path d={STAR_PATH} /></svg>
            ))}
        </div>
    );

    return (
        <div className="relative inline-flex text-gray-200 dark:text-panel-3">
            {row}
            <div className="absolute inset-0 overflow-hidden text-amber-500" style={{ width: `${pct}%` }}>{row}</div>
        </div>
    );
}

/**
 * Only rendered once the ticket is finished — a rating cannot exist before the
 * requester closes it, and an empty star row on an in-progress ticket reads as
 * "rated zero" rather than "not rated yet".
 */
function RatingBlock({ ticket }) {
    const { satisfactionRating: rating, feedbackNote, ratingActive } = ticket;

    return (
        <div className="mt-6">
            <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.flow.rating')}</p>
            <div className="rounded-2xl bg-white dark:bg-panel-2 p-4 shadow-sm">
                {rating === null || rating === undefined ? (
                    <p className="text-[12.5px] text-gray-400 dark:text-ink-3">{trans('teamlead.flow.no_rating')}</p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Stars rating={rating} size={17} />
                            <span className="text-[15px] font-extrabold text-gray-900 dark:text-ink-1">{Number(rating).toFixed(1)}</span>
                            <span className={`ml-auto rounded-full px-2.5 py-1 text-[11px] font-semibold ${ratingActive ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2'}`}>
                                {trans(ratingActive ? 'teamlead.flow.rating_included' : 'teamlead.flow.rating_excluded')}
                            </span>
                        </div>
                        {feedbackNote && (
                            <p className="mt-2.5 border-t border-gray-100 dark:border-edge pt-2.5 text-[12.5px] leading-relaxed text-gray-600 dark:text-ink-2">“{feedbackNote}”</p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
const TL_STYLE = { done: 'bg-emerald-500', current: 'bg-blue-500', pending: 'bg-gray-300', rejected: 'bg-red-500' };

function SlaPill({ kind, label }) {
    const style = kind === 'breach' ? 'text-red-600 dark:text-bad-text' : kind === 'warning' ? 'text-amber-600 dark:text-warn-text' : kind === 'none' ? 'text-gray-400 dark:text-ink-3' : 'text-emerald-600 dark:text-ok-text';
    return <span className={`flex items-center gap-1.5 text-[13px] font-bold ${style}`}><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2"/></svg>{label}</span>;
}

function SlideOverSkeleton() {
    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-6 py-5">
                <div className="flex items-center justify-between">
                    <SkeletonBar className="h-3.5 w-24" />
                    <SkeletonBar className="h-5 w-5 rounded-full" />
                </div>
                <SkeletonBar className="mt-3 h-6 w-3/4" />
                <div className="mt-4 flex items-center gap-2">
                    <SkeletonBar className="h-6 w-16 rounded-full" />
                    <SkeletonBar className="h-6 w-14 rounded-full" />
                    <SkeletonBar className="h-6 w-20 rounded-md" />
                    <SkeletonBar className="ml-auto h-6 w-24 rounded-full" />
                </div>
            </div>

            <div className="flex-1 overflow-hidden px-6 py-5">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="flex flex-col gap-1.5">
                            <SkeletonBar className="h-2.5 w-16" />
                            <SkeletonBar className="h-3.5 w-28" />
                        </div>
                    ))}
                </div>

                <SkeletonBar className="mt-7 h-2.5 w-32" />
                <SkeletonBar className="mt-2.5 h-20 w-full rounded-2xl" />

                <SkeletonBar className="mt-7 h-2.5 w-28" />
                <div className="mt-3 flex flex-col gap-3.5">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3">
                            <SkeletonBar className="h-2.5 w-2.5 shrink-0 rounded-full" />
                            <SkeletonBar className={`h-3 ${i === 1 ? 'w-2/3' : 'w-full'}`} />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function ApprovalFlow({ flow }) {
    if (!flow) return null;
    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <p className="text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.ticket.approval_flow')}</p>
                <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500 dark:text-ink-2">{flow.type}</span>
            </div>
            <div className="flex items-stretch rounded-2xl bg-white dark:bg-panel-2 p-4 shadow-sm">
                {flow.steps.map((s, i) => {
                    const st = STEP_STYLE[s.state] ?? STEP_STYLE.pending;
                    return (
                        <div key={i} className="flex flex-1 flex-col items-center gap-2 text-center">
                            <div className="flex w-full items-center justify-center">
                                <span className={`h-0.5 flex-1 ${i === 0 ? 'bg-transparent' : STEP_STYLE[flow.steps[i - 1].state]?.line ?? 'bg-gray-200'}`} />
                                <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white ${st.dot}`}>
                                    {s.state === 'done' ? <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l4 4 10-11"/></svg> : <span className="h-1.5 w-1.5 rounded-full bg-white dark:bg-panel-2" />}
                                </span>
                                <span className={`h-0.5 flex-1 ${i === flow.steps.length - 1 ? 'bg-transparent' : st.line}`} />
                            </div>
                            <div>
                                <p className={`text-[11.5px] font-bold ${st.text}`}>{s.name}</p>
                                <p className="text-[10px] text-gray-400 dark:text-ink-3">{s.sub}</p>
                                {s.by && <p className="mt-0.5 text-[10px] font-medium text-gray-500 dark:text-ink-2">{s.by}</p>}
                                {s.at && <p className="text-[9.5px] text-gray-400 dark:text-ink-3">{s.at}</p>}
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className={`mt-2 flex items-center gap-1.5 text-[11.5px] font-semibold ${NOTE_STYLE[flow.noteState] ?? NOTE_STYLE.done}`}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={NOTE_ICON[flow.noteState] ?? NOTE_ICON.done} /></svg>
                {flow.note}
            </p>
        </div>
    );
}

export default function TicketSlideOver({ ticketId, remindUrlBase, onClose, onChanged }) {
    useLockBodyScroll();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(null);
    const [note, setNote] = useState('');
    const [comments, setComments] = useState([]);
    const [toast, setToast] = useState(null);

    function flash(m) { setToast(m); setTimeout(() => setToast(null), 3000); }

    async function load() {
        setLoading(true);
        try {
            const res = await apiFetch(`${remindUrlBase}/${ticketId}/data`);
            setData(res);
            setComments(res.comments || []);
        } catch {
            setData(null);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { load(); /* eslint-disable-next-line */ }, [ticketId]);

    async function saveNote() {
        if (!note.trim()) return;
        try {
            const c = await apiFetch(`${remindUrlBase}/${ticketId}/note`, { method: 'POST', body: JSON.stringify({ message: note }) });
            setComments((prev) => [...prev, c]);
            setNote('');
            flash(trans('teamlead.ticket.note_added'));
        } catch (e) { flash(e.message || trans('teamlead.ticket.note_failed')); }
    }

    const t = data?.ticket;
    const row = t ? { id: t.id, subject: t.subject, agent: t.agent, agentId: t.agentId, priority: t.priority, sla: t.sla, subcategory: t.subcategory, service: t.service } : null;
    // Mirrors Ticket::DONE_STATUSES — a finished ticket has a rating to show
    // and nothing left to hand to another agent.
    const finished = ['Resolved', 'Completed', 'Closed'].includes(t?.status);

    return (
        <div className="fixed inset-0 z-40 flex justify-end bg-slate-950/40 backdrop-blur-sm" onMouseDown={onClose}>
            <div className="liquid-glass-dense flex h-full w-[33vw] min-w-[420px] max-w-full flex-col shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                {loading ? (
                    <SlideOverSkeleton />
                ) : !t ? (
                    <div className="flex flex-1 items-center justify-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.not_found')}</div>
                ) : (
                    <>
                        <div className="liquid-glass-well border-b border-gray-200 dark:border-edge-strong px-6 py-5">
                            <div className="flex items-center justify-between">
                                <span className="text-[13px] font-bold text-blue-600 dark:text-accent-text">{t.id}</span>
                                <button onClick={onClose} className="rounded-lg p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                            </div>
                            <h1 className="mt-1 text-xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{t.subject}</h1>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-red-50 dark:bg-bad-soft px-2.5 py-1 text-[11px] font-bold text-red-600 dark:text-bad-text">{t.type}</span>
                                <PriorityBadge priority={t.priority} />
                                <span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:text-ink-2">{t.service}</span>
                                <StatusBadge status={t.status} />
                                <span className="ml-auto"><SlaPill kind={t.slaKind} label={t.sla} /></span>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto px-6 py-5">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                                {[[trans('teamlead.ticket.reporter'), t.requester?.name ?? '—'], [trans('teamlead.ticket.unit'), t.requester?.unit ?? '—'], [trans('teamlead.ticket.app'), t.service], [trans('teamlead.ticket.assigned_agent'), t.agent], [trans('teamlead.ticket.category'), t.type], [trans('teamlead.ticket.subcategory'), t.subcategory]].map(([k, v]) => (
                                    <div key={k}>
                                        <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{k}</p>
                                        <p className="mt-0.5 text-[13px] font-semibold text-gray-900 dark:text-ink-1">{v}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6">
                                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.ticket.description')}</p>
                                <div className="rounded-2xl bg-white dark:bg-panel-2 p-4 text-[13px] leading-relaxed text-gray-700 dark:text-ink-2 shadow-sm">{t.description || trans('teamlead.ticket.no_description')}</div>
                            </div>

                            <div className="mt-6"><ApprovalFlow flow={data.approvalFlow} /></div>

                            {finished && <RatingBlock ticket={t} />}

                            <div className="mt-6">
                                <p className="mb-3 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.ticket.sla_timeline')}</p>
                                <TicketFlow flow={data.flow} />
                                <div className="mt-4 border-t border-gray-100 dark:border-edge pt-4" />
                                <div className="flex flex-col">
                                    {data.timeline.map((s, i) => (
                                        <div key={i} className="flex gap-3">
                                            <div className="flex flex-col items-center">
                                                <span className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${TL_STYLE[s.state] ?? TL_STYLE.pending}`} />
                                                {i < data.timeline.length - 1 && <span className="my-1 w-px flex-1 bg-gray-200" style={{ minHeight: 16 }} />}
                                            </div>
                                            <div className="pb-2.5">
                                                <p className="text-[12.5px] font-semibold text-gray-800 dark:text-ink-1">{s.label}</p>
                                                {(s.who || s.at) && <p className="text-[11px] text-gray-400 dark:text-ink-3">{[s.who, s.at].filter(Boolean).join(' · ')}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="mt-6">
                                <p className="mb-3 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.ticket.activity')}</p>
                                <div className="flex flex-col gap-3.5">
                                    {comments.map((c) => (
                                        <div key={c.id} className="flex gap-3">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-panel-3 text-[10px] font-bold text-gray-600 dark:text-ink-2">{(c.authorName || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}</span>
                                            <div><p className="text-[12.5px] text-gray-800 dark:text-ink-1"><span className="font-bold">{c.authorName}</span> <span className="text-gray-400 dark:text-ink-3">· {c.at}</span></p><p className="mt-0.5 text-[12.5px] text-gray-700 dark:text-ink-2">{c.message}</p></div>
                                        </div>
                                    ))}
                                    {comments.length === 0 && <p className="text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.no_activity')}</p>}
                                </div>
                            </div>

                            <div className="mt-6">
                                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-warn-text">{trans('teamlead.ticket.internal_note')}</p>
                                <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} placeholder={trans('teamlead.ticket.note_placeholder')} className="w-full resize-none rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-3.5 text-[13px] outline-none focus:border-blue-400" />
                                <button onClick={saveNote} disabled={!note.trim()} className="mt-2 rounded-xl bg-gray-900 dark:bg-panel-selected px-4 py-2 text-[12.5px] font-bold text-white hover:bg-gray-800 disabled:opacity-40">{trans('teamlead.ticket.add_note')}</button>
                            </div>
                        </div>

                        <div className="flex gap-2 border-t border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-6 py-3.5">
                            <button onClick={() => setModal('remind')} title={trans('teamlead.ticket.send_remind')} className="flex h-11 w-11 items-center justify-center rounded-xl border border-red-200 text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg></button>
                            {!finished && (
                                <button onClick={() => setModal('reassign')} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 dark:bg-blue-500 py-3 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 8h13 M16 5l4 3-4 3 M17 16H4 M8 13l-4 3 4 3"/></svg>{trans('teamlead.ticket.reassign')}</button>
                            )}
                            {finished && (
                                <p className="flex flex-1 items-center justify-center px-3 text-center text-[12px] font-medium text-gray-400 dark:text-ink-3">{trans('teamlead.flow.closed_no_action')}</p>
                            )}
                            <button onClick={() => setModal('raise')} className="flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-edge-strong px-4 py-3 text-[13px] font-bold text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5 M6 11l6-6 6 6"/></svg>{trans('teamlead.ticket.priority_btn')}</button>
                        </div>
                    </>
                )}
            </div>

            {modal === 'remind' && row && <RemindModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSent={(res) => { setModal(null); flash(res?.message ?? trans('teamlead.ticket.reminded')); load(); onChanged?.(); }} />}
            {modal === 'reassign' && row && <ReassignModal ticket={row} agents={data.agentOptions} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onReassigned={(res) => { setModal(null); flash(res?.message ?? trans('teamlead.ticket.reassigned')); load(); onChanged?.(); }} />}
            {modal === 'raise' && row && <RaisePriorityModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSaved={(res) => { setModal(null); flash(res?.message ?? trans('teamlead.ticket.priority_updated')); load(); onChanged?.(); }} />}

            {toast && <div className="fixed bottom-6 left-1/2 z-[70] -translate-x-1/2 rounded-xl bg-gray-900 dark:bg-panel-selected px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>}
        </div>
    );
}

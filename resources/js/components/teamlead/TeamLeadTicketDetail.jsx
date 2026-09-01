import { useState } from 'react';
import { StatusBadge, PriorityBadge } from '../StatusBadge';
import RemindModal from './RemindModal';
import ReassignModal from './ReassignModal';
import RaisePriorityModal from './RaisePriorityModal';
import { t as trans } from '../../lib/i18n';
import TicketFlow from '../TicketFlow';
import SlaPanel from '../SlaPanel';
import BrandLockup from '../BrandLockup';


const STEP_STYLE = {
    done: { dot: 'bg-emerald-500', text: 'text-gray-800 dark:text-ink-1' },
    current: { dot: 'bg-blue-500 ring-4 ring-blue-100', text: 'text-blue-700 dark:text-accent-text' },
    pending: { dot: 'bg-gray-300', text: 'text-gray-400 dark:text-ink-3' },
    rejected: { dot: 'bg-red-500', text: 'text-red-600 dark:text-bad-text' },
};

function SlaPill({ kind, label }) {
    const style = kind === 'breach' ? 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' : kind === 'warning' ? 'bg-amber-50 dark:bg-warn-soft text-amber-600 dark:text-warn-text' : kind === 'none' ? 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2' : 'bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text';
    return <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold ${style}`}>{label}</span>;
}

export default function TeamLeadTicketDetail({ flow = null, ticket: initial, timeline = [], comments = [], agentOptions = [], remindUrlBase, dashboardUrl }) {
    const [ticket, setTicket] = useState(initial);
    const [modal, setModal] = useState(null);
    const [toast, setToast] = useState(null);

    function flash(msg) { setToast(msg); setTimeout(() => setToast(null), 3000); }

    const row = { id: ticket.id, subject: ticket.subject, agent: ticket.agent, agentId: ticket.agentId, priority: ticket.priority, sla: ticket.sla };

    return (
        <div className="flex min-h-screen flex-col">
            <header className="sticky top-2 z-20 mx-2 flex min-h-[62px] items-center gap-2.5 rounded-2xl sm:top-3 sm:mx-3 sm:gap-4 border border-black/5 dark:border-white/10 bg-white/65 dark:bg-white/[0.06] px-3 sm:px-7 shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 md:mx-6">
                <BrandLockup />
                <a href={dashboardUrl} className="ml-2 flex items-center gap-1.5 rounded-[10px] px-3 py-2 text-[13px] font-semibold text-gray-600 dark:text-ink-2 transition-all duration-200 ease-out hover:bg-white/60 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-ink-1">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                    Kembali ke Dashboard
                </a>
                <div className="flex-1" />
            </header>

            <main className="mx-auto flex w-full max-w-[900px] flex-1 flex-col gap-6 px-7 py-7">
                <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-[13px] font-bold text-blue-600 dark:text-accent-text">{ticket.id}</p>
                            <h1 className="mt-1 text-xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{ticket.subject}</h1>
                        </div>
                        <SlaPill kind={ticket.slaKind} label={ticket.sla} />
                    </div>
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <StatusBadge status={ticket.status} />
                        <PriorityBadge priority={ticket.priority} />
                        <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-1 text-[11px] font-semibold text-gray-600 dark:text-ink-2">{ticket.type}</span>
                        <span className="rounded-md bg-gray-100 dark:bg-panel-3 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:text-ink-2">{ticket.service}</span>
                    </div>

                    <div className="mt-5 flex flex-wrap gap-2 border-t border-gray-100 dark:border-edge pt-5">
                        <button onClick={() => setModal('remind')} className="flex items-center gap-1.5 rounded-xl bg-red-50 dark:bg-bad-soft px-4 py-2.5 text-[13px] font-bold text-red-600 dark:text-bad-text hover:bg-red-100 dark:hover:bg-bad-soft">{trans('teamlead.ticket.send_remind_btn')}</button>
                        <button onClick={() => setModal('reassign')} className="flex items-center gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong px-4 py-2.5 text-[13px] font-bold text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">{trans('teamlead.ticket.reassign')}</button>
                        <button onClick={() => setModal('raise')} className="flex items-center gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong px-4 py-2.5 text-[13px] font-bold text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">{trans('teamlead.ticket.raise')}</button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.description')}</h2>
                            <p className="text-[13.5px] leading-relaxed text-gray-700 dark:text-ink-2">{ticket.description || trans('teamlead.ticket.no_description')}</p>
                        </div>

                        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.activity')}</h2>
                            <div className="flex flex-col gap-4">
                                {comments.map((c) => (
                                    <div key={c.id} className="flex gap-3">
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-panel-3 text-[10px] font-bold text-gray-600 dark:text-ink-2">{(c.authorName || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}</span>
                                        <div>
                                            <p className="text-[12.5px] text-gray-800 dark:text-ink-1"><span className="font-bold">{c.authorName}</span> <span className="text-gray-400 dark:text-ink-3">· {c.authorRole} · {c.at}</span></p>
                                            <p className="mt-0.5 text-[12.5px] text-gray-700 dark:text-ink-2">{c.message}</p>
                                        </div>
                                    </div>
                                ))}
                                {comments.length === 0 && <p className="text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.no_activity')}</p>}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-6">
                        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.info')}</h2>
                            <dl className="flex flex-col gap-3 text-[13px]">
                                {[[trans('teamlead.ticket.support_pic'), ticket.agent], [trans('teamlead.ticket.reporter'), ticket.requester?.name ?? '—'], [trans('teamlead.ticket.unit'), ticket.requester?.unit ?? '—'], [trans('teamlead.ticket.email'), ticket.requester?.email ?? '—'], [trans('teamlead.ticket.subcategory'), ticket.subcategory], [trans('teamlead.ticket.created'), ticket.createdAt], [trans('teamlead.ticket.sla_due'), ticket.resolutionDue ?? '—']].map(([k, v]) => (
                                    <div key={k} className="flex flex-col gap-0.5">
                                        <dt className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{k}</dt>
                                        <dd className="font-semibold text-gray-900 dark:text-ink-1">{v}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">SLA</h2>
                            <SlaPanel
                                sla={ticket.slaPanel}
                                rating={ticket.satisfactionRating}
                                feedbackNote={ticket.feedbackNote}
                                ratingActive={ticket.ratingActive ?? true}
                            />
                        </div>

                        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.status_history')}</h2>
                            <TicketFlow flow={flow} />
                        </div>

                        <div className="mt-6 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                            <h2 className="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.ticket.sla_timeline')}</h2>
                            <div className="flex flex-col">
                                {timeline.map((s, i) => {
                                    const st = STEP_STYLE[s.state] ?? STEP_STYLE.pending;
                                    return (
                                        <div key={i} className="flex gap-3">
                                            <div className="flex flex-col items-center">
                                                <span className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${st.dot}`} />
                                                {i < timeline.length - 1 && <span className="my-1 w-px flex-1 bg-gray-200 dark:bg-edge-strong" style={{ minHeight: 18 }} />}
                                            </div>
                                            <div className="pb-3">
                                                <p className={`text-[12.5px] font-semibold ${st.text}`}>{s.label}</p>
                                                {(s.who || s.at) && <p className="text-[11px] text-gray-400 dark:text-ink-3">{[s.who, s.at].filter(Boolean).join(' · ')}</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            {modal === 'remind' && (
                <RemindModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSent={(res) => { setModal(null); flash(res?.message ?? trans('teamlead.ticket.reminded')); }} />
            )}
            {modal === 'reassign' && (
                <ReassignModal ticket={row} agents={agentOptions} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onReassigned={(res) => { setTicket((t) => ({ ...t, agent: res.agent.name, agentId: res.agent.id })); setModal(null); flash(res?.message ?? trans('teamlead.ticket.reassigned')); }} />
            )}
            {modal === 'raise' && (
                <RaisePriorityModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSaved={(res) => { setTicket((t) => ({ ...t, priority: res.priority })); setModal(null); flash(res?.message ?? trans('teamlead.ticket.priority_updated')); }} />
            )}

            {toast && <div className="fixed bottom-6 left-1/2 z-[80] -translate-x-1/2 rounded-xl bg-gray-900 dark:bg-panel-selected px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>}
        </div>
    );
}

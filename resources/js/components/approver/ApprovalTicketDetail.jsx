import { useState } from 'react';
import { t as trans } from '../../lib/i18n';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import { apiFetch, uploadFile } from '../../lib/api';
import TicketFlow from '../TicketFlow';
import SlaPanel from '../SlaPanel';
import AttachmentViewer from '../AttachmentViewer';
import CommentAttachmentChip from '../CommentAttachmentChip';
import CommentComposer from '../CommentComposer';
import useLockBodyScroll from '../../lib/useLockBodyScroll';

// Colour stays here (presentation); every string comes from lang/*/approver.php.
const CONFIRM_STYLE = {
    approved: 'bg-blue-600 dark:bg-blue-500 hover:bg-blue-700 dark:hover:bg-blue-400',
    revision_requested: 'bg-amber-600 hover:bg-amber-700',
    rejected: 'bg-red-600 hover:bg-red-700',
};

function Card({ title, children, className = '' }) {
    return (
        <div className={`rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm ${className}`}>
            {title && <h2 className="mb-3.5 text-[14px] font-bold text-gray-900 dark:text-ink-1">{title}</h2>}
            {children}
        </div>
    );
}

function Field({ label, value }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
            <p className="mt-1 text-[13px] font-medium text-gray-900 dark:text-ink-1">{value || '—'}</p>
        </div>
    );
}

function ConfirmModal({ decision, ticketId, note, submitting, error, onCancel, onConfirm }) {
    useLockBodyScroll();
    const copy = {
        title: trans(`approver.confirm.${decision}.title`),
        body: trans(`approver.confirm.${decision}.body`, { id: ticketId }),
        button: trans(`approver.confirm.${decision}.button`),
        color: CONFIRM_STYLE[decision],
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onClick={onCancel}>
            <div className="liquid-glass-dense w-full max-w-md overflow-hidden rounded-2xl p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-accent-soft text-blue-600 dark:text-accent-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9" /></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{copy.title}</h2>
                        <p className="mt-1 text-[13px] leading-relaxed text-gray-500 dark:text-ink-2">{copy.body}</p>
                    </div>
                </div>

                <label className="mb-1.5 mt-4 block text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('approver.detail.your_note')}</label>
                <div className="rounded-xl bg-gray-50 dark:bg-panel-3 px-3.5 py-3 text-[13px] text-gray-700 dark:text-ink-2">{note}</div>

                {error && <p className="mt-3 rounded-lg bg-red-50 dark:bg-bad-soft p-2.5 text-xs text-red-700 dark:text-bad-text">{error}</p>}

                <div className="mt-5 flex justify-end gap-2.5">
                    <button onClick={onCancel} className="rounded-full border border-gray-200 dark:border-edge-strong px-4 py-2.5 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        {trans('approver.confirm.no')}
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={submitting}
                        className={`rounded-full px-4 py-2.5 text-[13px] font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 ${copy.color}`}
                    >
                        {submitting ? trans('approver.confirm.sending') : copy.button}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function ApprovalTicketDetail({ ticket: initialTicket, comments: initialComments = [], flow: initialFlow = null, dataUrl, commentsUrl, decideUrl, ticketsUrl }) {
    const [ticket, setTicket] = useState(initialTicket);
    const [flow, setFlow] = useState(initialFlow);
    const [comments, setComments] = useState(initialComments);
    const [reply, setReply] = useState('');
    const [sending, setSending] = useState(false);
    const [note, setNote] = useState('');
    const [noteError, setNoteError] = useState(false);
    const [confirmDecision, setConfirmDecision] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    async function sendReply(file) {
        if (!reply.trim() && !file) return;
        setSending(true);
        try {
            const comment = file
                ? await uploadFile(commentsUrl, file, { message: reply })
                : await apiFetch(commentsUrl, { method: 'POST', body: JSON.stringify({ message: reply }) });
            setComments((prev) => [...prev, comment]);
            setReply('');
        } catch (e) {
            setError(e.message || 'Gagal mengirim pesan.');
        } finally {
            setSending(false);
        }
    }

    function requestDecision(decision) {
        if (!note.trim()) {
            setNoteError(true);
            return;
        }
        setNoteError(false);
        setConfirmDecision(decision);
    }

    async function confirmDecisionAction() {
        setSubmitting(true);
        setError('');
        try {
            await apiFetch(decideUrl, { method: 'POST', body: JSON.stringify({ decision: confirmDecision, note }) });
            const fresh = await apiFetch(dataUrl);
            setTicket(fresh.ticket);
            setComments(fresh.comments);
            setFlow(fresh.flow);
            setConfirmDecision(null);
        } catch (e) {
            setError(e.message || 'Gagal mengirim keputusan.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <a href={ticketsUrl} className="flex w-fit items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-ink-2 hover:text-gray-800 dark:hover:text-ink-1">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                My Tickets
            </a>

            <Card>
                <div className="flex flex-wrap items-center gap-2.5">
                    <span className="rounded-full bg-blue-50 dark:bg-accent-soft px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700 dark:text-accent-text">{trans('approver.detail.mode')}</span>
                    <StatusBadge status={ticket.status} />
                    <PriorityBadge priority={ticket.priority} />
                </div>
                <p className="mt-3 text-[14px] font-bold text-blue-600 dark:text-accent-text">{ticket.id}</p>
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{ticket.title}</h1>
                <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-gray-500 dark:text-ink-2">
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42L12 2Z" /><path d="M7 7h.01" /></svg>
                        {ticket.category}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        Dibuat {ticket.createdAt}
                    </span>
                </div>
            </Card>

            {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.7fr_1fr]">
                <div className="flex flex-col gap-6">
                    <Card title={trans('approver.detail.status_history')}>
                        <TicketFlow flow={flow} />
                    </Card>

                    <Card title={trans('approver.detail.ticket_info')}>
                        <p className="text-[13px] leading-relaxed text-gray-700 dark:text-ink-2">{ticket.description || 'Tidak ada deskripsi.'}</p>
                        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 dark:border-edge pt-4 sm:grid-cols-2">
                            <Field label={trans('approver.detail.requester')} value={ticket.requester?.name} />
                            <Field label={trans('approver.detail.unit')} value={ticket.requester?.unit} />
                            <Field label={trans('approver.detail.catalog_service')} value={ticket.layananKatalog} />
                            <Field label={trans('approver.detail.contact')} value={ticket.requester?.email} />
                        </div>
                        {ticket.attachments?.length > 0 && (
                            <div className="mt-4 border-t border-gray-100 dark:border-edge pt-4">
                                <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('approver.detail.attachments')}</p>
                                <AttachmentViewer attachments={ticket.attachments} />
                            </div>
                        )}
                    </Card>

                    <Card title={trans('approver.detail.forum')}>
                        <p className="mb-3 text-[12px] text-gray-400 dark:text-ink-3">Percakapan antara Requester, Approver, dan Support terekam di sini.</p>
                        <div className="flex flex-col gap-3">
                            {comments.length === 0 && (
                                <p className="rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-4 text-center text-[13px] text-gray-400 dark:text-ink-3">{trans('approver.detail.forum_empty')}</p>
                            )}
                            {comments.map((c) => (
                                <div key={c.id} className={`max-w-[85%] rounded-2xl px-4 py-3 ${c.authorRole === 'Approver' ? 'ml-auto bg-blue-600 dark:bg-blue-500 text-white' : 'bg-gray-50 dark:bg-panel-3 text-gray-800 dark:text-ink-1'}`}>
                                    <div className={`mb-1 flex items-center gap-2 text-[11px] font-semibold ${c.authorRole === 'Approver' ? 'text-blue-100' : 'text-gray-500 dark:text-ink-2'}`}>
                                        <span>{c.authorName}</span>
                                        <span className="opacity-70">· {c.authorRole}</span>
                                        <span className="opacity-70">· {c.at}</span>
                                    </div>
                                    {c.message && <p className="text-[13px] leading-relaxed">{c.message}</p>}
                                    <CommentAttachmentChip attachment={c.attachment} dark={c.authorRole === 'Approver'} />
                                </div>
                            ))}
                        </div>

                        {ticket.status !== 'Closed' && ticket.status !== 'Rejected' && (
                            <CommentComposer
                                value={reply}
                                onChange={setReply}
                                onSend={sendReply}
                                sending={sending}
                                placeholder={trans('approver.detail.forum_placeholder')}
                                sendingLabel={trans('approver.confirm.sending')}
                                sendLabel="Kirim"
                            />
                        )}
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    {ticket.canDecide ? (
                        <Card title={trans('approver.detail.decision_panel')}>
                            <p className="mb-3 text-[12px] leading-relaxed text-gray-400 dark:text-ink-3">Keputusan Anda sebagai Approver — {ticket.requester?.unit ?? ''}.</p>
                            <label className="mb-1.5 block text-[13px] font-bold text-gray-800 dark:text-ink-1">{trans('approver.detail.note_label')}</label>
                            <textarea
                                value={note}
                                onChange={(e) => {
                                    setNote(e.target.value);
                                    if (e.target.value.trim()) setNoteError(false);
                                }}
                                rows={4}
                                placeholder={trans('approver.detail.note_placeholder')}
                                className={`w-full resize-none rounded-xl border px-3.5 py-3 text-[13px] outline-none focus:border-blue-400 ${noteError ? 'border-red-400' : 'border-gray-200 dark:border-edge-strong'}`}
                            />
                            {noteError && <p className="mt-1.5 text-xs font-medium text-red-600 dark:text-bad-text">{trans('approver.detail.note_required')}</p>}

                            <div className="mt-4 flex flex-col gap-2.5">
                                <button
                                    onClick={() => requestDecision('approved')}
                                    className="flex items-center justify-center gap-2 rounded-xl bg-blue-600 dark:bg-blue-500 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    Setujui
                                </button>
                                <button
                                    onClick={() => requestDecision('revision_requested')}
                                    className="flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 dark:bg-warn-soft px-4 py-2.5 text-[13px] font-bold text-amber-700 dark:text-warn-text hover:bg-amber-100 dark:hover:bg-warn-soft"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    Minta Perbaikan
                                </button>
                                <button
                                    onClick={() => requestDecision('rejected')}
                                    className="flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white dark:bg-panel-2 px-4 py-2.5 text-[13px] font-bold text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                                    Tolak
                                </button>
                            </div>

                            <p className="mt-3 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                                Keputusan tercatat di riwayat approval &amp; audit trail. Requester menerima notifikasi.
                            </p>
                        </Card>
                    ) : ticket.lastDecision ? (
                        <Card title={trans('approver.detail.your_decision')}>
                            <p className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{ticket.lastDecision.decisionLabel}</p>
                            <p className="mt-1 text-[11px] text-gray-400 dark:text-ink-3">{ticket.lastDecision.at}</p>
                            <p className="mt-3 rounded-lg bg-gray-50 dark:bg-panel-3 p-3 text-[13px] text-gray-700 dark:text-ink-2">{ticket.lastDecision.note}</p>
                            {ticket.lastDecision.forwardedTo && (
                                <p className="mt-3 text-[11px] text-gray-400 dark:text-ink-3">{trans('approver.detail.forwarded_to')}<span className="font-semibold text-gray-600 dark:text-ink-2">{ticket.lastDecision.forwardedTo}</span></p>
                            )}
                        </Card>
                    ) : null}

                    <Card title={trans('approver.detail.sla')}>
                        <SlaPanel
                            sla={ticket.sla}
                            rating={ticket.satisfactionRating}
                            feedbackNote={ticket.feedbackNote}
                            ratingActive={ticket.ratingActive ?? true}
                        />
                    </Card>

                    <Card title={trans('approver.detail.people')}>
                        <div className="flex flex-col gap-3.5">
                            {[ticket.people?.requester, ticket.people?.approver, ...(ticket.people?.support ?? [])].filter(Boolean).map((p, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:text-accent-text">
                                        {p.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-900 dark:text-ink-1">{p.name}</p>
                                        <p className="truncate text-[11px] text-gray-400 dark:text-ink-3">{p.role}</p>
                                        {p.email && <p className="truncate text-[11px] text-blue-600 dark:text-accent-text">{p.email}</p>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>

            {confirmDecision && (
                <ConfirmModal
                    decision={confirmDecision}
                    ticketId={ticket.id}
                    note={note}
                    submitting={submitting}
                    error={error}
                    onCancel={() => !submitting && setConfirmDecision(null)}
                    onConfirm={confirmDecisionAction}
                />
            )}
        </div>
    );
}

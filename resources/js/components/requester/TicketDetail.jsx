import { useState } from 'react';
import { t as trans } from '../../lib/i18n';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import { apiFetch, uploadFile } from '../../lib/api';
import TicketFlow from '../TicketFlow';
import NewTicketModal from '../NewTicketModal';
import SlaPanel from '../SlaPanel';
import AttachmentViewer from '../AttachmentViewer';
import CommentAttachmentChip from '../CommentAttachmentChip';
import CommentComposer from '../CommentComposer';
import { isMine } from '../../lib/discussion';
import AutoCloseCountdown from '../AutoCloseCountdown';
import useLockBodyScroll from '../../lib/useLockBodyScroll';


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

function StarRating({ value, onChange }) {
    return (
        <div className="flex items-center gap-1.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    onClick={() => onChange(n)}
                    aria-label={`${n} star`}
                    className="p-0.5"
                >
                    <svg
                        width="30"
                        height="30"
                        viewBox="0 0 24 24"
                        fill={n <= value ? '#f59e0b' : 'none'}
                        stroke={n <= value ? '#f59e0b' : '#d1d5db'}
                        strokeWidth="1.6"
                        strokeLinejoin="round"
                    >
                        <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z" />
                    </svg>
                </button>
            ))}
        </div>
    );
}

function ConfirmCloseModal({ ticket, onClose, onDone, reopenUrl, closeUrl }) {
    useLockBodyScroll();
    const [step, setStep] = useState('choice');
    const [note, setNote] = useState('');
    const [rating, setRating] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    async function submitReopen() {
        if (!note.trim()) return;
        setSubmitting(true);
        setError('');
        try {
            await apiFetch(reopenUrl, { method: 'POST', body: JSON.stringify({ note }) });
            onDone();
        } catch (e) {
            setError(e.message || trans('requester.detail.send_to_support_failed'));
        } finally {
            setSubmitting(false);
        }
    }

    async function submitClose() {
        if (rating < 1) return;
        setSubmitting(true);
        setError('');
        try {
            await apiFetch(closeUrl, { method: 'POST', body: JSON.stringify({ rating, note: note || null }) });
            onDone();
        } catch (e) {
            setError(e.message || trans('requester.detail.close_failed'));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onClick={onClose}>
            <div className="liquid-glass-dense w-full max-w-md overflow-hidden rounded-2xl shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-5 py-4">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.detail.confirm_title')}</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{ticket.id} · {ticket.title}</p>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600" aria-label="Tutup">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                    </button>
                </div>

                <div className="px-5 py-5">
                    {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-xs text-red-700 dark:text-bad-text">{error}</p>}

                    {step === 'choice' && (
                        <>
                            <p className="mb-4 text-[14px] font-semibold text-gray-800 dark:text-ink-1">{trans('requester.detail.confirm_question')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    onClick={() => setStep('reopen')}
                                    className="flex flex-col items-center gap-2 rounded-2xl border-2 border-red-100 bg-red-50/50 px-4 py-5 text-center hover:border-red-300"
                                >
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600 dark:text-bad-text">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                                    </span>
                                    <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.detail.not_yet')}</span>
                                    <span className="text-[11px] text-gray-500 dark:text-ink-2">{trans('requester.detail.not_yet_hint')}</span>
                                </button>
                                <button
                                    onClick={() => setStep('rate')}
                                    className="flex flex-col items-center gap-2 rounded-2xl border-2 border-emerald-100 bg-emerald-50/50 px-4 py-5 text-center hover:border-emerald-300"
                                >
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:text-ok-text">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    </span>
                                    <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.detail.yes_done')}</span>
                                    <span className="text-[11px] text-gray-500 dark:text-ink-2">Beri penilaian &amp; tutup</span>
                                </button>
                            </div>
                        </>
                    )}

                    {step === 'reopen' && (
                        <>
                            <div className="mb-4 flex gap-2.5 rounded-xl bg-red-50 dark:bg-bad-soft p-3.5 text-[12.5px] leading-relaxed text-red-700 dark:text-bad-text">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
                                Tiket akan dibuka kembali dan dikirim ke Tim Support untuk penanganan lanjutan. Jelaskan kendala yang masih Anda alami.
                            </div>
                            <label className="mb-1.5 block text-[13px] font-bold text-gray-800 dark:text-ink-1">{trans('requester.detail.note_required')}</label>
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={4}
                                placeholder="Contoh: Akses ME51N sudah bisa, tetapi ME52N masih menampilkan error otorisasi saat menyimpan…"
                                className="w-full resize-none rounded-xl border border-gray-200 dark:border-edge-strong px-3.5 py-3 text-[13px] outline-none focus:border-blue-400"
                            />
                        </>
                    )}

                    {step === 'rate' && (
                        <>
                            <p className="mb-2.5 text-[14px] font-semibold text-gray-800 dark:text-ink-1">{trans('requester.detail.rate_question')}</p>
                            <StarRating value={rating} onChange={setRating} />
                            <p className="mt-1.5 text-xs text-gray-400 dark:text-ink-3">{trans('requester.detail.rate_hint')}</p>

                            <label className="mb-1.5 mt-4 block text-[13px] font-bold text-gray-800 dark:text-ink-1">{trans('requester.detail.note_optional')}</label>
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={4}
                                placeholder="Ceritakan pengalaman Anda — apa yang sudah baik atau bisa ditingkatkan…"
                                className="w-full resize-none rounded-xl border border-gray-200 dark:border-edge-strong px-3.5 py-3 text-[13px] outline-none focus:border-blue-400"
                            />
                        </>
                    )}
                </div>

                {step !== 'choice' && (
                    <div className="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-5 py-4">
                        <button
                            onClick={() => setStep('choice')}
                            className="rounded-full border border-gray-200 dark:border-edge-strong px-5 py-2.5 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                        >
                            Kembali
                        </button>
                        {step === 'reopen' ? (
                            <button
                                onClick={submitReopen}
                                disabled={submitting || !note.trim()}
                                className="flex items-center gap-2 rounded-full bg-blue-600 dark:bg-blue-500 px-5 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 14-7-4 14-4-5z" /><path d="m11 14 8-9" /></svg>
                                {submitting ? 'Mengirim…' : 'Kirim ke Support'}
                            </button>
                        ) : (
                            <button
                                onClick={submitClose}
                                disabled={submitting || rating < 1}
                                className="flex items-center gap-2 rounded-full bg-gray-900 dark:bg-panel-selected px-5 py-2.5 text-[13px] font-bold text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M20 6 9 17l-5-5" /></svg>
                                {submitting ? 'Mengirim…' : 'Kirim & Tutup Tiket'}
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

const APPROVAL_NOTE_STYLES = {
    approved: {
        border: 'border-emerald-200', bg: 'bg-emerald-50 dark:bg-ok-soft', iconBg: 'bg-emerald-100', iconColor: 'text-emerald-700 dark:text-ok-text',
        title: 'text-emerald-800', body: 'text-emerald-900', time: 'text-emerald-600 dark:text-ok-text',
        label: (name) => `Disetujui oleh ${name}`,
        icon: 'M20 6 9 17l-5-5',
    },
    revision_requested: {
        border: 'border-amber-200', bg: 'bg-amber-50 dark:bg-warn-soft', iconBg: 'bg-amber-100', iconColor: 'text-amber-700 dark:text-warn-text',
        title: 'text-amber-800', body: 'text-amber-900', time: 'text-amber-600 dark:text-warn-text',
        label: (name) => `Diminta perbaikan oleh ${name}`,
        icon: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z',
    },
    rejected: {
        border: 'border-red-200', bg: 'bg-red-50 dark:bg-bad-soft', iconBg: 'bg-red-100', iconColor: 'text-red-700 dark:text-bad-text',
        title: 'text-red-800', body: 'text-red-900', time: 'text-red-600 dark:text-bad-text',
        label: (name) => `Ditolak oleh ${name}`,
        icon: 'M6 6l12 12 M18 6 6 18',
    },
};

function ApprovalNoteBanner({ approvalNote }) {
    const style = APPROVAL_NOTE_STYLES[approvalNote.decision];
    if (!style) return null;

    return (
        <div className={`flex gap-3 rounded-2xl border ${style.border} ${style.bg} p-4`}>
            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${style.iconBg} ${style.iconColor}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={style.icon} /></svg>
            </span>
            <div className="min-w-0">
                <p className={`text-[13px] font-bold ${style.title}`}>{style.label(approvalNote.approverName ?? 'Approver')}</p>
                <p className={`mt-1 text-[13px] leading-relaxed ${style.body}`}>{approvalNote.note}</p>
                <p className={`mt-1.5 text-[11px] ${style.time}`}>{approvalNote.at}</p>
            </div>
        </div>
    );
}

function ReopenNoteBanner({ reopenNote }) {
    return (
        <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 dark:bg-warn-soft p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:text-warn-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.5 12a9.5 9.5 0 1 1-2.8-6.7M21.5 3v6h-6" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-amber-800">Dibuka kembali — dikirim ke Tim Support</p>
                <p className="mt-1 text-[13px] leading-relaxed text-amber-900">{reopenNote.note}</p>
                <p className="mt-1.5 text-[11px] text-amber-600 dark:text-warn-text">{reopenNote.at}</p>
            </div>
        </div>
    );
}

function SupportReturnNoteBanner({ supportReturnNote }) {
    return (
        <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 dark:bg-warn-soft p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:text-warn-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-amber-800">Dikembalikan oleh {supportReturnNote.agentName}</p>
                <p className="mt-1 text-[13px] leading-relaxed text-amber-900">{supportReturnNote.note}</p>
                <p className="mt-1.5 text-[11px] text-amber-600 dark:text-warn-text">{supportReturnNote.at}</p>
            </div>
        </div>
    );
}

function ResolutionNoteBanner({ resolutionNote }) {
    return (
        <div className="flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 dark:bg-ok-soft p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:text-ok-text">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-emerald-800 dark:text-ok-text">Diselesaikan oleh {resolutionNote.agentName}</p>
                <p className="mt-1 text-[13px] leading-relaxed text-emerald-900 dark:text-ok-text">{resolutionNote.note}</p>
                <p className="mt-1.5 text-[11px] text-emerald-600 dark:text-ok-text">{resolutionNote.at}</p>
            </div>
        </div>
    );
}

function ResolvedAnnouncementModal({ ticket, onDismiss, onConfirmNow }) {
    useLockBodyScroll();
    // `people.pic` is whoever actually holds the ticket right now — NOT
    // `people.support[0]`, which leads with the catalog Subject's configured
    // routing agent (e.g. the original BPO PIC) even after the ticket moved
    // on to someone else entirely, which is what misattributed a Support IT
    // resolution to the Subject's default BPO agent.
    const support = ticket.people?.pic;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onClick={onDismiss}>
            <div className="liquid-glass-dense w-full max-w-sm rounded-2xl p-6 text-center shadow-xl" onClick={(e) => e.stopPropagation()}>
                <span className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                </span>
                <h2 className="text-[16px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.detail.resolved_banner')}</h2>
                <p className="mt-2 text-[13px] leading-relaxed text-gray-500 dark:text-ink-2">
                    {support ? `${support.name} (${support.role})` : 'Tim Support'} telah menyelesaikan tiket{' '}
                    <span className="font-semibold text-gray-700 dark:text-ink-2">{ticket.id}</span> — {ticket.title}. Mohon konfirmasi apakah masalah Anda sudah teratasi agar tiket dapat ditutup.
                </p>
                {/* Tenggatnya disebut justru di sini: "Nanti Saja" adalah pilihan
                    yang membawa konsekuensi, dan requester berhak tahu apa
                    konsekuensinya sebelum menekannya. */}
                {ticket.autoClose && (
                    <div className="mt-4">
                        <AutoCloseCountdown autoClose={ticket.autoClose} />
                    </div>
                )}
                <div className="mt-5 flex gap-3">
                    <button onClick={onDismiss} className="flex-1 rounded-full border border-gray-200 dark:border-edge-strong px-4 py-2.5 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        Nanti Saja
                    </button>
                    <button onClick={onConfirmNow} className="flex-1 rounded-full bg-emerald-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-emerald-700">
                        Konfirmasi Sekarang
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function TicketDetail({ ticket: initialTicket, viewer = null, comments: initialComments = [], flow: initialFlow = null, dataUrl, commentsUrl, reopenUrl, closeUrl, ticketsUrl, editUrl, catalogUrl, approversUrl }) {
    const [ticket, setTicket] = useState(initialTicket);
    const [flow, setFlow] = useState(initialFlow);
    const status = ticket.status;
    const canConfirmClose = ticket.canConfirmClose;
    const [comments, setComments] = useState(initialComments);
    const [reply, setReply] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [announceOpen, setAnnounceOpen] = useState(initialTicket.canConfirmClose);

    // Re-fetch this ticket in place after reopen/close, instead of a full
    // window.location.reload() — every field the page reads (status,
    // banners, SLA, feedback) lives in `ticket` state, so swapping it in
    // updates the whole page without a flash.
    async function refresh() {
        try {
            const fresh = await apiFetch(dataUrl);
            setTicket(fresh.ticket);
            setComments(fresh.comments);
            setFlow(fresh.flow);
        } catch {
            // Keep the last-known data on a failed refresh.
        }
    }

    async function sendReply(file) {
        if (!reply.trim() && !file) return;
        setSending(true);
        setError('');
        try {
            const comment = file
                ? await uploadFile(commentsUrl, file, { message: reply })
                : await apiFetch(commentsUrl, { method: 'POST', body: JSON.stringify({ message: reply }) });
            setComments((prev) => [...prev, comment]);
            setReply('');
        } catch (e) {
            setError(e.message || trans('requester.detail.send_failed'));
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <a href={ticketsUrl} className="flex w-fit items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-ink-2 hover:text-gray-800 dark:hover:text-ink-1">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                My Tickets
            </a>

            {ticket.approvalNote && <ApprovalNoteBanner approvalNote={ticket.approvalNote} />}
            {ticket.supportReturnNote && <SupportReturnNoteBanner supportReturnNote={ticket.supportReturnNote} />}
            {ticket.reopenNote && <ReopenNoteBanner reopenNote={ticket.reopenNote} />}
            {ticket.resolutionNote && <ResolutionNoteBanner resolutionNote={ticket.resolutionNote} />}

            <Card>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="text-[14px] font-bold text-blue-600 dark:text-accent-text">{ticket.id}</span>
                        <StatusBadge status={status} />
                        <PriorityBadge priority={ticket.priority} />
                        <AutoCloseCountdown autoClose={ticket.autoClose} compact />
                    </div>
                    {canConfirmClose && (
                        <button
                            onClick={() => setModalOpen(true)}
                            className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-emerald-700"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                            Confirm & Close
                        </button>
                    )}
                    {(status === 'Draft' || status === 'Returned') && (
                        <NewTicketModal editTicket={ticket} editUrl={editUrl} catalogUrl={catalogUrl} approversUrl={approversUrl} triggerLabel={status === 'Returned' ? 'Edit & Resubmit' : 'Edit Draft'} />
                    )}
                </div>

                <h1 className="mt-3 text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">{ticket.title}</h1>

                <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-gray-500 dark:text-ink-2">
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42L12 2Z" /><path d="M7 7h.01" /></svg>
                        {ticket.category}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /></svg>
                        {ticket.service || '—'}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        Created {ticket.createdAt}
                    </span>
                </div>
            </Card>

            {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.7fr_1fr]">
                <div className="flex flex-col gap-6">
                    <Card title={trans('requester.detail.status_history')}>
                        <TicketFlow flow={flow} />
                    </Card>

                    <Card title={trans('requester.detail.ticket_info')}>
                        <p className="text-[13px] leading-relaxed text-gray-700 dark:text-ink-2">{ticket.description || trans('requester.detail.no_description')}</p>
                        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 dark:border-edge pt-4 sm:grid-cols-3">
                            <Field label={trans('requester.detail.category')} value={ticket.category} />
                            <Field label={trans('requester.detail.service')} value={ticket.service} />
                            <Field label={trans('requester.detail.subject')} value={ticket.subject} />
                        </div>
                        <AttachmentViewer attachments={ticket.attachments} className="mt-4" />
                    </Card>

                    {/* Draft SAJA yang menyembunyikan forum — tiket yang belum
                        pernah dikirim tidak punya lawan bicara, dan tidak ada
                        satu pun komentar yang bisa ada di sana.

                        "Returned" sempat ikut disembunyikan di sini, sebagai
                        bayangan dari syarat tombol "Edit & Resubmit" di atas
                        (Draft || Returned) — bukan karena ada alasannya
                        sendiri. Akibatnya justru terbalik: tiket Returned
                        adalah tiket yang SUDAH dikirim dan sudah punya
                        percakapan, dan pengembaliannya berarti requester
                        diminta memperbaiki sesuatu. Di sanalah isi forum paling
                        dibutuhkan, tapi requester baru bisa membacanya setelah
                        menekan Edit & Resubmit — sesudah momen ia perlu tahu
                        apa yang harus diperbaiki.

                        Server tidak pernah menyamakan keduanya: addComment()
                        hanya menutup Closed dan Rejected. Yang justru ditutup
                        saat Returned adalah sisi Support (Returned ada di
                        Ticket::NOT_YET_RELEASED_STATUSES) — giliran bicara
                        memang berpindah ke requester. */}
                    {status !== 'Draft' && (
                    <Card title={trans('requester.detail.discussion')}>
                        <div className="flex flex-col gap-3">
                            {comments.length === 0 && (
                                <p className="rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-4 text-center text-[13px] text-gray-400 dark:text-ink-3">
                                    {trans('requester.detail.forum_empty')}
                                </p>
                            )}
                            {/*
                              | Perataan ditentukan IDENTITAS, bukan peran — lihat
                              | lib/discussion.js. Peran di bawah hanya dipakai
                              | sebagai cadangan untuk komentar lama yang belum
                              | menyimpan id penulisnya.
                            */}
                            {comments.map((c) => {
                                const mine = isMine(c, viewer, 'Requester');

                                return (
                                <div key={c.id} className={`max-w-[85%] rounded-2xl px-4 py-3 ${mine ? 'ml-auto bg-blue-600 dark:bg-blue-500 text-white' : 'bg-gray-50 dark:bg-panel-3 text-gray-800 dark:text-ink-1'}`}>
                                    <div className={`mb-1 flex items-center gap-2 text-[11px] font-semibold ${mine ? 'text-blue-100' : 'text-gray-500 dark:text-ink-2'}`}>
                                        <span>{c.authorName}</span>
                                        <span className="opacity-70">· {c.authorRole}</span>
                                        <span className="opacity-70">· {c.at}</span>
                                    </div>
                                    {c.message && <p className="text-[13px] leading-relaxed">{c.message}</p>}
                                    <CommentAttachmentChip attachment={c.attachment} dark={mine} />
                                </div>
                                );
                            })}
                        </div>

                        {status !== 'Closed' && status !== 'Rejected' && (
                            <CommentComposer
                                value={reply}
                                onChange={setReply}
                                onSend={sendReply}
                                sending={sending}
                                placeholder={trans('requester.detail.forum_placeholder')}
                                sendingLabel={trans('requester.detail.sending')}
                                sendLabel={trans('requester.detail.send_reply')}
                            />
                        )}
                    </Card>
                    )}
                </div>

                <div className="flex flex-col gap-6">
                    <Card title="SLA">
                        <SlaPanel
                            sla={ticket.sla}
                            rating={ticket.satisfactionRating}
                            feedbackNote={ticket.feedbackNote}
                            ratingActive={ticket.ratingActive ?? true}
                        />
                    </Card>

                    <Card title={trans('requester.detail.people')}>
                        <div className="flex flex-col gap-3.5">
                            {[ticket.people.requester, ticket.people.approver, ...(ticket.people.support ?? [])].filter(Boolean).map((p, i) => (
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
                            {(ticket.people.support ?? []).length === 0 && ['Open', 'Assigned', 'In Progress', 'Waiting for Response'].includes(status) && (
                                <div className="flex items-center gap-3 opacity-60">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-panel-3 text-xs font-bold text-gray-400 dark:text-ink-3">
                                        ?
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-500 dark:text-ink-2">Support Team</p>
                                        <p className="truncate text-[11px] text-gray-400 dark:text-ink-3">{trans('requester.detail.unassigned')}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </Card>
                </div>
            </div>

            {announceOpen && (
                <ResolvedAnnouncementModal
                    ticket={ticket}
                    onDismiss={() => setAnnounceOpen(false)}
                    onConfirmNow={() => {
                        setAnnounceOpen(false);
                        setModalOpen(true);
                    }}
                />
            )}

            {modalOpen && (
                <ConfirmCloseModal
                    ticket={ticket}
                    reopenUrl={reopenUrl}
                    closeUrl={closeUrl}
                    onClose={() => setModalOpen(false)}
                    onDone={() => { setModalOpen(false); refresh(); }}
                />
            )}
        </div>
    );
}

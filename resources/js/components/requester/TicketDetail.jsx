import { useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import { apiFetch } from '../../lib/api';
import NewTicketModal from '../NewTicketModal';
import FeedbackDisplay from '../FeedbackDisplay';

const SLA_COLOR = { ontrack: '#10b981', warning: '#d97706', breach: '#dc2626', none: '#9ca3af' };

const TIMELINE_DOT = {
    done: 'bg-emerald-500',
    current: 'bg-blue-600',
    pending: 'bg-gray-200',
    rejected: 'bg-red-600',
};

function Card({ title, children, className = '' }) {
    return (
        <div className={`rounded-2xl border border-gray-200 bg-white p-5 shadow-sm ${className}`}>
            {title && <h2 className="mb-3.5 text-[14px] font-bold text-gray-900">{title}</h2>}
            {children}
        </div>
    );
}

function Field({ label, value }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{label}</p>
            <p className="mt-1 text-[13px] font-medium text-gray-900">{value || '—'}</p>
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
    const [step, setStep] = useState('choice');
    const [note, setNote] = useState('');
    const [rating, setRating] = useState(0);
    const [ratingTouched, setRatingTouched] = useState(false);
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
            setError(e.message || 'Failed to send to Support.');
        } finally {
            setSubmitting(false);
        }
    }

    async function submitClose() {
        setRatingTouched(true);
        if (rating < 1) return;
        setSubmitting(true);
        setError('');
        try {
            await apiFetch(closeUrl, { method: 'POST', body: JSON.stringify({ rating, note: note || null }) });
            onDone();
        } catch (e) {
            setError(e.message || 'Failed to close the ticket.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">Konfirmasi Penyelesaian</h2>
                        <p className="mt-0.5 text-xs text-gray-400">{ticket.id} · {ticket.title}</p>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                    </button>
                </div>

                <div className="px-5 py-5">
                    {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-700">{error}</p>}

                    {step === 'choice' && (
                        <>
                            <p className="mb-4 text-[14px] font-semibold text-gray-800">Apakah masalah Anda sudah teratasi?</p>
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    onClick={() => setStep('reopen')}
                                    className="flex flex-col items-center gap-2 rounded-2xl border-2 border-red-100 bg-red-50/50 px-4 py-5 text-center hover:border-red-300"
                                >
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                                    </span>
                                    <span className="text-[13px] font-bold text-gray-900">Belum</span>
                                    <span className="text-[11px] text-gray-500">Masih ada kendala</span>
                                </button>
                                <button
                                    onClick={() => setStep('rate')}
                                    className="flex flex-col items-center gap-2 rounded-2xl border-2 border-emerald-100 bg-emerald-50/50 px-4 py-5 text-center hover:border-emerald-300"
                                >
                                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    </span>
                                    <span className="text-[13px] font-bold text-gray-900">Ya, Sudah</span>
                                    <span className="text-[11px] text-gray-500">Beri penilaian &amp; tutup</span>
                                </button>
                            </div>
                        </>
                    )}

                    {step === 'reopen' && (
                        <>
                            <div className="mb-4 flex gap-2.5 rounded-xl bg-red-50 p-3.5 text-[12.5px] leading-relaxed text-red-700">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
                                Tiket akan dibuka kembali dan dikirim ke Tim Support untuk penanganan lanjutan. Jelaskan kendala yang masih Anda alami.
                            </div>
                            <label className="mb-1.5 block text-[13px] font-bold text-gray-800">Catatan untuk Tim Support *</label>
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={4}
                                placeholder="Contoh: Akses ME51N sudah bisa, tetapi ME52N masih menampilkan error otorisasi saat menyimpan…"
                                className="w-full resize-none rounded-xl border border-gray-200 px-3.5 py-3 text-[13px] outline-none focus:border-blue-400"
                            />
                        </>
                    )}

                    {step === 'rate' && (
                        <>
                            <p className="mb-2.5 text-[14px] font-semibold text-gray-800">Bagaimana penilaian Anda atas layanan ini?</p>
                            <StarRating value={rating} onChange={(n) => { setRating(n); setRatingTouched(false); }} />
                            {ratingTouched && rating < 1 ? (
                                <p className="mt-1.5 text-xs font-medium text-red-600">Ketuk bintang untuk menilai</p>
                            ) : (
                                <p className="mt-1.5 text-xs text-gray-400">Ketuk bintang untuk menilai</p>
                            )}

                            <label className="mb-1.5 mt-4 block text-[13px] font-bold text-gray-800">Catatan untuk Tim Support (opsional)</label>
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={4}
                                placeholder="Ceritakan pengalaman Anda — apa yang sudah baik atau bisa ditingkatkan…"
                                className="w-full resize-none rounded-xl border border-gray-200 px-3.5 py-3 text-[13px] outline-none focus:border-blue-400"
                            />
                        </>
                    )}
                </div>

                {step !== 'choice' && (
                    <div className="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
                        <button
                            onClick={() => setStep('choice')}
                            className="rounded-full border border-gray-200 px-5 py-2.5 text-[13px] font-bold text-gray-600 hover:bg-gray-50"
                        >
                            Kembali
                        </button>
                        {step === 'reopen' ? (
                            <button
                                onClick={submitReopen}
                                disabled={submitting || !note.trim()}
                                className="flex items-center gap-2 rounded-full bg-blue-600 px-5 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 14-7-4 14-4-5z" /><path d="m11 14 8-9" /></svg>
                                {submitting ? 'Mengirim…' : 'Kirim ke Support'}
                            </button>
                        ) : (
                            <button
                                onClick={submitClose}
                                disabled={submitting}
                                className="flex items-center gap-2 rounded-full bg-gray-900 px-5 py-2.5 text-[13px] font-bold text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50"
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
        border: 'border-emerald-200', bg: 'bg-emerald-50', iconBg: 'bg-emerald-100', iconColor: 'text-emerald-700',
        title: 'text-emerald-800', body: 'text-emerald-900', time: 'text-emerald-600',
        label: (name) => `Disetujui oleh ${name}`,
        icon: 'M20 6 9 17l-5-5',
    },
    revision_requested: {
        border: 'border-amber-200', bg: 'bg-amber-50', iconBg: 'bg-amber-100', iconColor: 'text-amber-700',
        title: 'text-amber-800', body: 'text-amber-900', time: 'text-amber-600',
        label: (name) => `Diminta perbaikan oleh ${name}`,
        icon: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z',
    },
    rejected: {
        border: 'border-red-200', bg: 'bg-red-50', iconBg: 'bg-red-100', iconColor: 'text-red-700',
        title: 'text-red-800', body: 'text-red-900', time: 'text-red-600',
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
        <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.5 12a9.5 9.5 0 1 1-2.8-6.7M21.5 3v6h-6" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-amber-800">Dibuka kembali — dikirim ke Tim Support</p>
                <p className="mt-1 text-[13px] leading-relaxed text-amber-900">{reopenNote.note}</p>
                <p className="mt-1.5 text-[11px] text-amber-600">{reopenNote.at}</p>
            </div>
        </div>
    );
}

function ResolvedAnnouncementModal({ ticket, onDismiss, onConfirmNow }) {
    const support = (ticket.people.support ?? [])[0];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onDismiss}>
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl" onClick={(e) => e.stopPropagation()}>
                <span className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                </span>
                <h2 className="text-[16px] font-bold text-gray-900">Tiket Anda Telah Diselesaikan</h2>
                <p className="mt-2 text-[13px] leading-relaxed text-gray-500">
                    {support ? `${support.name} (${support.role})` : 'Tim Support'} telah menyelesaikan tiket{' '}
                    <span className="font-semibold text-gray-700">{ticket.id}</span> — {ticket.title}. Mohon konfirmasi apakah masalah Anda sudah teratasi agar tiket dapat ditutup.
                </p>
                <div className="mt-5 flex gap-3">
                    <button onClick={onDismiss} className="flex-1 rounded-full border border-gray-200 px-4 py-2.5 text-[13px] font-bold text-gray-600 hover:bg-gray-50">
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

export default function TicketDetail({ ticket, comments: initialComments = [], timeline = [], commentsUrl, reopenUrl, closeUrl, ticketsUrl, editUrl, catalogUrl, approversUrl }) {
    const [status] = useState(ticket.status);
    const [canConfirmClose] = useState(ticket.canConfirmClose);
    const [comments, setComments] = useState(initialComments);
    const [reply, setReply] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [announceOpen, setAnnounceOpen] = useState(ticket.canConfirmClose);

    async function sendReply() {
        if (!reply.trim()) return;
        setSending(true);
        setError('');
        try {
            const comment = await apiFetch(commentsUrl, { method: 'POST', body: JSON.stringify({ message: reply }) });
            setComments((prev) => [...prev, comment]);
            setReply('');
        } catch (e) {
            setError(e.message || 'Failed to send reply.');
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <a href={ticketsUrl} className="flex w-fit items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                My Tickets
            </a>

            {ticket.approvalNote && <ApprovalNoteBanner approvalNote={ticket.approvalNote} />}
            {ticket.reopenNote && <ReopenNoteBanner reopenNote={ticket.reopenNote} />}

            <Card>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="text-[14px] font-bold text-blue-600">{ticket.id}</span>
                        <StatusBadge status={status} />
                        <PriorityBadge priority={ticket.priority} />
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
                    {status === 'Draft' && (
                        <NewTicketModal editTicket={ticket} editUrl={editUrl} catalogUrl={catalogUrl} approversUrl={approversUrl} triggerLabel="Edit Draft" />
                    )}
                </div>

                <h1 className="mt-3 text-2xl font-extrabold tracking-tight text-gray-900">{ticket.title}</h1>

                <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-gray-500">
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42L12 2Z" /><path d="M7 7h.01" /></svg>
                        {ticket.category}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /></svg>
                        {ticket.service || '—'}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        Created {ticket.createdAt}
                    </span>
                </div>
            </Card>

            {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.7fr_1fr]">
                <div className="flex flex-col gap-6">
                    <Card title="Ticket Information">
                        <p className="text-[13px] leading-relaxed text-gray-700">{ticket.description || 'No description was provided.'}</p>
                        <div className="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                            <Field label="Category" value={ticket.category} />
                            <Field label="Layanan" value={ticket.service} />
                            <Field label="Subject" value={ticket.subject} />
                        </div>
                        {ticket.attachments?.length > 0 && (
                            <div className="mt-4 flex flex-col gap-1.5">
                                {ticket.attachments.map((a) => (
                                    <a
                                        key={a.id}
                                        href={a.url}
                                        className="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-[13px] font-medium text-blue-600 hover:bg-gray-100 hover:underline"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9" /></svg>
                                        {a.name}
                                    </a>
                                ))}
                            </div>
                        )}
                    </Card>

                    <Card title="Discussion">
                        <div className="flex flex-col gap-3">
                            {comments.length === 0 && (
                                <p className="rounded-lg bg-gray-50 px-3 py-4 text-center text-[13px] text-gray-400">
                                    No discussion yet. Add a note if you have more details to share.
                                </p>
                            )}
                            {comments.map((c) => (
                                <div key={c.id} className={`max-w-[85%] rounded-2xl px-4 py-3 ${c.authorRole === 'Requester' ? 'ml-auto bg-blue-600 text-white' : 'bg-gray-50 text-gray-800'}`}>
                                    <div className={`mb-1 flex items-center gap-2 text-[11px] font-semibold ${c.authorRole === 'Requester' ? 'text-blue-100' : 'text-gray-500'}`}>
                                        <span>{c.authorName}</span>
                                        <span className="opacity-70">· {c.authorRole}</span>
                                        <span className="opacity-70">· {c.at}</span>
                                    </div>
                                    <p className="text-[13px] leading-relaxed">{c.message}</p>
                                </div>
                            ))}
                        </div>

                        {status !== 'Closed' && status !== 'Rejected' && (
                            <div className="mt-4 flex items-end gap-2 border-t border-gray-100 pt-4">
                                <textarea
                                    value={reply}
                                    onChange={(e) => setReply(e.target.value)}
                                    rows={2}
                                    placeholder="Write a note or reply…"
                                    className="flex-1 resize-none rounded-xl border border-gray-200 px-3.5 py-2.5 text-[13px] outline-none focus:border-blue-400"
                                />
                                <button
                                    onClick={sendReply}
                                    disabled={sending || !reply.trim()}
                                    className="rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {sending ? 'Sending…' : 'Send Reply'}
                                </button>
                            </div>
                        )}
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card title="SLA">
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-[13px] text-gray-500">Resolution target</span>
                            <span className="text-[13px] font-bold" style={{ color: SLA_COLOR[ticket.sla.kind] }}>{ticket.sla.label}</span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div className="h-full rounded-full" style={{ width: `${ticket.sla.pct}%`, backgroundColor: SLA_COLOR[ticket.sla.kind] }} />
                        </div>
                        <div className="mt-4 space-y-2 border-t border-gray-100 pt-3.5 text-[13px]">
                            <div className="flex justify-between"><span className="text-gray-500">Response target</span><span className="font-semibold text-gray-800">{ticket.sla.responseTarget}</span></div>
                            <div className="flex justify-between"><span className="text-gray-500">Resolution target</span><span className="font-semibold text-gray-800">{ticket.sla.resolutionTarget}</span></div>
                            <div className="flex justify-between"><span className="text-gray-500">Priority</span><span className="font-semibold text-gray-800">{ticket.priority}</span></div>
                        </div>
                    </Card>

                    {status === 'Closed' && ticket.satisfactionRating && (
                        <Card title="Feedback">
                            <FeedbackDisplay rating={ticket.satisfactionRating} note={ticket.feedbackNote} />
                        </Card>
                    )}

                    <Card title="People">
                        <div className="flex flex-col gap-3.5">
                            {[ticket.people.requester, ticket.people.approver, ...(ticket.people.support ?? [])].filter(Boolean).map((p, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                        {p.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-900">{p.name}</p>
                                        <p className="truncate text-[11px] text-gray-400">{p.role}</p>
                                        {p.email && <p className="truncate text-[11px] text-blue-600">{p.email}</p>}
                                    </div>
                                </div>
                            ))}
                            {(ticket.people.support ?? []).length === 0 && ['Open', 'Assigned', 'In Progress', 'Waiting for Response'].includes(status) && (
                                <div className="flex items-center gap-3 opacity-60">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-400">
                                        ?
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-500">Support Team</p>
                                        <p className="truncate text-[11px] text-gray-400">Belum ditugaskan</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card title="Status Timeline">
                        <div className="flex flex-col">
                            {timeline.map((step, i) => (
                                <div key={i} className="flex gap-3">
                                    <div className="flex flex-col items-center">
                                        <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${TIMELINE_DOT[step.state]}`} />
                                        {i < timeline.length - 1 && <span className="w-px flex-1 bg-gray-200" />}
                                    </div>
                                    <div className={`pb-4 ${step.state === 'pending' ? 'opacity-50' : ''}`}>
                                        <p className="text-[13px] font-semibold text-gray-900">{step.label}</p>
                                        {step.who && <p className="text-[11px] text-gray-400">{step.who}</p>}
                                        {step.at && <p className="text-[11px] text-gray-400">{step.at}</p>}
                                    </div>
                                </div>
                            ))}
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
                    onDone={() => window.location.reload()}
                />
            )}
        </div>
    );
}

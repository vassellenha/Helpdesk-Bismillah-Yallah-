import { useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import { apiFetch } from '../../lib/api';
import FeedbackDisplay from '../FeedbackDisplay';
import AttachmentViewer from '../AttachmentViewer';

const TIMELINE_DOT = {
    done: 'bg-emerald-500',
    current: 'bg-blue-600',
    pending: 'bg-gray-200',
    rejected: 'bg-red-600',
};

const CONFIRM_COPY = {
    approved: {
        title: 'Setujui permohonan ini?',
        body: (id) => `Tiket ${id} akan disetujui dan diteruskan ke tahap berikutnya (Support).`,
        button: 'Ya, Setujui',
        color: 'bg-blue-600 hover:bg-blue-700',
    },
    revision_requested: {
        title: 'Minta perbaikan?',
        body: (id) => `Tiket ${id} akan dikembalikan ke requester untuk diperbaiki sesuai catatan Anda.`,
        button: 'Ya, Minta Perbaikan',
        color: 'bg-amber-600 hover:bg-amber-700',
    },
    rejected: {
        title: 'Tolak permohonan ini?',
        body: (id) => `Tiket ${id} akan ditolak dan ditutup. Requester akan menerima notifikasi.`,
        button: 'Ya, Tolak',
        color: 'bg-red-600 hover:bg-red-700',
    },
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

function ConfirmModal({ decision, ticketId, note, submitting, error, onCancel, onConfirm }) {
    const copy = CONFIRM_COPY[decision];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onCancel}>
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9" /></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">{copy.title}</h2>
                        <p className="mt-1 text-[13px] leading-relaxed text-gray-500">{copy.body(ticketId)}</p>
                    </div>
                </div>

                <label className="mb-1.5 mt-4 block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Catatan Anda</label>
                <div className="rounded-xl bg-gray-50 px-3.5 py-3 text-[13px] text-gray-700">{note}</div>

                {error && <p className="mt-3 rounded-lg bg-red-50 p-2.5 text-xs text-red-700">{error}</p>}

                <div className="mt-5 flex justify-end gap-2.5">
                    <button onClick={onCancel} className="rounded-full border border-gray-200 px-4 py-2.5 text-[13px] font-bold text-gray-600 hover:bg-gray-50">
                        Tidak
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={submitting}
                        className={`rounded-full px-4 py-2.5 text-[13px] font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 ${copy.color}`}
                    >
                        {submitting ? 'Mengirim…' : copy.button}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function ApprovalTicketDetail({ ticket, comments: initialComments = [], timeline = [], commentsUrl, decideUrl, ticketsUrl }) {
    const [comments, setComments] = useState(initialComments);
    const [reply, setReply] = useState('');
    const [sending, setSending] = useState(false);
    const [note, setNote] = useState('');
    const [noteError, setNoteError] = useState(false);
    const [confirmDecision, setConfirmDecision] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    async function sendReply() {
        if (!reply.trim()) return;
        setSending(true);
        try {
            const comment = await apiFetch(commentsUrl, { method: 'POST', body: JSON.stringify({ message: reply }) });
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
            window.location.reload();
        } catch (e) {
            setError(e.message || 'Gagal mengirim keputusan.');
            setSubmitting(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <a href={ticketsUrl} className="flex w-fit items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                My Tickets
            </a>

            <Card>
                <div className="flex flex-wrap items-center gap-2.5">
                    <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">Mode Approval</span>
                    <StatusBadge status={ticket.status} />
                    <PriorityBadge priority={ticket.priority} />
                </div>
                <p className="mt-3 text-[14px] font-bold text-blue-600">{ticket.id}</p>
                <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-gray-900">{ticket.title}</h1>
                <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-gray-500">
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42L12 2Z" /><path d="M7 7h.01" /></svg>
                        {ticket.category}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        Dibuat {ticket.createdAt}
                    </span>
                </div>
            </Card>

            {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.7fr_1fr]">
                <div className="flex flex-col gap-6">
                    <Card title="Informasi Tiket">
                        <p className="text-[13px] leading-relaxed text-gray-700">{ticket.description || 'Tidak ada deskripsi.'}</p>
                        <div className="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
                            <Field label="Requester" value={ticket.requester?.name} />
                            <Field label="Unit Kerja" value={ticket.requester?.unit} />
                            <Field label="Layanan Katalog" value={ticket.layananKatalog} />
                            <Field label="Kontak" value={ticket.requester?.email} />
                        </div>
                        {ticket.attachments?.length > 0 && (
                            <div className="mt-4 border-t border-gray-100 pt-4">
                                <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Lampiran</p>
                                <AttachmentViewer attachments={ticket.attachments} />
                            </div>
                        )}
                    </Card>

                    <Card title="Forum Diskusi">
                        <p className="mb-3 text-[12px] text-gray-400">Percakapan antara Requester, Approver, dan Support terekam di sini.</p>
                        <div className="flex flex-col gap-3">
                            {comments.length === 0 && (
                                <p className="rounded-lg bg-gray-50 px-3 py-4 text-center text-[13px] text-gray-400">Belum ada diskusi.</p>
                            )}
                            {comments.map((c) => (
                                <div key={c.id} className={`max-w-[85%] rounded-2xl px-4 py-3 ${c.authorRole === 'Approver' ? 'ml-auto bg-blue-600 text-white' : 'bg-gray-50 text-gray-800'}`}>
                                    <div className={`mb-1 flex items-center gap-2 text-[11px] font-semibold ${c.authorRole === 'Approver' ? 'text-blue-100' : 'text-gray-500'}`}>
                                        <span>{c.authorName}</span>
                                        <span className="opacity-70">· {c.authorRole}</span>
                                        <span className="opacity-70">· {c.at}</span>
                                    </div>
                                    <p className="text-[13px] leading-relaxed">{c.message}</p>
                                </div>
                            ))}
                        </div>

                        {ticket.status !== 'Closed' && ticket.status !== 'Rejected' && (
                            <div className="mt-4 flex items-end gap-2 border-t border-gray-100 pt-4">
                                <textarea
                                    value={reply}
                                    onChange={(e) => setReply(e.target.value)}
                                    rows={2}
                                    placeholder="Tulis pertanyaan atau feedback untuk requester…"
                                    className="flex-1 resize-none rounded-xl border border-gray-200 px-3.5 py-2.5 text-[13px] outline-none focus:border-blue-400"
                                />
                                <button
                                    onClick={sendReply}
                                    disabled={sending || !reply.trim()}
                                    className="rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {sending ? 'Mengirim…' : 'Kirim'}
                                </button>
                            </div>
                        )}
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    {ticket.canDecide ? (
                        <Card title="Panel Keputusan">
                            <p className="mb-3 text-[12px] leading-relaxed text-gray-400">Keputusan Anda sebagai Approver — {ticket.requester?.unit ?? ''}.</p>
                            <label className="mb-1.5 block text-[13px] font-bold text-gray-800">Catatan / Feedback</label>
                            <textarea
                                value={note}
                                onChange={(e) => {
                                    setNote(e.target.value);
                                    if (e.target.value.trim()) setNoteError(false);
                                }}
                                rows={4}
                                placeholder="Wajib diisi sebelum menyetujui, meminta perbaikan, atau menolak"
                                className={`w-full resize-none rounded-xl border px-3.5 py-3 text-[13px] outline-none focus:border-blue-400 ${noteError ? 'border-red-400' : 'border-gray-200'}`}
                            />
                            {noteError && <p className="mt-1.5 text-xs font-medium text-red-600">Catatan wajib diisi sebelum melanjutkan.</p>}

                            <div className="mt-4 flex flex-col gap-2.5">
                                <button
                                    onClick={() => requestDecision('approved')}
                                    className="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    Setujui
                                </button>
                                <button
                                    onClick={() => requestDecision('revision_requested')}
                                    className="flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[13px] font-bold text-amber-700 hover:bg-amber-100"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    Minta Perbaikan
                                </button>
                                <button
                                    onClick={() => requestDecision('rejected')}
                                    className="flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-[13px] font-bold text-red-600 hover:bg-red-50"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M6 6l12 12" /><path d="M18 6 6 18" /></svg>
                                    Tolak
                                </button>
                            </div>

                            <p className="mt-3 text-[11px] leading-relaxed text-gray-400">
                                Keputusan tercatat di riwayat approval &amp; audit trail. Requester menerima notifikasi.
                            </p>
                        </Card>
                    ) : ticket.lastDecision ? (
                        <Card title="Keputusan Anda">
                            <p className="text-[13px] font-bold text-gray-900">{ticket.lastDecision.decisionLabel}</p>
                            <p className="mt-1 text-[11px] text-gray-400">{ticket.lastDecision.at}</p>
                            <p className="mt-3 rounded-lg bg-gray-50 p-3 text-[13px] text-gray-700">{ticket.lastDecision.note}</p>
                            {ticket.lastDecision.forwardedTo && (
                                <p className="mt-3 text-[11px] text-gray-400">Diteruskan ke: <span className="font-semibold text-gray-600">{ticket.lastDecision.forwardedTo}</span></p>
                            )}
                        </Card>
                    ) : null}

                    {ticket.status === 'Closed' && ticket.satisfactionRating && (
                        <Card title="Feedback">
                            <FeedbackDisplay rating={ticket.satisfactionRating} note={ticket.feedbackNote} />
                        </Card>
                    )}

                    <Card title="Riwayat Status">
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

import { useState } from 'react';
import { PriorityBadge, StatusBadge } from '../StatusBadge';
import { apiFetch } from '../../lib/api';
import FeedbackDisplay from '../FeedbackDisplay';

const TIMELINE_DOT = {
    done: 'bg-emerald-500',
    current: 'bg-blue-600',
    pending: 'bg-gray-200',
    rejected: 'bg-red-600',
};

const CONFIRM_COPY = {
    resolve: {
        title: 'Tutup Layanan (Service Closed)?',
        body: (id) => `Tiket ${id} akan ditandai selesai dan ditutup. Requester akan diminta memberi penilaian atas layanan. Tindakan ini tercatat di riwayat status.`,
        button: 'Ya, Tutup Layanan',
        color: 'bg-blue-600 hover:bg-blue-700',
    },
    escalate: {
        title: 'Eskalasi ke Tim IT?',
        body: (id) => `Tiket ${id} akan dieskalasi ke Tim IT Lanjutan untuk penanganan lebih dalam. Tindakan ini tercatat di riwayat status.`,
        button: 'Ya, Eskalasi',
        color: 'bg-blue-600 hover:bg-blue-700',
    },
};

function ReopenNoteBanner({ reopenNote }) {
    return (
        <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.5 12a9.5 9.5 0 1 1-2.8-6.7M21.5 3v6h-6" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-amber-800">Requester membuka kembali tiket ini</p>
                <p className="mt-1 text-[13px] leading-relaxed text-amber-900">{reopenNote.note}</p>
                <p className="mt-1.5 text-[11px] text-amber-600">{reopenNote.at}</p>
            </div>
        </div>
    );
}

function EscalationNoteBanner({ note }) {
    return (
        <div className="flex gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5 M5 12l7-7 7 7" /></svg>
            </span>
            <div className="min-w-0">
                <p className="text-[13px] font-bold text-blue-800">Dieskalasi dari Support BPO</p>
                <p className="mt-1 text-[13px] leading-relaxed text-blue-900">{note}</p>
            </div>
        </div>
    );
}

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

function ConfirmModal({ action, ticketId, note, submitting, error, onCancel, onConfirm }) {
    const copy = CONFIRM_COPY[action];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onCancel}>
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 9v4 M12 17h.01 M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
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

export default function SupportTicketDetail({ ticket, comments: initialComments = [], timeline = [], commentsUrl, resolveUrl, escalateUrl, ticketsUrl }) {
    const [comments, setComments] = useState(initialComments);
    const [reply, setReply] = useState('');
    const [sending, setSending] = useState(false);
    const [note, setNote] = useState('');
    const [confirmAction, setConfirmAction] = useState(null);
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

    async function confirmActionSubmit() {
        setSubmitting(true);
        setError('');
        const url = confirmAction === 'resolve' ? resolveUrl : escalateUrl;
        try {
            await apiFetch(url, { method: 'POST', body: JSON.stringify({ note }) });
            if (confirmAction === 'escalate') {
                // Escalating hands the ticket off to Support IT — it no longer
                // belongs to this queue, so reloading the same URL would 403.
                window.location.href = ticketsUrl;
            } else {
                window.location.reload();
            }
        } catch (e) {
            setError(e.message || 'Gagal mengirim tindakan.');
            setSubmitting(false);
        }
    }

    const noteFilled = note.trim().length > 0;

    return (
        <div className="flex flex-col gap-6">
            <a href={ticketsUrl} className="flex w-fit items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                My Tickets
            </a>

            {ticket.reopenNote && <ReopenNoteBanner reopenNote={ticket.reopenNote} />}
            {ticket.escalated && !escalateUrl && ticket.escalationNote && <EscalationNoteBanner note={ticket.escalationNote} />}

            <Card>
                <div className="flex flex-wrap items-center gap-2.5">
                    <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">Mode Support</span>
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
                            <Field label="Layanan" value={ticket.service} />
                            <Field label="Kontak" value={ticket.requester?.email} />
                        </div>
                        {ticket.attachments?.length > 0 && (
                            <div className="mt-4 border-t border-gray-100 pt-4">
                                <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Lampiran</p>
                                <div className="flex flex-col gap-1.5">
                                    {ticket.attachments.map((a) => (
                                        <a
                                            key={a.id}
                                            href={a.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-[13px] font-medium text-blue-600 hover:bg-gray-100 hover:underline"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9" /></svg>
                                            {a.name}
                                        </a>
                                    ))}
                                </div>
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
                                <div key={c.id} className={`max-w-[85%] rounded-2xl px-4 py-3 ${c.authorRole === 'Support' ? 'ml-auto bg-blue-600 text-white' : 'bg-gray-50 text-gray-800'}`}>
                                    <div className={`mb-1 flex items-center gap-2 text-[11px] font-semibold ${c.authorRole === 'Support' ? 'text-blue-100' : 'text-gray-500'}`}>
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
                                    placeholder="Tulis tanggapan untuk requester… (mis. minta detail transaksi, konfirmasi penyelesaian)"
                                    className="flex-1 resize-none rounded-xl border border-gray-200 px-3.5 py-2.5 text-[13px] outline-none focus:border-blue-400"
                                />
                                <button
                                    onClick={sendReply}
                                    disabled={sending || !reply.trim()}
                                    className="rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {sending ? 'Mengirim…' : 'Kirim Tanggapan'}
                                </button>
                            </div>
                        )}
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card title="Aksi Penanganan">
                        <div className="flex items-center justify-between text-[13px]">
                            <span className="text-gray-400">Status saat ini</span>
                            <StatusBadge status={ticket.status} />
                        </div>
                        <div className="mt-3 flex items-center justify-between text-[13px]">
                            <span className="text-gray-400">PIC</span>
                            <span className="text-right font-semibold text-blue-600">
                                {ticket.people?.pic?.name}
                                <span className="block text-[11px] font-normal text-gray-400">{ticket.people?.pic?.role}</span>
                            </span>
                        </div>

                        {ticket.escalated && escalateUrl && (
                            <div className="mt-3 border-t border-gray-100 pt-3">
                                <p className="text-[12px] text-gray-500">Tiket Sudah Dieskalasi ke Tim IT.</p>
                                <div className="mt-2 flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-[12px] font-semibold text-emerald-700">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                    Sudah dieskalasi ke Tim IT Lanjutan
                                </div>
                            </div>
                        )}

                        {ticket.canAct ? (
                            <>
                                <label className="mb-1.5 mt-4 block text-[13px] font-bold text-gray-800">Catatan (wajib)</label>
                                <textarea
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    rows={4}
                                    placeholder="Tulis catatan penanganan…"
                                    className="w-full resize-none rounded-xl border border-gray-200 px-3.5 py-3 text-[13px] outline-none focus:border-blue-400"
                                />
                                {!noteFilled && <p className="mt-1.5 text-xs font-medium text-gray-400">Isi catatan untuk mengaktifkan tombol di bawah.</p>}

                                <div className="mt-4 flex flex-col gap-2.5">
                                    <button
                                        onClick={() => setConfirmAction('resolve')}
                                        disabled={!noteFilled}
                                        className="flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-[13px] font-bold text-gray-500 enabled:bg-blue-600 enabled:text-white enabled:hover:bg-blue-700 disabled:cursor-not-allowed"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                        Service Closed
                                    </button>
                                    {escalateUrl && (
                                        <button
                                            onClick={() => setConfirmAction('escalate')}
                                            disabled={!noteFilled}
                                            className="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-100 px-4 py-2.5 text-[13px] font-bold text-gray-500 enabled:border-amber-200 enabled:bg-amber-50 enabled:text-amber-700 enabled:hover:bg-amber-100 disabled:cursor-not-allowed"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5 M5 12l7-7 7 7" /></svg>
                                            Eskalasi IT
                                        </button>
                                    )}
                                </div>

                                <p className="mt-3 text-[11px] leading-relaxed text-gray-400">
                                    Tindakan tercatat di riwayat status &amp; audit trail. Requester menerima notifikasi.
                                </p>
                            </>
                        ) : null}
                    </Card>

                    <Card title="SLA">
                        <div className="flex items-center justify-between">
                            <span className="text-[13px] font-semibold text-gray-700">Target Penyelesaian</span>
                            <span className={`text-[13px] font-bold ${ticket.sla?.kind === 'breach' ? 'text-red-600' : ticket.sla?.kind === 'ontrack' ? 'text-emerald-600' : 'text-gray-700'}`}>
                                {ticket.sla?.label}
                            </span>
                        </div>
                        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                className={`h-full rounded-full ${ticket.sla?.kind === 'breach' ? 'bg-red-500' : ticket.sla?.kind === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                style={{ width: `${ticket.sla?.pct ?? 0}%` }}
                            />
                        </div>
                    </Card>

                    <Card title="People">
                        <div className="flex flex-col gap-3">
                            {[ticket.people?.requester, ticket.people?.approver, ...(ticket.people?.support ?? [])].filter(Boolean).map((p, i) => (
                                <div key={`${p.name}-${i}`} className="flex items-center gap-2.5">
                                    <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${p.role?.startsWith('Support') ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>
                                        {p.name.split(' ').map((x) => x[0]).slice(0, 2).join('')}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-[13px] font-semibold text-gray-900">{p.name}</span>
                                        <span className="block truncate text-[11px] text-gray-400">{p.role}</span>
                                        {p.email && <span className="block truncate text-[11px] text-blue-600">{p.email}</span>}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Card>

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

            {confirmAction && (
                <ConfirmModal
                    action={confirmAction}
                    ticketId={ticket.id}
                    note={note}
                    submitting={submitting}
                    error={error}
                    onCancel={() => !submitting && setConfirmAction(null)}
                    onConfirm={confirmActionSubmit}
                />
            )}
        </div>
    );
}

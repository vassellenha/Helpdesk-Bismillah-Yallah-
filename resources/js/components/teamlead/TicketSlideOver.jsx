import { useEffect, useState } from 'react';
import { apiFetch } from '../../lib/api';
import { StatusBadge, PriorityBadge } from '../StatusBadge';
import RemindModal from './RemindModal';
import ReassignModal from './ReassignModal';
import RaisePriorityModal from './RaisePriorityModal';

const STEP_STYLE = {
    done: { dot: 'bg-emerald-500', line: 'bg-emerald-500', text: 'text-gray-900' },
    current: { dot: 'bg-blue-500 ring-4 ring-blue-100', line: 'bg-gray-200', text: 'text-blue-700' },
    pending: { dot: 'bg-gray-300', line: 'bg-gray-200', text: 'text-gray-400' },
};
const TL_STYLE = { done: 'bg-emerald-500', current: 'bg-blue-500', pending: 'bg-gray-300', rejected: 'bg-red-500' };

function SlaPill({ kind, label }) {
    const style = kind === 'breach' ? 'text-red-600' : kind === 'warning' ? 'text-amber-600' : kind === 'none' ? 'text-gray-400' : 'text-emerald-600';
    return <span className={`flex items-center gap-1.5 text-[13px] font-bold ${style}`}><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2"/></svg>{label}</span>;
}

function ApprovalFlow({ flow }) {
    if (!flow) return null;
    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <p className="text-[11px] font-bold uppercase tracking-wide text-amber-700">Alur Approval</p>
                <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500">{flow.type}</span>
            </div>
            <div className="flex items-stretch rounded-2xl bg-white p-4 shadow-sm">
                {flow.steps.map((s, i) => {
                    const st = STEP_STYLE[s.state] ?? STEP_STYLE.pending;
                    return (
                        <div key={i} className="flex flex-1 flex-col items-center gap-2 text-center">
                            <div className="flex w-full items-center justify-center">
                                <span className={`h-0.5 flex-1 ${i === 0 ? 'bg-transparent' : STEP_STYLE[flow.steps[i - 1].state]?.line ?? 'bg-gray-200'}`} />
                                <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-white ${st.dot}`}>
                                    {s.state === 'done' ? <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l4 4 10-11"/></svg> : <span className="h-1.5 w-1.5 rounded-full bg-white" />}
                                </span>
                                <span className={`h-0.5 flex-1 ${i === flow.steps.length - 1 ? 'bg-transparent' : st.line}`} />
                            </div>
                            <div>
                                <p className={`text-[11.5px] font-bold ${st.text}`}>{s.name}</p>
                                <p className="text-[10px] text-gray-400">{s.sub}</p>
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className="mt-2 flex items-center gap-1.5 text-[11.5px] font-semibold text-emerald-600">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M8.5 12l2.5 2.5 4.5-5"/></svg>
                {flow.note}
            </p>
        </div>
    );
}

export default function TicketSlideOver({ ticketId, remindUrlBase, onClose, onChanged }) {
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
            flash('Catatan ditambahkan.');
        } catch (e) { flash(e.message || 'Gagal menambah catatan.'); }
    }

    const t = data?.ticket;
    const row = t ? { id: t.id, subject: t.subject, agent: t.agent, agentId: t.agentId, priority: t.priority, sla: t.sla, subcategory: t.subcategory, service: t.service } : null;

    return (
        <div className="fixed inset-0 z-40 flex justify-end bg-gray-900/45" onMouseDown={onClose}>
            <div className="flex h-full w-[33vw] min-w-[420px] max-w-full flex-col bg-gray-50 shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                {loading || !t ? (
                    <div className="flex flex-1 items-center justify-center text-sm text-gray-400">{loading ? 'Memuat…' : 'Tiket tidak ditemukan.'}</div>
                ) : (
                    <>
                        <div className="border-b border-gray-200 bg-white px-6 py-5">
                            <div className="flex items-center justify-between">
                                <span className="text-[13px] font-bold text-blue-600">{t.id}</span>
                                <button onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                            </div>
                            <h1 className="mt-1 text-xl font-extrabold tracking-tight text-gray-900">{t.subject}</h1>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-600">{t.type}</span>
                                <PriorityBadge priority={t.priority} />
                                <span className="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600">{t.service}</span>
                                <StatusBadge status={t.status} />
                                <span className="ml-auto"><SlaPill kind={t.slaKind} label={t.sla} /></span>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto px-6 py-5">
                            <div className="grid grid-cols-2 gap-x-5 gap-y-4">
                                {[['Pelapor', t.requester?.name ?? '—'], ['Unit Kerja', t.requester?.unit ?? '—'], ['Aplikasi', t.service], ['Agen Ditugaskan', t.agent], ['Kategori', t.type], ['Sub-Kategori', t.subcategory]].map(([k, v]) => (
                                    <div key={k}>
                                        <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{k}</p>
                                        <p className="mt-0.5 text-[13px] font-semibold text-gray-900">{v}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6">
                                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-700">Deskripsi Masalah</p>
                                <div className="rounded-2xl bg-white p-4 text-[13px] leading-relaxed text-gray-700 shadow-sm">{t.description || 'Tidak ada deskripsi.'}</div>
                            </div>

                            <div className="mt-6"><ApprovalFlow flow={data.approvalFlow} /></div>

                            <div className="mt-6">
                                <p className="mb-3 text-[11px] font-bold uppercase tracking-wide text-amber-700">Timeline SLA</p>
                                <div className="flex flex-col">
                                    {data.timeline.map((s, i) => (
                                        <div key={i} className="flex gap-3">
                                            <div className="flex flex-col items-center">
                                                <span className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${TL_STYLE[s.state] ?? TL_STYLE.pending}`} />
                                                {i < data.timeline.length - 1 && <span className="my-1 w-px flex-1 bg-gray-200" style={{ minHeight: 16 }} />}
                                            </div>
                                            <div className="pb-2.5">
                                                <p className="text-[12.5px] font-semibold text-gray-800">{s.label}</p>
                                                {(s.who || s.at) && <p className="text-[11px] text-gray-400">{[s.who, s.at].filter(Boolean).join(' · ')}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="mt-6">
                                <p className="mb-3 text-[11px] font-bold uppercase tracking-wide text-amber-700">Riwayat Aktivitas</p>
                                <div className="flex flex-col gap-3.5">
                                    {comments.map((c) => (
                                        <div key={c.id} className="flex gap-3">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-600">{(c.authorName || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}</span>
                                            <div><p className="text-[12.5px] text-gray-800"><span className="font-bold">{c.authorName}</span> <span className="text-gray-400">· {c.at}</span></p><p className="mt-0.5 text-[12.5px] text-gray-700">{c.message}</p></div>
                                        </div>
                                    ))}
                                    {comments.length === 0 && <p className="text-sm text-gray-400">Belum ada aktivitas.</p>}
                                </div>
                            </div>

                            <div className="mt-6">
                                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-amber-700">Catatan Internal</p>
                                <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} placeholder="Tambah catatan untuk tim…" className="w-full resize-none rounded-2xl border border-gray-200 bg-white p-3.5 text-[13px] outline-none focus:border-blue-400" />
                                <button onClick={saveNote} disabled={!note.trim()} className="mt-2 rounded-xl bg-gray-900 px-4 py-2 text-[12.5px] font-bold text-white hover:bg-gray-800 disabled:opacity-40">Tambah Catatan</button>
                            </div>
                        </div>

                        <div className="flex gap-2 border-t border-gray-200 bg-white px-6 py-3.5">
                            <button onClick={() => setModal('remind')} title="Kirim teguran" className="flex h-11 w-11 items-center justify-center rounded-xl border border-red-200 text-red-600 hover:bg-red-50"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg></button>
                            <button onClick={() => setModal('reassign')} className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-[13px] font-bold text-white hover:bg-blue-700"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 8h13 M16 5l4 3-4 3 M17 16H4 M8 13l-4 3 4 3"/></svg>Alihkan</button>
                            <button onClick={() => setModal('raise')} className="flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-[13px] font-bold text-gray-700 hover:bg-gray-50"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5 M6 11l6-6 6 6"/></svg>Prioritas</button>
                        </div>
                    </>
                )}
            </div>

            {modal === 'remind' && row && <RemindModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSent={(res) => { setModal(null); flash(res?.message ?? 'Teguran terkirim.'); load(); onChanged?.(); }} />}
            {modal === 'reassign' && row && <ReassignModal ticket={row} agents={data.agentOptions} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onReassigned={(res) => { setModal(null); flash(res?.message ?? 'Tiket dialihkan.'); load(); onChanged?.(); }} />}
            {modal === 'raise' && row && <RaisePriorityModal ticket={row} remindUrlBase={remindUrlBase} onClose={() => setModal(null)} onSaved={(res) => { setModal(null); flash(res?.message ?? 'Prioritas diperbarui.'); load(); onChanged?.(); }} />}

            {toast && <div className="fixed bottom-6 left-1/2 z-[70] -translate-x-1/2 rounded-xl bg-gray-900 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>}
        </div>
    );
}

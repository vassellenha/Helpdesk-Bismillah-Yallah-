import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';

const TABS = [['sub', 'Sub-kategori sama'], ['app', 'Aplikasi sama'], ['all', 'Semua aplikasi']];

function loadState(load) {
    if (load <= 5) return { label: 'Ringan', text: 'text-emerald-600', bar: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-600' };
    if (load <= 9) return { label: 'Sedang', text: 'text-amber-600', bar: 'bg-amber-500', pill: 'bg-amber-50 text-amber-700' };
    return { label: 'Padat', text: 'text-red-600', bar: 'bg-red-500', pill: 'bg-red-50 text-red-600' };
}

function AgentCard({ a, onPick, saving, recommended }) {
    const ls = loadState(a.load);
    return (
        <div className={`rounded-2xl border bg-white p-4 ${recommended ? 'border-emerald-400' : 'border-gray-200'}`}>
            <div className="flex items-center gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">{a.initials}</span>
                <div className="min-w-0 flex-1">
                    <p className="text-[14px] font-bold text-gray-900">{a.name}</p>
                    <p className="text-[11.5px] text-gray-400">Beban {a.load} tiket aktif · Support {a.type}</p>
                </div>
                <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold ${ls.pill}`}>{recommended ? 'Beban Teringan' : ls.label}</span>
            </div>
            <div className="mt-3 flex items-center gap-3">
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100"><div className={`h-full rounded-full ${ls.bar}`} style={{ width: `${Math.min(100, Math.round((a.load / 20) * 100))}%` }} /></div>
                <span className={`text-[11px] font-bold ${ls.text}`}>{ls.label}</span>
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2">
                {[['RESOLVED', a.resolved], ['SLA', a.slaPct === null ? '—' : `${a.slaPct}%`], ['AVG RESOLUSI', a.avgResolution]].map(([k, v]) => (
                    <div key={k} className="rounded-xl bg-gray-50 px-2.5 py-2">
                        <p className="text-[9px] font-bold uppercase tracking-wide text-gray-400">{k}</p>
                        <p className={`mt-0.5 text-[15px] font-extrabold ${k === 'SLA' && a.slaPct !== null ? (a.slaPct >= 90 ? 'text-emerald-600' : 'text-amber-600') : 'text-gray-900'}`}>{v}</p>
                    </div>
                ))}
            </div>
            {a.subjects.length > 0 && (
                <p className="mt-3 text-[11px] leading-relaxed text-gray-500">
                    <span className="font-bold uppercase tracking-wide text-gray-400">Subjek ({a.subjects.length}) </span>
                    {a.subjects.slice(0, 4).join(', ')}{a.subjects.length > 4 ? ', …' : ''}
                </p>
            )}
            <button onClick={() => onPick(a.id)} disabled={saving} className="mt-3 w-full rounded-xl bg-blue-600 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 disabled:opacity-60">Alihkan ke {a.name.split(' ')[0]}</button>
        </div>
    );
}

export default function ReassignModal({ ticket, agents = [], remindUrlBase, onClose, onReassigned }) {
    const [tab, setTab] = useState('sub');
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const pool = useMemo(() => agents.filter((a) => a.id !== ticket.agentId), [agents, ticket.agentId]);

    const recommended = useMemo(() => {
        const inSub = pool.filter((a) => ticket.subcategory && a.subcats.includes(ticket.subcategory));
        const inApp = pool.filter((a) => ticket.service && a.apps.includes(ticket.service));
        const base = inSub.length ? inSub : inApp.length ? inApp : pool;
        return [...base].sort((x, y) => x.load - y.load)[0] ?? null;
    }, [pool, ticket]);

    const list = useMemo(() => {
        let out = pool;
        if (tab === 'sub' && ticket.subcategory) out = out.filter((a) => a.subcats.includes(ticket.subcategory));
        else if (tab === 'app' && ticket.service) out = out.filter((a) => a.apps.includes(ticket.service));
        const q = search.trim().toLowerCase();
        if (q) out = out.filter((a) => (a.name + ' ' + a.subjects.join(' ')).toLowerCase().includes(q));
        return [...out].sort((x, y) => x.load - y.load);
    }, [pool, tab, ticket, search]);

    async function pick(agentId) {
        setSaving(true);
        setError('');
        try {
            const res = await apiFetch(`${remindUrlBase}/${ticket.id}/reassign`, { method: 'POST', body: JSON.stringify({ agent_id: agentId }) });
            onReassigned?.(res);
        } catch (e) {
            setError(e.message || 'Gagal mengalihkan tiket.');
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[55] flex items-center justify-center bg-gray-900/40 p-4" onMouseDown={onClose}>
            <div className="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 className="text-[17px] font-extrabold text-gray-900">Alihkan Tiket</h2>
                        <p className="mt-0.5 text-[12.5px] text-gray-400">{ticket.id} · pilih tujuan berdasarkan agen, subjek, aplikasi, atau kategori</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>

                <div className="border-b border-gray-100 px-6 py-3">
                    <div className="relative">
                        <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg></span>
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari subjek, PIC, atau agen tujuan…" className="w-full rounded-xl border border-gray-200 py-2.5 pl-10 pr-4 text-[13px] outline-none focus:border-blue-400" />
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-4">
                    {recommended && (
                        <div className="mb-5 rounded-2xl border-[1.5px] border-emerald-400 bg-emerald-50/60 p-4">
                            <p className="flex items-center gap-2 text-[12px] font-extrabold uppercase tracking-wide text-emerald-600">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2l2.4 5 5.6.5-4.2 3.7 1.3 5.5L12 19l-5.1 2.7 1.3-5.5L4 12.5l5.6-.5Z"/></svg>
                                Rekomendasi PIC
                            </p>
                            <p className="mt-2 text-[11.5px] text-gray-600">Sub-kategori terdekat: <b>{ticket.subcategory || '—'}</b> · {ticket.service || '—'}</p>
                            <div className="mt-3 flex items-center gap-3 rounded-xl border border-emerald-300 bg-white p-3">
                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-600">{recommended.initials}</span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[13.5px] font-bold text-gray-900">{recommended.name}</p>
                                    <p className="text-[11px] text-gray-400">Mengerjakan {recommended.subjects.length} subjek · beban {recommended.load} tiket</p>
                                </div>
                                <button onClick={() => pick(recommended.id)} disabled={saving} className="rounded-lg bg-emerald-600 px-4 py-2 text-[12px] font-bold text-white hover:bg-emerald-700 disabled:opacity-60">Alihkan</button>
                            </div>
                        </div>
                    )}

                    <div className="mb-3 flex items-center justify-between gap-2">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-gray-400">Agen Tujuan</p>
                        <div className="flex flex-wrap gap-1.5">
                            {TABS.map(([k, l]) => (
                                <button key={k} onClick={() => setTab(k)} className={`rounded-full px-3 py-1.5 text-[11.5px] font-semibold transition ${tab === k ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-300' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'}`}>{l}</button>
                            ))}
                        </div>
                    </div>

                    {error && <p className="mb-3 rounded-lg bg-red-50 px-3 py-2 text-[12px] font-medium text-red-600">{error}</p>}

                    <div className="flex flex-col gap-3">
                        {list.map((a) => <AgentCard key={a.id} a={a} onPick={pick} saving={saving} recommended={recommended && a.id === recommended.id} />)}
                        {list.length === 0 && <p className="rounded-xl bg-gray-50 py-8 text-center text-[12.5px] text-gray-400">Tidak ada agen pada lingkup ini. Coba "Aplikasi sama" atau "Semua aplikasi".</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

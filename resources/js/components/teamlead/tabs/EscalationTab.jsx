import { useMemo, useState } from 'react';
import { StatusBadge, PriorityBadge } from '../../StatusBadge';
import { MetricCard, ICON } from '../ui';

function BpoTable({ rows, actions }) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? rows.filter((e) => `${e.id} ${e.subject} ${e.from} ${e.to} ${e.service}`.toLowerCase().includes(q)) : rows;
    }, [rows, query]);

    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20V9 M8 12l4-4 4 4 M8 4h8"/></svg></span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">Eskalasi BPO → Tim IT <span className="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">dipantau</span></h2>
                        <p className="mt-0.5 text-xs text-gray-400">Tiket yang dieskalasi Support BPO ke Support IT karena perlu penanganan lanjutan (view-only).</p>
                    </div>
                </div>
                <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari tiket, agen, aplikasi…" className="w-56 rounded-xl border border-gray-200 px-3.5 py-2.5 text-[13px] text-gray-700 outline-none focus:border-blue-400" />
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[920px] text-sm">
                    <thead>
                        <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                            <th className="px-4 py-3.5 pl-6 text-left">No. Tiket</th>
                            <th className="px-4 py-3.5 text-left">Subjek &amp; Alasan</th>
                            <th className="px-4 py-3.5 text-left">Prioritas</th>
                            <th className="px-4 py-3.5 text-left">Dari (BPO) → Ke (IT)</th>
                            <th className="px-4 py-3.5 text-left">Dieskalasi</th>
                            <th className="px-4 py-3.5 pr-6 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((e) => (
                            <tr key={e.id} className="border-b border-gray-50 last:border-0 hover:bg-blue-50/30">
                                <td className="px-4 py-4 pl-6"><button onClick={() => actions.openTicket?.(e.id)} className="font-bold text-blue-600 hover:underline">{e.id}</button></td>
                                <td className="px-4 py-4">
                                    <p className="max-w-[280px] truncate text-[13px] font-semibold text-gray-900">{e.subject}</p>
                                    {e.note && <p className="mt-0.5 max-w-[280px] truncate text-[11.5px] text-gray-400">{e.note}</p>}
                                </td>
                                <td className="px-4 py-4"><PriorityBadge priority={e.priority} /></td>
                                <td className="px-4 py-4">
                                    <span className="flex items-center gap-1.5 text-[12px] text-gray-700">
                                        <span className="rounded-md bg-gray-100 px-2 py-0.5 font-semibold">{e.from}</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14 M13 6l6 6-6 6"/></svg>
                                        <span className="rounded-md bg-blue-50 px-2 py-0.5 font-semibold text-blue-700">{e.to}</span>
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-[12px] text-gray-500">{e.escalatedAt}</td>
                                <td className="px-4 py-4 pr-6"><StatusBadge status={e.status} /></td>
                            </tr>
                        ))}
                        {filtered.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-emerald-600">Belum ada tiket yang dieskalasi BPO ke Tim IT. 🎉</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function BreachTable({ rows, actions }) {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex items-center gap-3 p-5">
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900">Eskalasi SLA Breach <span className="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">{rows.length} dipantau</span></h2>
                    <p className="mt-0.5 text-xs text-gray-400">Tiket aktif yang sudah melewati SLA — dipantau supervisor (view-only).</p>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[680px] text-sm">
                    <thead>
                        <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                            <th className="px-4 py-3.5 pl-6 text-left">No. Tiket</th>
                            <th className="px-4 py-3.5 text-left">Subjek</th>
                            <th className="px-4 py-3.5 text-left">Aplikasi</th>
                            <th className="px-4 py-3.5 text-left">Prioritas</th>
                            <th className="px-4 py-3.5 text-left">Keterlambatan</th>
                            <th className="px-4 py-3.5 pr-6 text-left">PIC</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.id} className="border-b border-gray-50 last:border-0 hover:bg-blue-50/30">
                                <td className="px-4 py-4 pl-6"><button onClick={() => actions.openTicket?.(r.id)} className="font-bold text-blue-600 hover:underline">{r.id}</button></td>
                                <td className="px-4 py-4"><p className="max-w-[240px] truncate text-gray-800">{r.subject}</p></td>
                                <td className="px-4 py-4"><span className="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600">{r.service}</span></td>
                                <td className="px-4 py-4"><PriorityBadge priority={r.priority} /></td>
                                <td className="px-4 py-4"><span className="rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-600">{r.overdue}</span></td>
                                <td className="px-4 py-4 pr-6 text-gray-700">{r.agent}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-emerald-600">Tidak ada tiket breach. 🎉</td></tr>}
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
                <MetricCard label="Eskalasi BPO → IT" value={stats.total} icon={ICON.escalate} iconBg="bg-blue-50" iconColor="text-blue-600" sub={`${stats.active} sedang ditangani`} />
                <MetricCard label="SLA Breach Aktif" value={stats.breach} icon={ICON.warning} iconBg="bg-red-50" iconColor="text-red-600" sub="Sudah lewat batas" />
                <MetricCard label="Eskalasi Selesai" value={escalations.filter((e) => e.done).length} icon={ICON.check} iconBg="bg-emerald-50" iconColor="text-emerald-600" />
            </div>

            <div className="flex gap-1.5 self-start rounded-xl bg-gray-100 p-1">
                {[['bpo', `BPO → IT · ${stats.total}`], ['breach', `SLA Breach · ${stats.breach}`]].map(([k, l]) => (
                    <button key={k} onClick={() => setView(k)} className={`rounded-lg px-4 py-2 text-[13px] font-bold transition ${view === k ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}>{l}</button>
                ))}
            </div>

            {view === 'bpo' ? <BpoTable rows={escalations} actions={actions} /> : <BreachTable rows={breachEscalations} actions={actions} />}
        </div>
    );
}

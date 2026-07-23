import { useMemo, useState } from 'react';
import { PriorityBadge } from '../../../StatusBadge';

const AVAIL = {
    Online: { dot: 'bg-emerald-500', text: 'text-emerald-600', bg: 'bg-emerald-50' },
    Sibuk: { dot: 'bg-amber-500', text: 'text-amber-700', bg: 'bg-amber-50' },
    Cuti: { dot: 'bg-gray-400', text: 'text-gray-500', bg: 'bg-gray-100' },
};

function loadBar(load) {
    if (load >= 6) return 'bg-red-500';
    if (load >= 3) return 'bg-amber-500';
    return 'bg-blue-500';
}

function SlaPill({ kind, label }) {
    const style = kind === 'breach' ? 'bg-red-50 text-red-600' : kind === 'warning' ? 'bg-amber-50 text-amber-600' : kind === 'none' ? 'bg-gray-100 text-gray-500' : 'bg-emerald-50 text-emerald-600';
    return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold ${style}`}>{label}</span>;
}

function AgentDetail({ agent, onClose, onOpenTicket }) {
    const a = AVAIL[agent.availability] ?? AVAIL.Online;
    return (
        <div className="fixed inset-0 z-50 flex justify-end bg-gray-900/40" onMouseDown={onClose}>
            <div className="flex h-full w-[440px] max-w-full flex-col bg-gray-50 shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-center gap-3.5 border-b border-gray-200 bg-white px-6 py-5">
                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-base font-extrabold text-blue-700">{agent.initials}</span>
                    <div className="min-w-0 flex-1">
                        <p className="text-lg font-extrabold text-gray-900">{agent.name}</p>
                        <span className={`mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${a.bg} ${a.text}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${a.dot}`} />{agent.availability} · Support {agent.type}
                        </span>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6">
                    <div className="grid grid-cols-2 gap-3">
                        {[['Beban Aktif', agent.load, agent.load >= 6 ? 'text-red-600' : agent.load >= 3 ? 'text-amber-600' : 'text-emerald-600'], ['Resolved', agent.resolved, 'text-gray-900'], ['Produktivitas', agent.productivity === null ? '—' : `${agent.productivity}%`, 'text-gray-900'], ['Avg Resolusi', agent.avgResolution, 'text-gray-900']].map(([label, val, color]) => (
                            <div key={label} className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                                <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{label}</p>
                                <p className={`mt-1 text-2xl font-extrabold ${color}`}>{val}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-5 flex items-center justify-between">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-gray-500">Tiket Sedang Dipegang</p>
                        <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-500">{agent.tickets.length} tiket</span>
                    </div>
                    <div className="mt-3 flex flex-col gap-2.5">
                        {agent.tickets.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => onOpenTicket?.(t.id)}
                                className="block w-full cursor-pointer rounded-2xl border border-gray-200 bg-white p-3.5 text-left shadow-sm transition hover:border-blue-300 hover:bg-blue-50/40"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="text-[12px] font-bold text-blue-600">{t.id}</span>
                                    <span className="ml-auto"><SlaPill kind={t.slaKind} label={t.sla} /></span>
                                </div>
                                <p className="mt-1.5 text-[13px] font-semibold text-gray-900">{t.subject}</p>
                                <div className="mt-2 flex items-center gap-2">
                                    <span className="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">{t.service}</span>
                                    <PriorityBadge priority={t.priority} />
                                </div>
                            </button>
                        ))}
                        {agent.tickets.length === 0 && <p className="rounded-2xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-400">Tidak ada tiket aktif.</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function WorkloadTable({ rows = [], onOpenTicket }) {
    const [query, setQuery] = useState('');
    const [detail, setDetail] = useState(null);

    const summary = useMemo(() => {
        const totalLoad = rows.reduce((s, a) => s + a.load, 0);
        return {
            totalLoad,
            avg: rows.length ? (totalLoad / rows.length).toFixed(1) : '0',
            padat: rows.filter((a) => a.load >= 6).length,
            sedang: rows.filter((a) => a.load >= 3 && a.load < 6).length,
            ringan: rows.filter((a) => a.load < 3).length,
            online: rows.filter((a) => a.availability === 'Online').length,
            sibuk: rows.filter((a) => a.availability === 'Sibuk').length,
        };
    }, [rows]);

    const maxLoad = Math.max(1, ...rows.map((a) => a.load));
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? rows.filter((a) => a.name.toLowerCase().includes(q)) : rows;
    }, [rows, query]);

    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900">Support Workload</h2>
                    <p className="mt-0.5 text-xs text-gray-400">Active tickets &amp; produktivitas per agen</p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <div className="flex items-center gap-3 rounded-xl bg-gray-50 px-3.5 py-2">
                        <div><p className="text-base font-extrabold leading-none text-gray-900">{summary.totalLoad}</p><p className="text-[10px] font-semibold text-gray-400">Total aktif</p></div>
                        <span className="h-6 w-px bg-gray-200" />
                        <div><p className="text-base font-extrabold leading-none text-gray-900">{summary.avg}</p><p className="text-[10px] font-semibold text-gray-400">Rata / agen</p></div>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-600"><span className="h-1.5 w-1.5 rounded-full bg-red-500" />{summary.padat} Padat</span>
                        <span className="flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700"><span className="h-1.5 w-1.5 rounded-full bg-amber-500" />{summary.sedang} Sedang</span>
                        <span className="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-600"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />{summary.ringan} Ringan</span>
                    </div>
                </div>
            </div>

            <div className="px-5 pb-3">
                <div className="relative max-w-md">
                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg>
                    </span>
                    <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari agen support…" className="w-full rounded-xl border border-gray-200 py-2.5 pl-10 pr-4 text-[13px] text-gray-700 outline-none focus:border-blue-400" />
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[860px]">
                    <div className="grid grid-cols-[190px_1fr_70px_100px_90px_74px_50px] gap-3 border-y border-gray-100 bg-gray-50 px-6 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-gray-400">
                        <span>Agent</span><span>Active Load</span><span className="text-right">Resolved</span><span className="text-right">Avg Response</span><span className="text-right">Avg Resolusi</span><span className="text-right">Produktif</span><span className="text-right">Detail</span>
                    </div>
                    {filtered.map((a) => {
                        const av = AVAIL[a.availability] ?? AVAIL.Online;
                        return (
                            <div key={a.id} onClick={() => setDetail(a)} className="grid cursor-pointer grid-cols-[190px_1fr_70px_100px_90px_74px_50px] items-center gap-3 border-b border-gray-50 px-6 py-3.5 last:border-0 hover:bg-blue-50/30">
                                <div className="flex items-center gap-2.5">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700">{a.initials}</span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-semibold text-gray-900">{a.name}</p>
                                        <span className={`inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold ${av.bg} ${av.text}`}><span className={`h-1 w-1 rounded-full ${av.dot}`} />{a.availability}</span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 pr-4">
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div className={`h-full rounded-full ${loadBar(a.load)}`} style={{ width: `${Math.round((a.load / maxLoad) * 100)}%` }} /></div>
                                    <span className="w-5 text-[13px] font-bold text-gray-900">{a.load}</span>
                                </div>
                                <span className="text-right text-[13px] font-semibold text-gray-700">{a.resolved}</span>
                                <span className="text-right text-[12.5px] text-gray-500">{a.avgResponse}</span>
                                <span className="text-right text-[12.5px] text-gray-700">{a.avgResolution}</span>
                                <span className={`text-right text-[13px] font-bold ${a.productivity === null ? 'text-gray-400' : a.productivity >= 90 ? 'text-emerald-600' : a.productivity >= 60 ? 'text-amber-600' : 'text-red-600'}`}>{a.productivity === null ? '—' : `${a.productivity}%`}</span>
                                <span className="flex justify-end text-blue-500"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
                            </div>
                        );
                    })}
                    {filtered.length === 0 && <div className="px-6 py-10 text-center text-sm text-gray-400">Tidak ada agen yang cocok.</div>}
                </div>
            </div>

            {detail && <AgentDetail agent={detail} onClose={() => setDetail(null)} onOpenTicket={onOpenTicket} />}
        </div>
    );
}

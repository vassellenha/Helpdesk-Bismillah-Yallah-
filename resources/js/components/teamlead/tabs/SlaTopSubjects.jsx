import { useMemo, useState } from 'react';

const PER_PAGE = 6;

const TYPE_STYLE = {
    Incident: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text',
    Service: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text',
    Access: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text',
};

function compColor(pct) {
    if (pct >= 95) return '#059669';
    if (pct >= 90) return '#d97706';
    return '#dc2626';
}
function barColor(pct) {
    if (pct >= 95) return 'bg-blue-400';
    if (pct >= 90) return 'bg-amber-500';
    return 'bg-red-500';
}

export default function SlaTopSubjects({ rows = [] }) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [open, setOpen] = useState(null);

    const maxVolume = useMemo(() => Math.max(1, ...rows.map((r) => r.volume)), [rows]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) => `${r.subject} ${r.subCat} ${r.app} ${r.type}`.toLowerCase().includes(q));
    }, [rows, query]);

    const pages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
    const current = Math.min(page, pages);
    const start = (current - 1) * PER_PAGE;
    const pageRows = filtered.slice(start, start + PER_PAGE);

    function goto(p) {
        setPage(Math.max(1, Math.min(pages, p)));
        setOpen(null);
    }

    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-baseline justify-between gap-2 p-5 pb-3">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">SLA Compliance — Tiket Penyumbang Terbanyak</h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">Subjek dengan volume tertinggi &amp; kontribusi breach terbesar</p>
                </div>
                <span className="text-[11px] font-bold text-gray-400 dark:text-ink-3">Bulan ini</span>
            </div>

            <div className="px-5 pb-3">
                <div className="relative">
                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-ink-3">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg>
                    </span>
                    <input
                        value={query}
                        onChange={(e) => { setQuery(e.target.value); setPage(1); setOpen(null); }}
                        placeholder="Cari subjek, sub-kategori, atau aplikasi…"
                        className="w-full rounded-xl border border-gray-200 dark:border-edge-strong py-2.5 pl-10 pr-4 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400"
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[820px]">
                    <div className="grid grid-cols-[36px_1fr_120px_90px_80px_100px_36px] gap-3 border-y border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                        <span>#</span><span>Subjek</span><span>Aplikasi</span><span className="text-right">Volume</span><span className="text-right">Breach</span><span className="text-right">Compliance</span><span />
                    </div>

                    {pageRows.map((r, i) => {
                        const rank = start + i + 1;
                        const expanded = open === r.subject;
                        return (
                            <div key={r.subject} className="border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03]">
                                <div
                                    onClick={() => setOpen(expanded ? null : r.subject)}
                                    className={`grid cursor-pointer grid-cols-[36px_1fr_120px_90px_80px_100px_36px] items-center gap-3 px-6 py-3.5 ${expanded ? 'bg-blue-50/40 dark:bg-accent-soft' : 'hover:bg-blue-50/30 dark:hover:bg-panel-hover'}`}
                                >
                                    <span className="text-[12px] font-extrabold text-gray-400 dark:text-ink-3">{rank}</span>
                                    <div className="min-w-0">
                                        <p className="truncate text-[12.5px] font-semibold text-gray-900 dark:text-ink-1">{r.subject}</p>
                                        <div className="mt-1 flex items-center gap-1.5">
                                            <span className={`rounded-full px-1.5 py-0.5 text-[9px] font-bold ${TYPE_STYLE[r.type] ?? TYPE_STYLE.Incident}`}>{r.type}</span>
                                            <span className="text-[10.5px] font-semibold text-gray-400 dark:text-ink-3">{r.subCat}</span>
                                        </div>
                                        <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                                            <div className={`h-full rounded-full ${barColor(r.compliance)}`} style={{ width: `${Math.round((r.volume / maxVolume) * 100)}%` }} />
                                        </div>
                                    </div>
                                    <span className="truncate text-[11.5px] font-semibold text-gray-600 dark:text-ink-2">{r.app}</span>
                                    <span className="text-right text-[13px] font-extrabold text-gray-900 dark:text-ink-1">{r.volume}</span>
                                    <span className="text-right text-[13px] font-bold text-red-600 dark:text-bad-text">{r.breach}</span>
                                    <span className="text-right text-[12.5px] font-bold" style={{ color: compColor(r.compliance) }}>{r.compliance}%</span>
                                    <span className="flex justify-end text-gray-400 dark:text-ink-3">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ transform: expanded ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }}><path d="M6 9l6 6 6-6"/></svg>
                                    </span>
                                </div>

                                {expanded && (
                                    <div className="flex flex-col gap-2 bg-gray-50/50 px-6 pb-4 pl-[70px] pt-1">
                                        <p className="text-[10.5px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">Rincian Masalah Tiket</p>
                                        {r.causes.map((c) => (
                                            <div key={c.name} className="flex items-center gap-3">
                                                <span className="flex-1 truncate text-[12px] text-gray-700 dark:text-ink-2">{c.name}</span>
                                                <div className="h-1.5 w-32 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                                                    <div className="h-full rounded-full bg-red-500" style={{ width: `${Math.round((c.volume / Math.max(1, r.volume)) * 100)}%` }} />
                                                </div>
                                                <span className="w-16 shrink-0 text-right text-[11.5px] font-bold text-gray-700 dark:text-ink-2">{c.volume} tiket</span>
                                                <span className="w-16 shrink-0 text-right text-[11px] font-bold text-red-600 dark:text-bad-text">{c.breach} breach</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {pageRows.length === 0 && (
                        <div className="px-6 py-10 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada subjek yang cocok dengan pencarian.</div>
                    )}
                </div>
            </div>

            {filtered.length > PER_PAGE && (
                <div className="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-6 py-3.5">
                    <span className="text-[12.5px] text-gray-400 dark:text-ink-3">
                        {start + 1}–{Math.min(start + PER_PAGE, filtered.length)} dari {filtered.length} subjek
                    </span>
                    <div className="flex items-center gap-2.5">
                        <button onClick={() => goto(current - 1)} disabled={current <= 1} className="flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-panel-3 px-3 py-1.5 text-[12.5px] font-bold text-gray-700 dark:text-ink-2 disabled:text-gray-400">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                            Sebelumnya
                        </button>
                        <span className="text-[12.5px] font-bold text-gray-700 dark:text-ink-2">Hal {current} / {pages}</span>
                        <button onClick={() => goto(current + 1)} disabled={current >= pages} className={`flex items-center gap-1 rounded-lg px-3.5 py-1.5 text-[12.5px] font-bold ${current >= pages ? 'bg-gray-100 dark:bg-panel-3 text-gray-400 dark:text-ink-3' : 'bg-blue-600 dark:bg-blue-500 text-white hover:bg-blue-700 dark:hover:bg-blue-400'}`}>
                            Berikutnya
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

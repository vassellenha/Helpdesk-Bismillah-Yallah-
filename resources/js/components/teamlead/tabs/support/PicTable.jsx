import { useMemo, useState } from 'react';

const APP_COLORS = ['#dc2626', '#2563eb', '#059669', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

function appColor(name) {
    let h = 0;
    for (let i = 0; i < name.length; i += 1) h = (h * 31 + name.charCodeAt(i)) >>> 0;
    return APP_COLORS[h % APP_COLORS.length];
}

export default function PicTable({ rows = [] }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) => `${r.subject} ${r.pic} ${r.app} ${r.subCat}`.toLowerCase().includes(q));
    }, [rows, query]);

    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M3 20c0-3 2.7-5 6-5s6 2 6 5 M16.5 11a3 3 0 1 0-1.5-5.6 M21 20c0-2.6-1.6-4.4-4-4.9"/></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">PIC per Subjek Tiket</h2>
                        <p className="mt-0.5 text-xs text-gray-400">Penanggung jawab (support) yang mengerjakan tiap subjek layanan</p>
                    </div>
                </div>
                <span className="flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-[11px] font-bold text-gray-500">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M9 21a3 3 0 0 0 6 0"/></svg>
                    Read-only · diatur Administrator
                </span>
            </div>

            <div className="px-5 pb-3">
                <div className="relative max-w-md">
                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z M20 20l-3.6-3.6"/></svg>
                    </span>
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Cari subjek, PIC, aplikasi, atau sub-kategori…"
                        className="w-full rounded-xl border border-gray-200 py-2.5 pl-10 pr-4 text-[13px] text-gray-700 outline-none focus:border-blue-400"
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[720px]">
                    <div className="grid grid-cols-[170px_150px_180px_1fr] gap-3 border-y border-gray-100 bg-gray-50 px-6 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <span>PIC</span><span>Layanan</span><span>Sub-Kategori</span><span>Subjek</span>
                    </div>
                    <div className="max-h-[380px] overflow-y-auto">
                        {filtered.map((r, i) => (
                            <div key={`${r.subject}-${i}`} className="grid grid-cols-[170px_150px_180px_1fr] items-center gap-3 border-b border-gray-50 px-6 py-3 last:border-0 hover:bg-blue-50/30">
                                <span className="flex items-center gap-2 text-[12px] font-semibold text-gray-800">
                                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[9px] font-bold text-blue-700">{r.initials}</span>
                                    <span className="truncate">{r.pic}</span>
                                </span>
                                <span className="flex items-center gap-2 text-[12px] font-bold text-gray-900">
                                    <span className="h-2 w-2 shrink-0 rounded-sm" style={{ backgroundColor: appColor(r.app) }} />
                                    <span className="truncate">{r.app}</span>
                                </span>
                                <span className="truncate text-[11.5px] text-gray-400">{r.subCat}</span>
                                <span className="truncate text-[12.5px] font-semibold text-gray-900">{r.subject}</span>
                            </div>
                        ))}
                        {filtered.length === 0 && <div className="px-6 py-10 text-center text-sm text-gray-400">Tidak ada subjek yang cocok.</div>}
                    </div>
                </div>
            </div>
        </div>
    );
}

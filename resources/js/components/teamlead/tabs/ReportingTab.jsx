import { useState } from 'react';

const TYPE_ICON = {
    sla_compliance: 'M12 2.5l2.3 1.7 2.8-.3 1 2.7 2.5 1.4-.8 2.8.8 2.8-2.5 1.4-1 2.7-2.8-.3L12 21.5l-2.3-1.7-2.8.3-1-2.7-2.5-1.4.8-2.8-.8-2.8 2.5-1.4 1-2.7 2.8.3Z M9 12l2 2 4-4',
    sla_breach: 'M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3Z M10 10l4 4 M14 10l-4 4',
    support_perf: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M3 20c0-3 2.7-5 6-5s6 2 6 5 M16.5 11a3 3 0 1 0-1.5-5.6 M21 20c0-2.6-1.6-4.4-4-4.9',
    ticket_summary: 'M8 3h6l4 4v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M14 3v4h4 M10 12h6 M10 16h6',
    top_incident: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 8v5 M12 16h.01',
};

export default function ReportingTab({ reportTypes = {}, reports = {}, reportUnits = [], reportExportUrl = '' }) {
    const keys = Object.keys(reportTypes);
    const [type, setType] = useState(keys[0] ?? 'sla_compliance');
    const [unit, setUnit] = useState('Semua Unit');
    const [from, setFrom] = useState('2026-07-01');
    const [to, setTo] = useState('2026-07-31');

    const report = reports[type] ?? { title: '', columns: [], rows: [] };

    function download(format) {
        const params = new URLSearchParams({ type, format, from, to, unit });
        window.location.href = `${reportExportUrl}?${params.toString()}`;
    }

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-[320px_1fr]">
            <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 className="mb-4 text-[15px] font-bold text-gray-900">Report Generator</h2>

                <p className="mb-2 text-[12px] font-bold text-amber-700">Jenis Laporan</p>
                <div className="mb-4 flex flex-col gap-1.5">
                    {keys.map((k) => (
                        <button
                            key={k}
                            onClick={() => setType(k)}
                            className={`flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-[13px] font-semibold transition ${type === k ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'}`}
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={TYPE_ICON[k]} /></svg>
                            {reportTypes[k]}
                        </button>
                    ))}
                </div>

                <p className="mb-2 text-[12px] font-bold text-amber-700">Periode</p>
                <div className="mb-4 flex gap-2">
                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[13px] text-gray-700 outline-none focus:border-blue-400" />
                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[13px] text-gray-700 outline-none focus:border-blue-400" />
                </div>

                <p className="mb-2 text-[12px] font-bold text-amber-700">Unit Kerja</p>
                <select value={unit} onChange={(e) => setUnit(e.target.value)} className="mb-5 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-700 outline-none">
                    <option>Semua Unit</option>
                    {reportUnits.map((u) => <option key={u}>{u}</option>)}
                </select>

                <button onClick={() => download('pdf')} className="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5 M9 13h6 M9 17h4"/></svg>
                    Generate Report (PDF)
                </button>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">{report.title}</h2>
                        <p className="mt-0.5 text-xs text-gray-400">Pratinjau · {from} → {to} · {unit}</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={() => download('pdf')} className="flex items-center gap-2 rounded-full border border-red-200 px-4 py-2 text-[12px] font-bold text-red-600 hover:bg-red-50">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5"/></svg>
                            PDF
                        </button>
                        <button onClick={() => download('excel')} className="flex items-center gap-2 rounded-full border border-emerald-200 px-4 py-2 text-[12px] font-bold text-emerald-600 hover:bg-emerald-50">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5h16v14H4Z M4 10h16 M10 5v14"/></svg>
                            Excel
                        </button>
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                {report.columns.map((c, i) => <th key={i} className={`px-4 py-3.5 ${i === 0 ? 'pl-6' : ''} ${c.align === 'right' ? 'text-right' : 'text-left'}`}>{c.label}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((r, ri) => (
                                <tr key={ri} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/40">
                                    {r.map((cell, ci) => (
                                        <td key={ci} className={`px-4 py-3.5 ${ci === 0 ? 'pl-6 font-semibold text-gray-900' : report.columns[ci]?.align === 'right' ? 'text-right text-gray-700' : 'text-left text-gray-700'}`}>{cell}</td>
                                    ))}
                                </tr>
                            ))}
                            {report.rows.length === 0 && <tr><td colSpan={report.columns.length} className="px-5 py-12 text-center text-sm text-gray-400">Belum ada data untuk laporan ini.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

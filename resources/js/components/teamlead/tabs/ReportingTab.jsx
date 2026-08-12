import { useEffect, useMemo, useRef, useState } from 'react';
import SelectMenu from '../../SelectMenu';
import { apiFetch } from '../../../lib/api';
import { downloadFile } from '../../../lib/download';
import { t as trans } from '../../../lib/i18n';

// Harus sama dengan TeamLeadController::ALL_UNITS. Nilainya dikirim ke endpoint
// laporan, jadi harus token tetap — label terjemahan akan terbaca sebagai nama
// unit sungguhan.
const ALL_UNITS = '__all';

// Jeda sebelum pratinjau diminta ulang. Tanpa ini, mengetik tanggal lewat
// keyboard menembakkan satu permintaan per digit.
const PREVIEW_DEBOUNCE_MS = 350;

const EMPTY_REPORT = { title: '', columns: [], rows: [] };

const TYPE_ICON = {
    sla_compliance: 'M12 2.5l2.3 1.7 2.8-.3 1 2.7 2.5 1.4-.8 2.8.8 2.8-2.5 1.4-1 2.7-2.8-.3L12 21.5l-2.3-1.7-2.8.3-1-2.7-2.5-1.4.8-2.8-.8-2.8 2.5-1.4 1-2.7 2.8.3Z M9 12l2 2 4-4',
    sla_breach: 'M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3Z M10 10l4 4 M14 10l-4 4',
    support_perf: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M3 20c0-3 2.7-5 6-5s6 2 6 5 M16.5 11a3 3 0 1 0-1.5-5.6 M21 20c0-2.6-1.6-4.4-4-4.9',
    ticket_summary: 'M8 3h6l4 4v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M14 3v4h4 M10 12h6 M10 16h6',
    top_incident: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 8v5 M12 16h.01',
};

const today = () => new Date().toISOString().slice(0, 10);

export default function ReportingTab({
    reportTypes = {},
    reportUnits = [],
    reportDefaults = {},
    reportPreviewUrl = '',
    reportExportUrl = '',
}) {
    const keys = Object.keys(reportTypes);
    const [type, setType] = useState(keys[0] ?? 'sla_compliance');
    const [unit, setUnit] = useState(ALL_UNITS);
    const [from, setFrom] = useState(reportDefaults.from ?? today());
    const [to, setTo] = useState(reportDefaults.to ?? today());

    const [report, setReport] = useState(EMPTY_REPORT);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [downloading, setDownloading] = useState(null);

    // Rentang terbalik ditolak server dengan 422. Menahannya di sini juga
    // menjaga layar tetap menjelaskan sebabnya, bukan menampilkan tabel kosong.
    const invalidRange = Boolean(from && to && from > to);
    const params = useMemo(() => new URLSearchParams({ type, from, to, unit }).toString(), [type, from, to, unit]);

    const previewRequest = useRef(0);

    useEffect(() => {
        if (invalidRange) {
            setLoading(false);
            setError(trans('teamlead.reporting.invalid_range'));
            setReport(EMPTY_REPORT);

            return undefined;
        }

        // Balasan yang datang terlambat dari penyaring lama tidak boleh
        // menimpa hasil penyaring yang sedang aktif.
        const request = ++previewRequest.current;
        const timer = setTimeout(async () => {
            setLoading(true);
            try {
                const fresh = await apiFetch(`${reportPreviewUrl}?${params}`);
                if (request === previewRequest.current) {
                    setReport(fresh);
                    setError(null);
                }
            } catch (e) {
                if (request === previewRequest.current) {
                    setError(e.message || trans('teamlead.reporting.preview_failed'));
                    setReport(EMPTY_REPORT);
                }
            } finally {
                if (request === previewRequest.current) {
                    setLoading(false);
                }
            }
        }, PREVIEW_DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [params, invalidRange, reportPreviewUrl]);

    async function download(format) {
        if (invalidRange || downloading) return;

        setDownloading(format);
        setError(null);
        try {
            await downloadFile(`${reportExportUrl}?${params}&format=${format}`);
        } catch (e) {
            setError(e.message || trans('teamlead.reporting.download_failed'));
        } finally {
            setDownloading(null);
        }
    }

    const busy = downloading !== null;
    const unitLabel = unit === ALL_UNITS ? trans('teamlead.reporting.all_unit') : unit;

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-[320px_1fr]">
            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
                <h2 className="mb-4 text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.reporting.generator')}</h2>

                <p className="mb-2 text-[12px] font-bold text-amber-700 dark:text-warn-text">{trans('teamlead.reporting.report_type')}</p>
                <div className="mb-4 flex flex-col gap-1.5">
                    {keys.map((k) => (
                        <button
                            key={k}
                            onClick={() => setType(k)}
                            className={`flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-left text-[13px] font-semibold transition ${type === k ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-1 ring-blue-200' : 'bg-gray-50 dark:bg-panel-3 text-gray-700 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover'}`}
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={TYPE_ICON[k]} /></svg>
                            {reportTypes[k]}
                        </button>
                    ))}
                </div>

                <p className="mb-2 text-[12px] font-bold text-amber-700 dark:text-warn-text">{trans('teamlead.reporting.period')}</p>
                <div className="mb-4 flex gap-2">
                    <input type="date" value={from} max={to || undefined} onChange={(e) => setFrom(e.target.value)} className="w-full rounded-xl border border-gray-200 dark:border-edge-strong px-3 py-2.5 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400" />
                    <input type="date" value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} className="w-full rounded-xl border border-gray-200 dark:border-edge-strong px-3 py-2.5 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400" />
                </div>

                <p className="mb-2 text-[12px] font-bold text-amber-700 dark:text-warn-text">{trans('teamlead.reporting.unit')}</p>
                <div className="mb-5">
                    <SelectMenu
                        value={unit}
                        onChange={setUnit}
                        options={[{ value: ALL_UNITS, label: trans('teamlead.reporting.all_unit') }, ...reportUnits.map((u) => ({ value: u, label: u }))]}
                    />
                </div>

                <button
                    onClick={() => download('pdf')}
                    disabled={busy || invalidRange}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 dark:bg-blue-500 py-3 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5 M9 13h6 M9 17h4"/></svg>
                    {downloading === 'pdf' ? trans('teamlead.reporting.downloading') : trans('teamlead.reporting.generate_pdf')}
                </button>

                {error && <p className="mt-3 rounded-xl bg-red-50 dark:bg-bad-soft px-3 py-2 text-[12px] font-semibold text-red-600 dark:text-bad-text">{error}</p>}
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-edge p-5">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{report.title || reportTypes[type]}</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">
                            {trans('teamlead.reporting.preview', { from, to, unit: unitLabel })}
                            {' · '}
                            {loading ? trans('teamlead.reporting.loading') : trans('teamlead.reporting.row_count', { count: report.rows.length })}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => download('pdf')}
                            disabled={busy || invalidRange}
                            className="flex items-center gap-2 rounded-full border border-red-200 px-4 py-2 text-[12px] font-bold text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5"/></svg>
                            PDF
                        </button>
                        <button
                            onClick={() => download('excel')}
                            disabled={busy || invalidRange}
                            className="flex items-center gap-2 rounded-full border border-emerald-200 px-4 py-2 text-[12px] font-bold text-emerald-600 dark:text-ok-text hover:bg-emerald-50 dark:hover:bg-ok-soft disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5h16v14H4Z M4 10h16 M10 5v14"/></svg>
                            Excel
                        </button>
                    </div>
                </div>
                <div className={`overflow-x-auto transition-opacity ${loading ? 'opacity-50' : 'opacity-100'}`}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                {report.columns.map((c, i) => <th key={i} className={`px-4 py-3.5 ${i === 0 ? 'pl-6' : ''} ${c.align === 'right' ? 'text-right' : 'text-left'}`}>{c.label}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((r, ri) => (
                                <tr key={ri} className="border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-gray-50/40 dark:hover:bg-panel-hover">
                                    {r.map((cell, ci) => (
                                        <td key={ci} className={`px-4 py-3.5 ${ci === 0 ? 'pl-6 font-semibold text-gray-900 dark:text-ink-1' : report.columns[ci]?.align === 'right' ? 'text-right text-gray-700 dark:text-ink-2' : 'text-left text-gray-700 dark:text-ink-2'}`}>{cell}</td>
                                    ))}
                                </tr>
                            ))}
                            {report.rows.length === 0 && (
                                <tr>
                                    <td colSpan={Math.max(report.columns.length, 1)} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">
                                        {loading ? trans('teamlead.reporting.loading') : error ?? trans('teamlead.reporting.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

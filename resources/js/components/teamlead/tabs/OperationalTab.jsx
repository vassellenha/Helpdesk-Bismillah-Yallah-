import { useEffect, useMemo, useRef, useState } from 'react';
import { Area, AreaChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { apiFetch } from '../../../lib/api';
import useLockBodyScroll from '../../../lib/useLockBodyScroll';
import { t as trans } from '../../../lib/i18n';

const PRIORITY_PILL = {
    Critical: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text',
    High: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text',
    Medium: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text',
    Low: 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2',
};

function StatCard({ s, onClick }) {
    const Tag = onClick ? 'button' : 'div';
    return (
        <Tag
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={`flex flex-col gap-2.5 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 text-left shadow-sm ${onClick ? 'transition-shadow hover:shadow-md hover:border-blue-200 dark:hover:border-accent-text' : ''}`}
        >
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-500 dark:text-ink-2">{s.label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${s.bg} ${s.fg}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={s.icon} /></svg>
                </span>
            </div>
            <div className="text-[28px] font-extrabold leading-none text-gray-900 dark:text-ink-1">{s.value.toLocaleString('id-ID')}</div>
        </Tag>
    );
}

function AppTrend({ appTrend = {} }) {
    const { months = [], series = [], all = [] } = appTrend;
    const [focus, setFocus] = useState(null); // null = "Semua layanan"
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    // Lines drawn: the top-5 series, plus the focused app if it's outside the
    // top 5 (added from the full `all` list so any app can be inspected).
    const lines = useMemo(() => {
        const arr = series.map((s) => ({ ...s }));
        if (focus && !arr.some((s) => s.name === focus)) {
            const extra = all.find((a) => a.name === focus);
            if (extra) arr.push({ name: extra.name, color: '#2359d6', total: extra.total, points: extra.points });
        }
        return arr;
    }, [series, all, focus]);

    const data = useMemo(
        () => months.map((m, i) => {
            const row = { month: m };
            lines.forEach((s) => { row[s.name] = s.points[i]; });
            return row;
        }),
        [months, lines],
    );

    return (
        <div className="flex h-full flex-col gap-4 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.operational.by_app')}</h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{focus ? trans('teamlead.operational.showing_trend', { app: focus }) : trans('teamlead.operational.pick_app')}</p>
                </div>
                <div className="flex items-center gap-2" ref={ref}>
                    <div className="relative">
                        <button
                            onClick={() => setOpen((v) => !v)}
                            className="flex max-w-[190px] items-center gap-2 rounded-full bg-gray-50 dark:bg-panel-3 px-3.5 py-2 text-[12px] font-bold text-gray-800 dark:text-ink-1 hover:bg-blue-50 dark:hover:bg-panel-hover"
                        >
                            <span className="truncate">{focus ?? trans('teamlead.operational.all_app')}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        {open && (
                            <div className="absolute right-0 top-11 z-30 max-h-[300px] w-[220px] overflow-y-auto rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-xl">
                                {[null, ...all.map((a) => a.name)].map((name) => {
                                    const activeItem = (focus ?? null) === name;
                                    return (
                                        <button
                                            key={name ?? '__all'}
                                            onClick={() => { setFocus(name); setOpen(false); }}
                                            className={`flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-[12.5px] transition ${activeItem ? 'bg-blue-50 dark:bg-accent-soft font-bold text-blue-700 dark:text-accent-text' : 'font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                                        >
                                            {name ?? trans('teamlead.operational.all_app')}
                                            {activeItem && <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12l4 4 10-11"/></svg>}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                    {focus && (
                        <button onClick={() => setFocus(null)} className="rounded-full bg-blue-50 dark:bg-accent-soft px-3 py-2 text-[11px] font-bold text-blue-700 dark:text-accent-text hover:bg-blue-100 dark:hover:bg-panel-hover">{trans('teamlead.common.reset')}</button>
                    )}
                </div>
            </div>

            <div className="h-[180px]">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 4, right: 8, left: -22, bottom: 0 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="var(--chart-grid)" />
                        <XAxis dataKey="month" tick={{ fontSize: 10, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 10, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} allowDecimals={false} width={34} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: 'var(--chart-tooltip-border)', backgroundColor: 'var(--chart-tooltip-bg)', color: 'var(--chart-tooltip-text)', fontSize: 12 }} />
                        {lines.map((s) => {
                            const dim = focus && focus !== s.name;
                            return (
                                <Line
                                    key={s.name}
                                    type="monotone"
                                    dataKey={s.name}
                                    stroke={s.color}
                                    strokeWidth={focus === s.name ? 3 : 2}
                                    strokeOpacity={dim ? 0.12 : 1}
                                    dot={false}
                                    isAnimationActive={false}
                                />
                            );
                        })}
                    </LineChart>
                </ResponsiveContainer>
            </div>

            <div className="flex flex-wrap gap-2">
                {series.map((s) => (
                    <button
                        key={s.name}
                        onClick={() => setFocus((f) => (f === s.name ? null : s.name))}
                        className={`flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-bold transition ${
                            focus === s.name ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-1 ring-blue-200' : focus ? 'bg-gray-50 dark:bg-panel-3 text-gray-400 dark:text-ink-3 opacity-70' : 'bg-gray-50 dark:bg-panel-3 text-gray-700 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover'
                        }`}
                    >
                        <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: s.color }} />
                        {s.name}
                        <span className="font-extrabold text-gray-900 dark:text-ink-1">{s.total}</span>
                    </button>
                ))}
                {series.length === 0 && <p className="text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.common.no_data')}</p>}
            </div>
        </div>
    );
}

function EscalationRecs({ rows = [], escalateUrl, onRaised }) {
    const [done, setDone] = useState({});
    const [busy, setBusy] = useState(null);
    const [toast, setToast] = useState(null);
    // Baris rekomendasi yang sedang menunggu konfirmasi. Tombol "Naikkan" tidak
    // lagi langsung mengirim: tombolnya kecil, berdempetan dengan baris lain,
    // dan sekali tekan bisa mengubah prioritas belasan tiket sekaligus.
    const [confirming, setConfirming] = useState(null);

    async function raise(r) {
        setBusy(r.name);
        try {
            const res = await apiFetch(escalateUrl, {
                method: 'POST',
                body: JSON.stringify({ service: r.service, subcat: r.subcat, to: r.to }),
            });
            setDone((d) => ({ ...d, [r.name]: true }));
            setToast(res?.message ?? trans('teamlead.operational.raised'));
            setTimeout(() => setToast(null), 3200);
            // Refetch the dashboard so the new priority shows in SLA Monitoring
            // and everywhere else — not just when opening the ticket detail.
            onRaised?.();
        } catch (e) {
            setToast(e.message || trans('teamlead.operational.raise_failed'));
            setTimeout(() => setToast(null), 3200);
        } finally {
            setBusy(null);
        }
    }

    return (
        <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-warn-soft text-amber-600 dark:text-warn-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 16V9 M8.5 12.5 12 9l3.5 3.5"/></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.operational.recommendation')}</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.operational.recommendation_hint')}</p>
                    </div>
                </div>
                <span className="shrink-0 rounded-full bg-amber-50 dark:bg-warn-soft px-3 py-1 text-[12px] font-bold text-amber-600 dark:text-warn-text">{trans('teamlead.operational.suggested', { count: rows.filter((r) => !done[r.name]).length })}</span>
            </div>
            <div className="grid grid-cols-[1.6fr_56px_120px_110px] gap-3 border-y border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                <span>{trans('teamlead.operational.category_service')}</span><span className="text-center">{trans('teamlead.columns.ticket')}</span><span>{trans('teamlead.columns.priority')}</span><span className="text-right">{trans('teamlead.common.action')}</span>
            </div>
            {rows.map((r) => (
                <div key={r.name} className="grid grid-cols-[1.6fr_56px_120px_110px] items-center gap-3 border-b border-gray-50 dark:border-transparent dark:even:bg-white/[0.03] px-6 py-4 last:border-0 hover:bg-gray-50/50 dark:hover:bg-panel-hover">
                    <div className="min-w-0 pr-2">
                        <p className="truncate text-[14px] font-bold text-gray-900 dark:text-ink-1">{r.name}</p>
                        <p className="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-gray-400 dark:text-ink-3">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#d97706" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 11v5 M12 8h.01"/></svg>
                            {r.reason}
                        </p>
                    </div>
                    <span className="text-center text-base font-extrabold text-gray-900 dark:text-ink-1">{r.count}</span>
                    <div className="flex flex-col items-start gap-0.5">
                        <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold ${PRIORITY_PILL[r.from]}`}>{r.from}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="ml-1"><path d="M12 5v14 M6 13l6 6 6-6"/></svg>
                        <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-bold ${PRIORITY_PILL[r.to]}`}>{r.to}</span>
                    </div>
                    <div className="flex justify-end">
                        {done[r.name] ? (
                            <span className="flex items-center gap-1.5 text-[12px] font-bold text-emerald-600 dark:text-ok-text">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M8.5 12l2.5 2.5 4.5-5"/></svg>
                                {trans('teamlead.operational.raised_badge')}
                            </span>
                        ) : (
                            <button onClick={() => setConfirming(r)} disabled={busy === r.name} className="flex items-center gap-1.5 rounded-xl bg-blue-600 dark:bg-blue-500 px-3.5 py-2 text-[12px] font-bold text-white shadow-sm hover:bg-blue-700 dark:hover:bg-blue-400 disabled:opacity-60">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5 M6 11l6-6 6 6"/></svg>
                                {busy === r.name ? trans('teamlead.common.processing') : trans('teamlead.operational.raise')}
                            </button>
                        )}
                    </div>
                </div>
            ))}
            {rows.length === 0 && <p className="px-6 py-10 text-center text-sm text-emerald-600 dark:text-ok-text">{trans('teamlead.operational.no_recommendation')}</p>}
            {confirming && (
                <ConfirmRaiseDialog
                    row={confirming}
                    onCancel={() => setConfirming(null)}
                    onConfirm={() => { const r = confirming; setConfirming(null); raise(r); }}
                />
            )}
            {toast && <div className="fixed bottom-6 left-1/2 z-[80] -translate-x-1/2 rounded-xl bg-gray-900 dark:bg-panel-selected px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>}
        </div>
    );
}

/**
 * Konfirmasi sebelum menaikkan prioritas sekelompok tiket.
 *
 * Isinya menyebut angka dan nama kelompoknya, bukan "Anda yakin?" saja —
 * pertanyaan tanpa isi hanya melatih orang menekan "Ya" tanpa membaca, dan
 * penjaga yang selalu dilewati sama saja dengan tidak ada.
 */
function ConfirmRaiseDialog({ row, onCancel, onConfirm }) {
    useLockBodyScroll();

    return (
        <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onMouseDown={onCancel}>
            <div className="liquid-glass-dense w-full max-w-sm overflow-hidden rounded-2xl shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-start gap-3 px-5 pt-5">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 dark:bg-warn-soft text-amber-600 dark:text-warn-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <h2 className="pt-1 text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.operational.confirm_title')}</h2>
                </div>
                <div className="px-5 pt-3">
                    <p className="text-[13px] leading-relaxed text-gray-700 dark:text-ink-2">
                        {trans('teamlead.operational.confirm_body', { count: row.count, name: row.name, from: row.from, to: row.to })}
                    </p>
                    <p className="mt-2 text-[11.5px] leading-relaxed text-gray-400 dark:text-ink-3">{trans('teamlead.operational.confirm_note')}</p>
                </div>
                <div className="mt-5 flex justify-end gap-2 border-t border-gray-100 dark:border-edge px-5 py-4">
                    <button onClick={onCancel} className="rounded-xl px-4 py-2 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">{trans('teamlead.common.cancel')}</button>
                    <button onClick={onConfirm} className="rounded-xl bg-blue-600 dark:bg-blue-500 px-4 py-2 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400">{trans('teamlead.operational.confirm_yes')}</button>
                </div>
            </div>
        </div>
    );
}

function CreatedVsResolved({ ticketTrend = [] }) {
    const created = ticketTrend.reduce((s, r) => s + r.created, 0);
    const resolved = ticketTrend.reduce((s, r) => s + r.resolved, 0);
    const rate = created > 0 ? Math.round((resolved / created) * 100) : 0;

    return (
        <div className="flex flex-col gap-4 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <div className="flex items-start justify-between">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.operational.created_vs_resolved')}</h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.operational.monthly_trend')}</p>
                </div>
                <div className="flex gap-4 text-[12px] font-semibold text-gray-500 dark:text-ink-2">
                    <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-blue-500" />{trans('teamlead.columns.created')}</span>
                    <span className="flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-emerald-500" />{trans('teamlead.columns.resolved')}</span>
                </div>
            </div>
            <div className="flex flex-wrap gap-2.5">
                <div className="min-w-[90px] flex-1 rounded-xl bg-blue-50/60 dark:bg-accent-soft px-3.5 py-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.columns.created')}</p>
                    <p className="text-lg font-extrabold text-blue-700 dark:text-accent-text">{created.toLocaleString('id-ID')}</p>
                </div>
                <div className="min-w-[90px] flex-1 rounded-xl bg-emerald-50/60 dark:bg-ok-soft px-3.5 py-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.columns.resolved')}</p>
                    <p className="text-lg font-extrabold text-emerald-600 dark:text-ok-text">{resolved.toLocaleString('id-ID')}</p>
                </div>
                <div className="min-w-[90px] flex-1 rounded-xl bg-gray-50 dark:bg-panel-3 px-3.5 py-2.5">
                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.operational.resolve_ratio')}</p>
                    <p className="text-lg font-extrabold text-gray-900 dark:text-ink-1">{rate}%</p>
                </div>
            </div>
            <div className="h-[210px]">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={ticketTrend} margin={{ top: 4, right: 8, left: -22, bottom: 0 }}>
                        <defs>
                            <linearGradient id="tlCreatedOp" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="var(--chart-blue)" stopOpacity={0.14} />
                                <stop offset="100%" stopColor="var(--chart-blue)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="var(--chart-grid)" />
                        <XAxis dataKey="month" tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} allowDecimals={false} width={34} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: 'var(--chart-tooltip-border)', backgroundColor: 'var(--chart-tooltip-bg)', color: 'var(--chart-tooltip-text)', fontSize: 12 }} />
                        <Area type="monotone" dataKey="created" name={trans('teamlead.columns.created')} stroke="var(--chart-blue)" strokeWidth={2.5} fill="url(#tlCreatedOp)" />
                        <Line type="monotone" dataKey="resolved" name={trans('teamlead.columns.resolved')} stroke="var(--chart-green)" strokeWidth={2.5} strokeDasharray="6 4" dot={false} />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function CategoryTree({ tree = [] }) {
    return (
        <div className="flex flex-col gap-5 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <div className="flex items-baseline justify-between">
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.operational.by_category')}</h2>
                <span className="text-[11px] text-gray-400 dark:text-ink-3">{trans('teamlead.operational.category_path')}</span>
            </div>
            <div className="flex flex-col gap-5">
                {tree.map((c) => (
                    <div key={c.label} className="flex flex-col gap-2.5">
                        <div className="flex items-baseline justify-between">
                            <span className="flex items-center gap-2.5 text-[14px] font-semibold text-gray-800 dark:text-ink-1">
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: c.color }} />
                                {c.label}
                            </span>
                            <span className="text-[14px] font-extrabold text-gray-900 dark:text-ink-1">{c.total} <span className="font-medium text-gray-400 dark:text-ink-3">· {c.pct}%</span></span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                            <div className="h-full rounded-full" style={{ width: `${c.pct}%`, backgroundColor: c.color }} />
                        </div>
                        <div className="flex flex-col gap-1.5 pl-5">
                            {c.subs.map((s) => (
                                <div key={s.name} className="flex items-center gap-2.5">
                                    <span className="flex-1 truncate text-[12px] text-gray-500 dark:text-ink-2">{s.name}</span>
                                    <div className="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                                        <div className="h-full rounded-full opacity-60" style={{ width: `${s.pctW}%`, backgroundColor: c.color }} />
                                    </div>
                                    <span className="w-9 text-right text-[12px] font-bold text-gray-700 dark:text-ink-2">{s.count}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
                {tree.length === 0 && <p className="text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.common.no_data')}</p>}
            </div>
        </div>
    );
}

export default function OperationalTab({ opStats = [], appTrend = {}, escalationRecs = [], ticketTrend = [], categoryTree = [], escalateUrl, actions = {} }) {
    return (
        <div className="flex flex-col gap-5">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 md:grid-cols-3 lg:grid-cols-5">
                {opStats.map((s) => (
                    <StatCard key={s.label} s={s} onClick={actions.viewMonitoring ? () => actions.viewMonitoring(s.statuses ?? [], s.label) : undefined} />
                ))}
            </div>

            <div className="grid grid-cols-1 gap-3.5 lg:grid-cols-[0.85fr_1.6fr]">
                <AppTrend appTrend={appTrend} />
                <EscalationRecs rows={escalationRecs} escalateUrl={escalateUrl} onRaised={actions.refresh} />
            </div>

            <div className="grid grid-cols-1 gap-3.5 lg:grid-cols-[1.5fr_1fr]">
                <CreatedVsResolved ticketTrend={ticketTrend} />
                <CategoryTree tree={categoryTree} />
            </div>
        </div>
    );
}

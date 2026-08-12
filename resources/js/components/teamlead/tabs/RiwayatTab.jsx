import { useMemo, useState } from 'react';
import { t as trans } from '../../../lib/i18n';

// Setiap aksi yang bisa muncul di feed: tindakan korektif Team Lead sendiri
// (remind, reassign, raise_priority, remind_rating) dan perjalanan tiket yang
// dikerjakan orang lain tapi tetap di bawah pengawasannya (escalate, start,
// resolve, return, keputusan approval). Aksi yang belum terdaftar tetap tampil
// apa adanya, jadi entri baru tidak pernah hilang diam-diam dari jejak audit.
const ACTION_LABELS = {
    remind: { labelKey: 'teamlead.riwayat.remind', style: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' },
    remind_rating: { labelKey: 'teamlead.riwayat.remind_rating', style: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' },
    reassign: { labelKey: 'teamlead.riwayat.reassign', style: 'bg-indigo-50 dark:bg-accent-soft text-indigo-700 dark:text-accent-text' },
    raise_priority: { labelKey: 'teamlead.riwayat.raise', style: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' },
    escalate: { labelKey: 'teamlead.riwayat.escalate', style: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text' },
    resolve: { labelKey: 'teamlead.riwayat.resolve', style: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' },
    start: { labelKey: 'teamlead.riwayat.start', style: 'bg-sky-50 dark:bg-accent-soft text-sky-700 dark:text-accent-text' },
    return: { labelKey: 'teamlead.riwayat.return', style: 'bg-orange-50 dark:bg-warn-soft text-orange-700 dark:text-warn-text' },
    approve: { labelKey: 'teamlead.riwayat.approve', style: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' },
    reject: { labelKey: 'teamlead.riwayat.reject', style: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' },
    request_revision: { labelKey: 'teamlead.riwayat.request_revision', style: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text' },
    update: { labelKey: 'teamlead.riwayat.update', style: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2' },
};

const FILTERS = [
    ['all', 'teamlead.common.all'],
    ['escalate', 'teamlead.riwayat.escalate'],
    ['resolve', 'teamlead.riwayat.resolve'],
    ['remind', 'teamlead.riwayat.remind'],
    ['reassign', 'teamlead.riwayat.reassign'],
    ['raise_priority', 'teamlead.riwayat.raise_short'],
];

export default function RiwayatTab({ auditRows = [] }) {
    const [filter, setFilter] = useState('all');

    const rows = useMemo(
        () => (filter === 'all' ? auditRows : auditRows.filter((a) => a.action === filter)),
        [auditRows, filter],
    );

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                    {FILTERS.map(([k, l]) => (
                        <button
                            key={k}
                            onClick={() => setFilter(k)}
                            className={`rounded-full px-3.5 py-1.5 text-[12px] font-semibold transition ${filter === k ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-1 ring-blue-200' : 'bg-white dark:bg-panel-2 text-gray-600 dark:text-ink-2 ring-1 ring-gray-200 dark:ring-edge-strong hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                        >
                            {trans(l)}
                        </button>
                    ))}
                </div>
                <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-3 py-1.5 text-[12px] font-semibold text-gray-500 dark:text-ink-2">{trans('teamlead.riwayat.recorded', { count: auditRows.length })}</span>
            </div>

            <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="border-b border-gray-100 dark:border-edge p-5">
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.riwayat.audit')}</h2>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.riwayat.audit_hint')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3.5 pl-6 text-left">{trans('teamlead.riwayat.time')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.riwayat.action')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.ticket')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.riwayat.actor')}</th>
                                <th className="px-4 py-3.5 pr-6 text-left">{trans('teamlead.common.detail')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((a) => {
                                const meta = ACTION_LABELS[a.action] ?? { labelKey: null, label: a.action, style: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2' };
                                return (
                                    <tr key={a.id} className="border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-gray-50/60 dark:hover:bg-panel-hover">
                                        <td className="whitespace-nowrap px-4 py-4 pl-6 text-gray-500 dark:text-ink-2">{a.time}</td>
                                        <td className="px-4 py-4"><span className={`whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold ${meta.style}`}>{meta.labelKey ? trans(meta.labelKey) : meta.label}</span></td>
                                        <td className="px-4 py-4 font-bold text-blue-600 dark:text-accent-text">{a.ticket}</td>
                                        <td className="px-4 py-4 text-gray-600 dark:text-ink-2">{a.actor ?? '—'}</td>
                                        <td className="px-4 py-4 pr-6"><p className="max-w-[520px] whitespace-normal break-words leading-relaxed text-gray-600 dark:text-ink-2">{a.detail}</p></td>
                                    </tr>
                                );
                            })}
                            {rows.length === 0 && (
                                <tr><td colSpan={5} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.riwayat.empty')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

import { useMemo, useState } from 'react';
import { t as trans } from '../../../lib/i18n';

const ACTION_LABELS = {
    remind: { labelKey: 'teamlead.riwayat.remind', style: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' },
    reassign: { labelKey: 'teamlead.riwayat.reassign', style: 'bg-indigo-50 dark:bg-accent-soft text-indigo-700 dark:text-accent-text' },
    raise_priority: { labelKey: 'teamlead.riwayat.raise', style: 'bg-red-50 dark:bg-bad-soft text-red-600 dark:text-bad-text' },
};

const FILTERS = [['all', 'teamlead.common.all'], ['remind', 'teamlead.riwayat.remind'], ['reassign', 'teamlead.riwayat.reassign'], ['raise_priority', 'teamlead.riwayat.raise_short']];

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
                    <table className="w-full min-w-[680px] text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3.5 pl-6 text-left">{trans('teamlead.riwayat.time')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.riwayat.action')}</th>
                                <th className="px-4 py-3.5 text-left">{trans('teamlead.columns.ticket')}</th>
                                <th className="px-4 py-3.5 pr-6 text-left">{trans('teamlead.common.detail')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((a) => {
                                const meta = ACTION_LABELS[a.action] ?? { labelKey: null, label: a.action, style: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2' };
                                return (
                                    <tr key={a.id} className="border-b border-gray-50 last:border-0 dark:border-transparent dark:even:bg-white/[0.03] hover:bg-gray-50/60 dark:hover:bg-panel-hover">
                                        <td className="px-4 py-4 pl-6 text-gray-500 dark:text-ink-2">{a.time}</td>
                                        <td className="px-4 py-4"><span className={`rounded-full px-2.5 py-1 text-[11px] font-bold ${meta.style}`}>{meta.labelKey ? trans(meta.labelKey) : meta.label}</span></td>
                                        <td className="px-4 py-4 font-bold text-blue-600 dark:text-accent-text">{a.ticket}</td>
                                        <td className="px-4 py-4 pr-6"><p className="max-w-[520px] whitespace-normal break-words leading-relaxed text-gray-600 dark:text-ink-2">{a.detail}</p></td>
                                    </tr>
                                );
                            })}
                            {rows.length === 0 && (
                                <tr><td colSpan={4} className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">{trans('teamlead.riwayat.empty')}</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

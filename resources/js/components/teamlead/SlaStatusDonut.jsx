import DonutChart from '../charts/DonutChart';
import { t as trans } from '../../lib/i18n';

// 3-segment "SLA Status Split" donut for the Team Lead SLA tab: On Track /
// Warning / Breach. Kept separate from the shared 2-segment SlaComplianceDonut
// (used by the Support dashboard) so each stays accurate to its own meaning.
const SEGMENTS = [
    { key: 'onTrack', labelKey: 'teamlead.sla.on_track', color: '#10b981' },
    { key: 'warning', labelKey: 'teamlead.sla.warning', color: '#d97706' },
    { key: 'breach', labelKey: 'teamlead.sla.breach_label', color: '#dc2626' },
];

export default function SlaStatusDonut({ donut = { total: 0, onTrack: 0, warning: 0, breach: 0, pctWithinSla: 0 } }) {
    const data = SEGMENTS.map((s) => ({ ...s, label: trans(s.labelKey), value: donut[s.key] ?? 0 }));

    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex flex-1 items-center gap-6">
                <DonutChart
                    data={data}
                    size={152}
                    thickness={20}
                    emptyLabel={trans('teamlead.sla.donut_empty')}
                    center={
                        <>
                            <span className="text-2xl font-extrabold leading-none text-emerald-600 dark:text-ok-text">{donut.pctWithinSla}%</span>
                            <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.sla.donut_compliance')}</span>
                        </>
                    }
                />
                <div className="flex flex-1 flex-col gap-3.5">
                    {SEGMENTS.map((s) => (
                        <div key={s.key} className="flex items-center gap-2.5">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} />
                            <span className="flex-1 text-[12.5px] text-gray-700 dark:text-ink-2">{trans(s.labelKey)}</span>
                            <span className="text-[14px] font-bold text-gray-900 dark:text-ink-1">{donut[s.key] ?? 0}</span>
                        </div>
                    ))}
                    <p className="border-t border-gray-100 dark:border-edge pt-3 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                        {trans('teamlead.sla.donut_footnote', { pct: donut.pctWithinSla, total: donut.total })}
                    </p>
                </div>
            </div>
        </div>
    );
}

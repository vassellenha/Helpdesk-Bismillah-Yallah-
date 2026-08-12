import DonutChart from '../charts/DonutChart';
import Tip from '../Tip';
import { t as trans } from '../../lib/i18n';

/*
 | Penjelasan tiap status mengikuti Ticket::getSlaKindAttribute() persis:
 | breach = batas penyelesaian sudah lewat; warning = sudah melewati warning_at
 | tapi batasnya belum lewat; ontrack = belum keduanya. Ambang peringatannya
 | (warning_threshold_percent) diatur Admin per prioritas, jadi teksnya sengaja
 | tidak menyebut angka tetap yang bisa jadi salah.
 */
const SEGMENTS = [
    { key: 'onTrack', labelKey: 'requester.charts.sla_on_track', color: 'var(--chart-green)', helpKey: 'requester.sla_help.ontrack' },
    { key: 'warning', labelKey: 'requester.charts.sla_warning', color: 'var(--chart-amber)', helpKey: 'requester.sla_help.warning' },
    { key: 'breach', labelKey: 'requester.charts.sla_breach', color: 'var(--chart-red)', helpKey: 'requester.sla_help.breach' },
];

export default function SlaDistributionDonut({ donut = { total: 0, onTrack: 0, warning: 0, breach: 0, pctWithinSla: 0 } }) {
    const data = SEGMENTS.map((s) => ({ ...s, label: trans(s.labelKey), value: donut[s.key] ?? 0 }));

    return (
        <div className="flex h-full flex-col gap-4">
            <div>
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.charts.sla_distribution')}</h2>
                <p className="text-xs text-gray-400 dark:text-ink-3">{trans('requester.charts.sla_subtitle')}</p>
            </div>
            <div className="flex flex-1 items-center gap-5">
                <DonutChart
                    data={data}
                    size={136}
                    emptyLabel={trans('requester.charts.sla_empty')}
                    center={
                        <>
                            <span className="text-2xl font-extrabold leading-none text-gray-900 dark:text-ink-1">{donut.total}</span>
                            <span className="text-[10px] text-gray-400 dark:text-ink-3">{trans('requester.charts.sla_active')}</span>
                        </>
                    }
                />
                <div className="flex flex-1 flex-col gap-3">
                    {SEGMENTS.map((s) => (
                        <div key={s.key} tabIndex={0} className="group relative flex items-center gap-2 outline-none">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} />
                            {/* Garis putus-putus: penanda lazim bahwa ada penjelasan
                                kalau kursor mampir. Tanpa itu tooltipnya ada tapi
                                tidak ada yang tahu. */}
                            <span className="flex-1 text-xs text-gray-700 underline decoration-dotted decoration-gray-300 underline-offset-4 dark:text-ink-2">
                                {trans(s.labelKey)}
                            </span>
                            <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{donut[s.key] ?? 0}</span>
                            <Tip align="right">{trans(s.helpKey)}</Tip>
                        </div>
                    ))}
                    <div className="border-t border-gray-100 dark:border-edge pt-2.5 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                        {trans('requester.charts.sla_within_target', { pct: donut.pctWithinSla })}
                    </div>
                </div>
            </div>
        </div>
    );
}

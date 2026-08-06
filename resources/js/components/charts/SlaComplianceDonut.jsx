import DonutChart from './DonutChart';

const SEGMENTS = [
    { key: 'withinSla', label: 'Dalam SLA', color: 'var(--chart-green)' },
    { key: 'breach', label: 'SLA Breach', color: 'var(--chart-red)' },
];

export default function SlaComplianceDonut({ donut = { total: 0, withinSla: 0, breach: 0, pctWithinSla: 0 } }) {
    const data = SEGMENTS.map((s) => ({ ...s, value: donut[s.key] ?? 0 }));

    return (
        <div className="flex h-full flex-col gap-4">
            <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">Kepatuhan SLA</h2>
            <div className="flex flex-1 items-center gap-5">
                <DonutChart
                    data={data}
                    size={136}
                    emptyLabel="Belum ada tiket"
                    formatValue={(slice, pct) => `${slice.value} tiket · ${pct}%`}
                    center={
                        <>
                            <span className="text-xl font-extrabold leading-none text-gray-900 dark:text-ink-1">{donut.pctWithinSla}%</span>
                            <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Tercapai</span>
                        </>
                    }
                />
                <div className="flex flex-1 flex-col gap-3">
                    {SEGMENTS.map((s) => (
                        <div key={s.key} className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} />
                            <span className="flex-1 text-xs text-gray-700 dark:text-ink-2">{s.label}</span>
                            <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{donut[s.key] ?? 0}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

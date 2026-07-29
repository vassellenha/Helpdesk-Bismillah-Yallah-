import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';

const SEGMENTS = [
    { key: 'onTrack', label: 'On Track', color: 'var(--chart-green)' },
    { key: 'warning', label: 'SLA Warning', color: 'var(--chart-amber)' },
    { key: 'breach', label: 'SLA Breach', color: 'var(--chart-red)' },
];

export default function SlaDistributionDonut({ donut = { total: 0, onTrack: 0, warning: 0, breach: 0, pctWithinSla: 0 } }) {
    const data = SEGMENTS.map((s) => ({ ...s, value: donut[s.key] ?? 0 })).filter((d) => d.value > 0);
    const chartData = data.length > 0 ? data : [{ key: 'empty', label: 'No active tickets', color: 'var(--chart-empty)', value: 1 }];

    return (
        <div className="flex h-full flex-col gap-4">
            <div>
                <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">SLA Distribution</h2>
                <p className="text-xs text-gray-400 dark:text-ink-3">Tickets with an SLA clock running (excludes those awaiting approval)</p>
            </div>
            <div className="flex flex-1 items-center gap-5">
                <div className="relative h-32 w-32 shrink-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie data={chartData} dataKey="value" nameKey="label" innerRadius={44} outerRadius={62} paddingAngle={data.length > 1 ? 2 : 0} strokeWidth={0}>
                                {chartData.map((d) => (
                                    <Cell key={d.key} fill={d.color} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-2xl font-extrabold leading-none text-gray-900 dark:text-ink-1">{donut.total}</span>
                        <span className="text-[10px] text-gray-400 dark:text-ink-3">SLA active</span>
                    </div>
                </div>
                <div className="flex flex-1 flex-col gap-3">
                    {SEGMENTS.map((s) => (
                        <div key={s.key} className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} />
                            <span className="flex-1 text-xs text-gray-700 dark:text-ink-2">{s.label}</span>
                            <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">{donut[s.key] ?? 0}</span>
                        </div>
                    ))}
                    <div className="border-t border-gray-100 dark:border-edge pt-2.5 text-[11px] leading-relaxed text-gray-400 dark:text-ink-3">
                        {donut.pctWithinSla}% of your active tickets are still within SLA targets.
                    </div>
                </div>
            </div>
        </div>
    );
}

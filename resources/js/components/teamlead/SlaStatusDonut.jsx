import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';

// 3-segment "SLA Status Split" donut for the Team Lead SLA tab: On Track /
// Warning / Breach. Kept separate from the shared 2-segment SlaComplianceDonut
// (used by the Support dashboard) so each stays accurate to its own meaning.
const SEGMENTS = [
    { key: 'onTrack', label: 'On Track', color: '#10b981' },
    { key: 'warning', label: 'Warning', color: '#d97706' },
    { key: 'breach', label: 'Breach', color: '#dc2626' },
];

export default function SlaStatusDonut({ donut = { total: 0, onTrack: 0, warning: 0, breach: 0, pctWithinSla: 0 } }) {
    const data = SEGMENTS.map((s) => ({ ...s, value: donut[s.key] ?? 0 })).filter((d) => d.value > 0);
    const chartData = data.length > 0 ? data : [{ key: 'empty', label: 'Belum ada tiket', color: '#e5e7eb', value: 1 }];

    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex flex-1 items-center gap-6">
                <div className="relative h-36 w-36 shrink-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie data={chartData} dataKey="value" nameKey="label" innerRadius={48} outerRadius={68} paddingAngle={data.length > 1 ? 2 : 0} strokeWidth={0}>
                                {chartData.map((d) => <Cell key={d.key} fill={d.color} />)}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-2xl font-extrabold leading-none text-emerald-600">{donut.pctWithinSla}%</span>
                        <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Compliance</span>
                    </div>
                </div>
                <div className="flex flex-1 flex-col gap-3.5">
                    {SEGMENTS.map((s) => (
                        <div key={s.key} className="flex items-center gap-2.5">
                            <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} />
                            <span className="flex-1 text-[12.5px] text-gray-700">{s.label}</span>
                            <span className="text-[14px] font-bold text-gray-900">{donut[s.key] ?? 0}</span>
                        </div>
                    ))}
                    <p className="border-t border-gray-100 pt-3 text-[11px] leading-relaxed text-gray-400">
                        {donut.pctWithinSla}% dari {donut.total} tiket ber-SLA aktif memenuhi target.
                    </p>
                </div>
            </div>
        </div>
    );
}

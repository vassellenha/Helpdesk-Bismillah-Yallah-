import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const SERIES = [
    { key: 'Incident', color: 'var(--chart-blue)' },
    { key: 'Service Request', color: 'var(--chart-amber)' },
    { key: 'Access Request', color: 'var(--chart-green)' },
];

export default function TicketTrendChart({ data = [] }) {
    return (
        <div>
            <div className="mb-3 flex flex-wrap gap-4">
                {SERIES.map((s) => (
                    <span key={s.key} className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-ink-2">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: s.color }} />
                        {s.key}
                    </span>
                ))}
            </div>
            <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 5, right: 12, left: -12, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--chart-grid)" />
                        <XAxis dataKey="month" tick={{ fontSize: 12, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 12, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: 'var(--chart-tooltip-border)', backgroundColor: 'var(--chart-tooltip-bg)', color: 'var(--chart-tooltip-text)', fontSize: 13 }} />
                        {SERIES.map((s) => (
                            <Line key={s.key} type="monotone" dataKey={s.key} stroke={s.color} strokeWidth={2.5} dot={{ r: 3 }} />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const SERIES = [
    { key: 'Incident', color: '#2563eb' },
    { key: 'Service Request', color: '#f59e0b' },
    { key: 'Access Request', color: '#10b981' },
];

export default function TicketTrendChart({ data = [] }) {
    return (
        <div>
            <div className="mb-3 flex flex-wrap gap-4">
                {SERIES.map((s) => (
                    <span key={s.key} className="flex items-center gap-1.5 text-xs text-gray-600">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: s.color }} />
                        {s.key}
                    </span>
                ))}
            </div>
            <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 5, right: 12, left: -12, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#eef0f3" />
                        <XAxis dataKey="month" tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: '#e5e7eb', fontSize: 13 }} />
                        {SERIES.map((s) => (
                            <Line key={s.key} type="monotone" dataKey={s.key} stroke={s.color} strokeWidth={2.5} dot={{ r: 3 }} />
                        ))}
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function SlaChart({ data = [] }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-gray-900">Performa SLA Mingguan</h2>
            <div className="mt-4 h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 5, right: 12, left: -12, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#eef0f3" />
                        <XAxis dataKey="week" tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: '#e5e7eb', fontSize: 13 }} />
                        <Line type="monotone" dataKey="on_time" name="Tepat Waktu (%)" stroke="#1d4ed8" strokeWidth={2.5} dot={{ r: 3 }} />
                        <Line type="monotone" dataKey="breach" name="Breach (%)" stroke="#dc2626" strokeWidth={2.5} dot={{ r: 3 }} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

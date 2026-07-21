import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, XAxis, YAxis } from 'recharts';

export default function TopServiceBarChart({ data = [] }) {
    return (
        <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 20, right: 12, left: -12, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#eef0f3" />
                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fontSize: 12, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                    <Bar dataKey="total" fill="#2563eb" radius={[6, 6, 0, 0]}>
                        <LabelList dataKey="total" position="top" fontSize={12} fill="#374151" />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

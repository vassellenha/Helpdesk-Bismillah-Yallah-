import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, XAxis, YAxis } from 'recharts';

export default function TopServiceBarChart({ data = [] }) {
    return (
        <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 20, right: 12, left: -12, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--chart-grid)" />
                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                    <YAxis tick={{ fontSize: 12, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                    <Bar dataKey="total" fill="var(--chart-blue)" radius={[6, 6, 0, 0]}>
                        <LabelList dataKey="total" position="top" fontSize={12} fill="var(--chart-label)" />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

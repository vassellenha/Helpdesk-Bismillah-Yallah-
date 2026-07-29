import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';

export default function TicketCategoryDonut({ data = [], total = 0 }) {
    return (
        <div className="flex flex-col items-center gap-6 sm:flex-row">
            <div className="relative h-44 w-44 shrink-0">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie data={data} dataKey="value" nameKey="label" innerRadius={55} outerRadius={80} paddingAngle={2}>
                            {data.map((d) => (
                                <Cell key={d.label} fill={d.color} stroke="none" />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-2xl font-bold text-gray-900 dark:text-ink-1">{total.toLocaleString('id-ID')}</span>
                    <span className="text-xs text-gray-400 dark:text-ink-3">TOTAL TIKET</span>
                </div>
            </div>
            <ul className="w-full space-y-2">
                {data.map((d) => (
                    <li key={d.label} className="flex items-center justify-between text-sm">
                        <span className="flex items-center gap-2 text-gray-700 dark:text-ink-2">
                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: d.color }} />
                            {d.label}
                        </span>
                        <span className="font-semibold text-gray-900 dark:text-ink-1">{d.value}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

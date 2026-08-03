import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { t as trans } from '../../lib/i18n';

export default function CreatedVsResolvedChart({ data = [] }) {
    return (
        <div className="flex h-full flex-col gap-4">
            <div className="flex items-start justify-between">
                <div>
                    <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('requester.charts.created_vs_resolved')}</h2>
                    <p className="text-xs text-gray-400 dark:text-ink-3">Monthly · {data[0]?.month} – {data[data.length - 1]?.month} 2026</p>
                </div>
                <div className="flex gap-3.5 text-[11px] font-medium text-gray-400 dark:text-ink-3">
                    <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-[3px] bg-blue-600 dark:bg-blue-500" />{trans('requester.charts.created')}</span>
                    <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-[3px] bg-emerald-500" />{trans('requester.charts.resolved')}</span>
                </div>
            </div>
            <div className="h-[190px]">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 4, right: 4, left: -20, bottom: 0 }} barGap={4}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="var(--chart-grid)" />
                        <XAxis dataKey="month" tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fontSize: 11, fill: 'var(--chart-axis)' }} axisLine={false} tickLine={false} allowDecimals={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, borderColor: 'var(--chart-tooltip-border)', backgroundColor: 'var(--chart-tooltip-bg)', color: 'var(--chart-tooltip-text)', fontSize: 12 }} cursor={{ fill: 'rgba(37,99,235,0.05)' }} />
                        <Bar dataKey="created" name="Created" fill="var(--chart-blue)" radius={[4, 4, 0, 0]} maxBarSize={18} />
                        <Bar dataKey="resolved" name="Resolved" fill="var(--chart-green)" radius={[4, 4, 0, 0]} maxBarSize={18} fillOpacity={0.85} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

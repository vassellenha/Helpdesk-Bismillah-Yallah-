import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, BarRow } from '../ui';

export default function ManagementTab({ ticketTrend = [], topIssues = [], topApps = [], servicePerformance = [] }) {
    const maxApp = Math.max(1, ...topApps.map((a) => a.count));

    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card title="Tren Tiket" subtitle="Total tiket dibuat per bulan">
                    <div className="h-[230px]">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={ticketTrend} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                                <CartesianGrid vertical={false} strokeDasharray="3 3" stroke="#eef0f3" />
                                <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} allowDecimals={false} />
                                <Tooltip contentStyle={{ borderRadius: 8, borderColor: '#e5e7eb', fontSize: 12 }} />
                                <Line type="monotone" dataKey="created" name="Dibuat" stroke="#2563eb" strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <Card title="Isu Teratas" subtitle="Subjek tiket dengan volume tertinggi">
                    <div className="flex flex-col">
                        {topIssues.map((t, i) => (
                            <div key={t.name} className="flex items-center gap-3.5 border-b border-gray-50 py-3 last:border-0">
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-600">{i + 1}</span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[13.5px] font-bold text-gray-900">{t.name}</p>
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{t.apps}</p>
                                </div>
                                <span className="text-base font-extrabold text-gray-900">{t.count}</span>
                            </div>
                        ))}
                        {topIssues.length === 0 && <p className="text-sm text-gray-400">Belum ada data.</p>}
                    </div>
                </Card>
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card title="Aplikasi Teratas" subtitle="Volume tiket per aplikasi">
                    <div className="flex flex-col gap-3.5">
                        {topApps.map((a) => (
                            <BarRow key={a.name} label={a.name} value={a.count} pct={Math.round((a.count / maxApp) * 100)} />
                        ))}
                        {topApps.length === 0 && <p className="text-sm text-gray-400">Belum ada data.</p>}
                    </div>
                </Card>

                <Card title="Performa Layanan" subtitle="Avg resolusi, volume, & kepatuhan SLA per kategori">
                    <div className="flex flex-col">
                        {servicePerformance.map((s) => (
                            <div key={s.name} className="flex items-center justify-between border-b border-gray-50 py-3 last:border-0">
                                <div>
                                    <p className="text-[13.5px] font-bold text-gray-900">{s.name}</p>
                                    <p className="text-[11.5px] text-gray-400">{s.meta}</p>
                                </div>
                                <span className={`rounded-full px-3 py-1 text-[11px] font-bold ${s.compliance >= 90 ? 'bg-emerald-50 text-emerald-600' : s.compliance >= 60 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'}`}>
                                    {s.compliance}% SLA
                                </span>
                            </div>
                        ))}
                        {servicePerformance.length === 0 && <p className="text-sm text-gray-400">Belum ada data.</p>}
                    </div>
                </Card>
            </div>
        </div>
    );
}

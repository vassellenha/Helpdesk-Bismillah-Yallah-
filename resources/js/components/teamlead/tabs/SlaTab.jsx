import SlaStatusDonut from '../SlaStatusDonut';
import SlaTopSubjects from './SlaTopSubjects';
import { MetricCard, Card, ICON } from '../ui';

const PRIORITY_COLOR = { Critical: '#dc2626', High: '#d97706', Medium: '#2563eb', Low: '#9ca3af' };

export default function SlaTab({ slaDonut = {}, slaByPriority = [], metrics = {}, supportStats = {}, slaTopSubjects = [] }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Kepatuhan SLA" value={`${slaDonut.pctWithinSla ?? 0}%`} icon={ICON.verified} iconBg="bg-emerald-50 dark:bg-ok-soft" iconColor="text-emerald-600 dark:text-ok-text" sub="Target ≥ 95%" />
                <MetricCard label="SLA Breach" value={metrics.slaBreach ?? 0} icon={ICON.warning} iconBg="bg-red-50 dark:bg-bad-soft" iconColor="text-red-600 dark:text-bad-text" sub="Sudah lewat batas" />
                <MetricCard label="Mendekati SLA" value={metrics.slaWarning ?? 0} icon={ICON.clock} iconBg="bg-amber-50 dark:bg-warn-soft" iconColor="text-amber-600 dark:text-warn-text" sub="< ambang warning" />
                <MetricCard label="Produktivitas SLA" value={`${supportStats.productivity ?? 0}%`} icon={ICON.trending} iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text" sub="Tiket selesai tepat waktu" />
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card title="SLA Status Split">
                    <SlaStatusDonut donut={slaDonut} />
                </Card>

                <Card title="SLA Compliance by Priority" subtitle="Persentase tiket dalam SLA per prioritas">
                    <div className="flex flex-col gap-5">
                        {slaByPriority.map((p) => (
                            <div key={p.priority}>
                                <div className="mb-1.5 flex items-center justify-between text-[13px]">
                                    <span className="font-semibold text-gray-700 dark:text-ink-2">{p.priority} <span className="text-gray-400 dark:text-ink-3">· {p.total} tiket</span></span>
                                    <span className="font-bold" style={{ color: PRIORITY_COLOR[p.priority] }}>{p.pct}%</span>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                                    <div className="h-full rounded-full" style={{ width: `${p.pct}%`, backgroundColor: PRIORITY_COLOR[p.priority] }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            <SlaTopSubjects rows={slaTopSubjects} />
        </div>
    );
}

import { MetricCard, ICON } from '../ui';
import PicTable from './support/PicTable';
import WorkloadTable from './support/WorkloadTable';
import TeguranTable from './support/TeguranTable';

export default function SupportTab({
    supportStats = {},
    workload = [],
    picRows = [],
    teguranTickets = [],
    remindUrlBase,
    remindRatingUrlBase,
    ratingTeguranThreshold = 4,
    actions = {},
}) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard label="Agent Support" value={supportStats.agents ?? 0} icon={ICON.users} iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text" sub="Aktif" />
                <MetricCard label="Teguran Hari Ini" value={supportStats.teguranToday ?? 0} icon={ICON.bell} iconBg="bg-amber-50 dark:bg-warn-soft" iconColor="text-amber-600 dark:text-warn-text" sub="Email & WhatsApp" />
                <MetricCard label="Diselesaikan Hari Ini" value={supportStats.resolvedToday ?? 0} icon={ICON.check} iconBg="bg-emerald-50 dark:bg-ok-soft" iconColor="text-emerald-600 dark:text-ok-text" />
                <MetricCard label="Produktivitas" value={`${supportStats.productivity ?? 0}%`} icon={ICON.trending} iconBg="bg-blue-50 dark:bg-accent-soft" iconColor="text-blue-600 dark:text-accent-text" sub="Tepat waktu SLA" />
            </div>

            <PicTable rows={picRows} />
            <WorkloadTable
                rows={workload}
                onOpenTicket={actions.openTicket}
                remindRatingUrlBase={remindRatingUrlBase}
                ratingTeguranThreshold={ratingTeguranThreshold}
                onRatingReminded={actions.refresh}
            />
            <TeguranTable rows={teguranTickets} remindUrlBase={remindUrlBase} onOpenTicket={actions.openTicket} onSent={actions.refresh} />
        </div>
    );
}

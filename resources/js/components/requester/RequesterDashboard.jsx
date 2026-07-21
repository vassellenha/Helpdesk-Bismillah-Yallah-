import CreatedVsResolvedChart from './CreatedVsResolvedChart';
import SlaDistributionDonut from './SlaDistributionDonut';
import SlaLimitTable from './SlaLimitTable';
import NewTicketModal from '../NewTicketModal';

const CARD_ICONS = {
    active: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 7v5l3 3',
    awaitingApproval: 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9',
    needsResponse: 'M21 11.5a8.5 8.5 0 0 1-8.5 8.5c-1.5 0-3-.4-4.2-1.1L3 20l1.1-5.3A8.5 8.5 0 1 1 21 11.5Z',
    resolved: 'm5 13 4 4L19 7',
    closed: 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z',
};

function StatCard({ label, value, hint, hintClass = 'text-gray-400', iconBg, iconColor, path }) {
    return (
        <div className="flex flex-col gap-2.5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-400">{label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${iconBg} ${iconColor}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={path} /></svg>
                </span>
            </div>
            <div className="text-[32px] font-extrabold leading-none text-gray-900">{value}</div>
            {hint && <div className={`text-[11px] ${hintClass}`}>{hint}</div>}
        </div>
    );
}

export default function RequesterDashboard({ user = {}, stats, chart = [], slaDonut, slaRows = [], catalogUrl, approversUrl, submitUrl, ticketsUrl }) {
    const firstName = (user.name ?? 'there').split(' ')[0];
    const today = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

    return (
        <div className="flex flex-col gap-7">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">Good morning, {firstName} 👋</h1>
                    <p className="mt-1 text-[13px] text-gray-400">Your ticket summary and SLA status as of today, {today}.</p>
                </div>
                <NewTicketModal catalogUrl={catalogUrl} approversUrl={approversUrl} submitUrl={submitUrl} />
            </div>

            <div className="grid grid-cols-2 gap-x-5 gap-y-6 sm:grid-cols-3 lg:grid-cols-5">
                <StatCard
                    label="Active Tickets"
                    value={stats.active.count}
                    hint={stats.active.breakdown}
                    iconBg="bg-blue-50" iconColor="text-blue-600" path={CARD_ICONS.active}
                />
                <StatCard
                    label="Awaiting Approval"
                    value={stats.awaitingApproval.count}
                    hint={stats.awaitingApproval.breakdown}
                    iconBg="bg-gray-100" iconColor="text-gray-600" path={CARD_ICONS.awaitingApproval}
                />
                <StatCard
                    label="Needs My Response"
                    value={stats.needsResponse.count}
                    hint={stats.needsResponse.count > 0 ? 'Waiting for Response' : 'All caught up'}
                    hintClass="font-semibold text-violet-600"
                    iconBg="bg-violet-50" iconColor="text-violet-700" path={CARD_ICONS.needsResponse}
                />
                <StatCard
                    label="Resolved"
                    value={stats.resolved.count}
                    hint="Awaiting your confirmation"
                    iconBg="bg-emerald-50" iconColor="text-emerald-600" path={CARD_ICONS.resolved}
                />
                <StatCard
                    label="Closed"
                    value={stats.closed.count}
                    hint="Last 6 months"
                    iconBg="bg-gray-100" iconColor="text-gray-500" path={CARD_ICONS.closed}
                />
            </div>

            <div className="mt-3 grid grid-cols-1 gap-5 lg:grid-cols-[1.6fr_1fr]">
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <CreatedVsResolvedChart data={chart} />
                </div>
                <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <SlaDistributionDonut donut={slaDonut} />
                </div>
            </div>

            <div className="mt-3">
                <SlaLimitTable rows={slaRows} ticketsUrl={ticketsUrl} />
            </div>
        </div>
    );
}

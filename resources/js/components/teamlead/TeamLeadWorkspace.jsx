import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import TeamLeadTopNav from './TeamLeadTopNav';
import TicketSlideOver from './TicketSlideOver';
import RemindModal from './RemindModal';
import ReassignModal from './ReassignModal';
import RaisePriorityModal from './RaisePriorityModal';
import OperationalTab from './tabs/OperationalTab';
import SlaTab from './tabs/SlaTab';
import SupportTab from './tabs/SupportTab';
import ManagementTab from './tabs/ManagementTab';
import MonitoringTab from './tabs/MonitoringTab';
import EscalationTab from './tabs/EscalationTab';
import ReportingTab from './tabs/ReportingTab';
import RiwayatTab from './tabs/RiwayatTab';

const TABS = [
    { key: 'operational', label: 'Operational', icon: 'M4 4h6v7H4Z M14 4h6v5h-6Z M14 13h6v7h-6Z M4 15h6v5H4Z' },
    { key: 'sla', label: 'SLA', icon: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z' },
    { key: 'support', label: 'Support', icon: 'M4 13v-2a8 8 0 0 1 16 0v2 M4 14a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2Z M20 14a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-4' },
    { key: 'management', label: 'Management', icon: 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z' },
    { key: 'monitoring', label: 'SLA Monitoring', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 2' },
    { key: 'escalation', label: 'Eskalasi', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 8v5 M12 16h.01' },
    { key: 'reporting', label: 'Reporting', icon: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5 M9 13h6 M9 17h4' },
    { key: 'riwayat', label: 'Riwayat', icon: 'M12 3a9 9 0 1 0 9 9 M12 3a9 9 0 0 0-9 9 M4 5v4h4 M12 8v4l3 2' },
];

const TITLES = {
    operational: ['Operational Dashboard', 'Distribusi, tren, dan volume tiket tim Support IT.'],
    sla: ['SLA Dashboard', 'Kepatuhan, breach, dan performa SLA per prioritas.'],
    support: ['Support Dashboard', 'Beban kerja tim serta teguran tiket via Email & WhatsApp.'],
    management: ['Management Dashboard', 'Tren, isu teratas, aplikasi, dan performa layanan.'],
    monitoring: ['SLA Monitoring', 'Pantauan tiket aktif dengan sisa waktu SLA.'],
    escalation: ['Eskalasi', 'Tiket melewati SLA & eskalasi approval (dipantau).'],
    reporting: ['Reporting', 'Generator laporan berkala dengan export.'],
    riwayat: ['Riwayat Tindakan', 'Jejak audit semua tindakan korektif Team Lead.'],
};

const PERIODS = [['today', 'Today'], ['7d', '7 Hari'], ['30d', '30 Hari'], ['quarter', 'Kuartal']];
const PERIOD_TABS = ['operational', 'sla', 'support', 'management'];

export default function TeamLeadWorkspace(props) {
    // Dashboard data lives in state (seeded from the initial page props) so a
    // corrective action can refetch and update every panel in place.
    const [data, setData] = useState(props);
    const initialTab = (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab')) || 'operational';
    const [active, setActiveState] = useState(TABS.some((t) => t.key === initialTab) ? initialTab : 'operational');
    const period = data.period ?? '30d';

    // Keep the active tab in the URL so the period links (which reload the page
    // with a new ?period=) land back on the same tab.
    function setActive(key) {
        setActiveState(key);
        const params = new URLSearchParams(window.location.search);
        params.set('tab', key);
        window.history.replaceState(null, '', `?${params.toString()}`);
    }
    const [modal, setModal] = useState(null); // { type, row, onSuccess }
    const [ticketOpen, setTicketOpen] = useState(null); // ticket id for the slide-over
    const [toast, setToast] = useState(null);

    function flash(message) {
        setToast(message);
        setTimeout(() => setToast(null), 3500);
    }

    const actions = {
        remind: (row, onSuccess) => setModal({ type: 'remind', row, onSuccess }),
        reassign: (row, onSuccess) => setModal({ type: 'reassign', row, onSuccess }),
        raise: (row, onSuccess) => setModal({ type: 'raise', row, onSuccess }),
        openTicket: (id) => setTicketOpen(id),
    };

    // Refetch the whole dashboard payload and swap it into state, so every
    // panel (ticket lists, priorities) and the Riwayat feed update live —
    // no full page reload needed.
    async function refresh() {
        try {
            const sep = data.dashboardDataUrl.includes('?') ? '&' : '?';
            const fresh = await apiFetch(`${data.dashboardDataUrl}${sep}period=${period}`);
            setData((prev) => ({ ...prev, ...fresh }));
        } catch {
            // Keep the last-known data on a failed refresh rather than blanking the view.
        }
    }

    function handleSuccess(res) {
        modal?.onSuccess?.(res);
        flash(res?.message ?? 'Berhasil.');
        setModal(null);
        refresh();
    }

    const [title, subtitle] = TITLES[active];
    const shared = { ...data, actions };

    return (
        <div className="flex min-h-screen flex-col">
            <header className="sticky top-0 z-20 flex h-[62px] items-center gap-4 border-b border-gray-200 bg-white px-7">
                <div className="flex shrink-0 items-center gap-2.5">
                    <span className="flex h-8 w-8 items-center justify-center rounded-[10px] bg-blue-600 text-sm font-extrabold text-white">A</span>
                    <div className="leading-tight">
                        <p className="text-sm font-bold text-gray-900">Adhi Helpdesk</p>
                        <p className="text-[10px] text-gray-400">Enterprise ITSM</p>
                    </div>
                </div>

                <nav className="flex min-w-0 flex-1 items-center gap-0.5 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setActive(t.key)}
                            className={`flex shrink-0 items-center gap-2 rounded-[10px] px-3 py-2 text-[13px] font-semibold transition ${
                                active === t.key ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                            }`}
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={t.icon} /></svg>
                            {t.label}
                        </button>
                    ))}
                </nav>

                <div className="shrink-0">
                    <TeamLeadTopNav
                        notifications={props.notifications ?? []}
                        user={props.user ?? {}}
                        dashboardUrl={props.dashboardUrl ?? '/'}
                        markAllReadUrl={props.markAllReadUrl}
                        onOpenTicket={actions.openTicket}
                    />
                </div>
            </header>

            <main className="mx-auto flex w-full max-w-[1280px] flex-1 flex-col gap-6 px-7 py-7">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">Team Lead Workspace</p>
                        <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">{title}</h1>
                        <p className="mt-1 text-sm text-gray-500">{subtitle}</p>
                    </div>
                    {PERIOD_TABS.includes(active) && (
                        <div className="flex items-center gap-2.5">
                            <span className="text-[11px] font-bold uppercase tracking-wide text-gray-400">Period</span>
                            <div className="flex gap-1 rounded-xl bg-gray-100 p-1">
                                {PERIODS.map(([key, label]) => (
                                    <a
                                        key={key}
                                        href={`?period=${key}&tab=${active}`}
                                        className={`rounded-lg px-3 py-1.5 text-[12.5px] font-bold transition ${period === key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                                    >
                                        {label}
                                    </a>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {active === 'operational' && <OperationalTab {...shared} />}
                {active === 'sla' && <SlaTab {...shared} />}
                {active === 'support' && <SupportTab {...shared} />}
                {active === 'management' && <ManagementTab {...shared} />}
                {active === 'monitoring' && <MonitoringTab {...shared} />}
                {active === 'escalation' && <EscalationTab {...shared} />}
                {active === 'reporting' && <ReportingTab {...shared} />}
                {active === 'riwayat' && <RiwayatTab {...shared} />}
            </main>

            {modal?.type === 'remind' && (
                <RemindModal ticket={modal.row} remindUrlBase={props.remindUrlBase} onClose={() => setModal(null)} onSent={handleSuccess} />
            )}
            {modal?.type === 'reassign' && (
                <ReassignModal ticket={modal.row} agents={props.agentOptions} remindUrlBase={props.remindUrlBase} onClose={() => setModal(null)} onReassigned={handleSuccess} />
            )}
            {modal?.type === 'raise' && (
                <RaisePriorityModal ticket={modal.row} remindUrlBase={props.remindUrlBase} onClose={() => setModal(null)} onSaved={handleSuccess} />
            )}

            {ticketOpen && (
                <TicketSlideOver ticketId={ticketOpen} remindUrlBase={props.remindUrlBase} onClose={() => setTicketOpen(null)} />
            )}

            {toast && (
                <div className="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2 rounded-xl bg-gray-900 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">
                    {toast}
                </div>
            )}
        </div>
    );
}

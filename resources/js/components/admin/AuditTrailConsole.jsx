import { useMemo, useState } from 'react';
import useLockBodyScroll from '../../lib/useLockBodyScroll';
import SelectMenu from '../SelectMenu';
import { t as trans } from '../../lib/i18n';

// Language-independent sentinel — compared against real module/action codes.
const ALL = '__all';
const PAGE_SIZE = 15;

const MODULE_KEYS = {
    service_catalog: 'catalog',
    sla_configuration: 'sla',
    user_role_management: 'users',
    ticket_approval: 'approval',
    ticket_support: 'support',
    team_lead: 'teamlead',
    ticket_management: 'tickets',
    integration: 'integration',
    auth: 'auth',
};

const moduleLabel = (code) => trans(`admin.audit.module_name.${MODULE_KEYS[code]}`, {}, code);

const ACTION_KEYS = {
    create: 'create',
    update: 'edit',
    activate: 'activate',
    deactivate: 'deactivate',
    assign_support: 'update_support',
    change_level: 'update_level',
    change_role: 'update_role',
    approve: 'approve',
    request_revision: 'revision',
    reject: 'reject',
    resolve: 'resolve',
    escalate: 'escalate',
    remind: 'remind',
    reassign: 'reassign',
    raise_priority: 'raise',
    remind_rating: 'rating_remind',
    return: 'returned',
    sync: 'sync',
    login: 'login',
    start: 'start',
};

const actionLabel = (code) => (code === 'update'
    ? trans('admin.audit.edit')
    : trans(`admin.audit.action.${ACTION_KEYS[code]}`, {}, code));

export default function AuditTrailConsole({ logs, administrators }) {
    const [search, setSearch] = useState('');
    const [moduleFilter, setModuleFilter] = useState(ALL);
    const [actionFilter, setActionFilter] = useState(ALL);
    const [adminFilter, setAdminFilter] = useState(ALL);
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [page, setPage] = useState(1);
    const [detailLog, setDetailLog] = useState(null);

    const moduleOptions = useMemo(() => [{ value: ALL, label: trans('admin.audit.all_module') }, ...Object.keys(MODULE_KEYS).map((v) => ({ value: v, label: moduleLabel(v) }))], []);
    const actionOptions = useMemo(() => [{ value: ALL, label: trans('admin.audit.all_activity') }, ...Object.keys(ACTION_KEYS).map((v) => ({ value: v, label: actionLabel(v) }))], []);
    const adminOptions = useMemo(() => [{ value: ALL, label: trans('admin.audit.all_user') }, ...administrators.map((a) => ({ value: a, label: a }))], [administrators]);

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        const fromTs = dateFrom ? new Date(dateFrom + 'T00:00:00').getTime() / 1000 : null;
        const toTs = dateTo ? new Date(dateTo + 'T23:59:59').getTime() / 1000 : null;

        return logs.filter((l) => {
            const matchesSearch =
                q === '' ||
                l.target_name.toLowerCase().includes(q) ||
                l.administrator.toLowerCase().includes(q) ||
                l.description.toLowerCase().includes(q);
            const matchesModule = moduleFilter === ALL || l.module === moduleFilter;
            const matchesAction = actionFilter === ALL || l.action === actionFilter;
            const matchesAdmin = adminFilter === ALL || l.administrator === adminFilter;
            const matchesFrom = fromTs === null || l.timestamp >= fromTs;
            const matchesTo = toTs === null || l.timestamp <= toTs;
            return matchesSearch && matchesModule && matchesAction && matchesAdmin && matchesFrom && matchesTo;
        });
    }, [logs, search, moduleFilter, actionFilter, adminFilter, dateFrom, dateTo]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const page_ = Math.min(page, totalPages);
    const paginated = filtered.slice((page_ - 1) * PAGE_SIZE, page_ * PAGE_SIZE);

    function resetFilters() {
        setSearch('');
        setModuleFilter(ALL);
        setActionFilter(ALL);
        setAdminFilter(ALL);
        setDateFrom('');
        setDateTo('');
        setPage(1);
    }

    function updateFilter(setter) {
        return (value) => {
            setter(value);
            setPage(1);
        };
    }

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-3xl font-extrabold text-gray-900 dark:text-ink-1">{trans('admin.audit.title')}</h1>
                <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">{trans('admin.audit.subtitle')}</p>
            </div>

            <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => updateFilter(setSearch)(e.target.value)}
                        placeholder={trans('admin.audit.search')}
                        className="w-full max-w-sm rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <SelectMenu value={moduleFilter} onChange={updateFilter(setModuleFilter)} options={moduleOptions} />
                        <SelectMenu value={actionFilter} onChange={updateFilter(setActionFilter)} options={actionOptions} />
                        <SelectMenu value={adminFilter} onChange={updateFilter(setAdminFilter)} options={adminOptions} />
                        <input type="date" value={dateFrom} onChange={(e) => updateFilter(setDateFrom)(e.target.value)} className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm text-gray-700 dark:text-ink-2 focus:border-blue-400 focus:outline-none" />
                        <span className="text-sm text-gray-400 dark:text-ink-3">—</span>
                        <input type="date" value={dateTo} onChange={(e) => updateFilter(setDateTo)(e.target.value)} className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm text-gray-700 dark:text-ink-2 focus:border-blue-400 focus:outline-none" />
                        <button onClick={resetFilters} className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">{trans('admin.common.reset_filter')}</button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">{trans('admin.audit.showing', { from: filtered.length === 0 ? 0 : (page_ - 1) * PAGE_SIZE + 1, to: Math.min(page_ * PAGE_SIZE, filtered.length), total: filtered.length })}</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3">{trans('admin.common.time')}</th>
                                <th className="px-4 py-3">{trans('admin.common.user')}</th>
                                <th className="px-4 py-3">{trans('admin.common.module')}</th>
                                <th className="px-4 py-3">{trans('admin.common.activity')}</th>
                                <th className="px-4 py-3">{trans('admin.audit.col_target')}</th>
                                <th className="px-4 py-3 text-right">{trans('admin.common.detail')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                            {paginated.map((l) => (
                                <tr key={l.id} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    <td className="px-4 py-3 text-gray-500 dark:text-ink-2">{l.waktu}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-ink-1">{l.administrator}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-ink-2">{l.module_label}</span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700 dark:text-ink-2">{l.description}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{l.target_name}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={() => setDetailLog(l)} className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-accent-text hover:bg-blue-50 dark:hover:bg-panel-hover">
                                            Lihat Detail
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {paginated.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-14 text-center text-sm text-gray-400 dark:text-ink-3">
                                        {logs.length === 0 ? trans('admin.audit.no_activity') : trans('admin.audit.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {filtered.length > 0 && (
                    <div className="flex items-center justify-between border-t border-gray-100 dark:border-edge px-4 py-3">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={page_ === 1}
                            className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {trans('admin.audit.prev')}
                        </button>
                        <span className="text-sm text-gray-500 dark:text-ink-2">{trans('admin.audit.page', { page: page_, total: totalPages })}</span>
                        <button
                            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                            disabled={page_ === totalPages}
                            className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {trans('admin.audit.next')}
                        </button>
                    </div>
                )}
            </div>

            {detailLog && <DetailModal log={detailLog} onClose={() => setDetailLog(null)} />}
        </div>
    );
}

function humanizeKey(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function humanizeValue(value) {
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
}

function DetailModal({ log, onClose }) {
    useLockBodyScroll();
    const oldValue = log.old_value ?? {};
    const newValue = log.new_value ?? {};
    const keys = Array.from(new Set([...Object.keys(oldValue), ...Object.keys(newValue)]));
    // Notification-only actions (teguran, remind_rating, …) never carry a
    // "before" state — showing an all-dashes Sebelum column for those just
    // adds noise, so collapse to a single Field/Nilai layout instead.
    const hasBefore = Object.values(oldValue).some((v) => v !== null && v !== undefined && v !== '');

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-edge px-6 py-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-ink-2">{log.module_label}</span>
                            <span className="text-xs font-medium text-gray-400 dark:text-ink-3">{log.action_label}</span>
                        </div>
                        <h2 className="mt-1.5 truncate text-lg font-bold text-gray-900 dark:text-ink-1">{log.target_name}</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{log.administrator} · {log.waktu}</p>
                        <p className="mt-0.5 truncate text-xs text-gray-400 dark:text-ink-3" title={log.url || undefined}>
                            {trans('admin.audit.col_ip')}: {log.ip_address || '—'} · {trans('admin.audit.col_url')}: {log.url || '—'}
                        </p>
                    </div>
                    <button onClick={onClose} className="shrink-0 rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600">✕</button>
                </div>

                <div className="flex-1 overflow-y-auto">
                    <div className="px-6 py-4">
                        <p className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3 text-sm leading-relaxed text-gray-700 dark:text-ink-2">{log.description}</p>
                    </div>

                    {keys.length > 0 ? (
                        <div className="px-6 pb-6">
                            <table className="min-w-full table-fixed divide-y divide-gray-100 dark:divide-transparent text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                        <th className="w-1/3 py-2 pr-4">{trans('admin.audit.col_field')}</th>
                                        {hasBefore && <th className="w-1/3 py-2 pr-4">{trans('admin.audit.before')}</th>}
                                        <th className="py-2">{hasBefore ? trans('admin.audit.after') : trans('admin.audit.value')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                                    {keys.map((key) => (
                                        <tr key={key} className="dark:even:bg-white/[0.03]">
                                            <td className="py-2.5 pr-4 align-top font-medium text-gray-700 dark:text-ink-2">{humanizeKey(key)}</td>
                                            {hasBefore && <td className="py-2.5 pr-4 align-top text-gray-500 dark:text-ink-2">{humanizeValue(oldValue[key])}</td>}
                                            <td className="py-2.5 align-top font-medium text-gray-900 dark:text-ink-1">{humanizeValue(newValue[key])}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="px-6 pb-6 text-sm text-gray-400 dark:text-ink-3">{trans('admin.audit.no_values')}</p>
                    )}
                </div>

                <div className="flex justify-end border-t border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">{trans('admin.common.close')}</button>
                </div>
            </div>
        </div>
    );
}

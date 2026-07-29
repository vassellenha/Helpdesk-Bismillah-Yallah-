// Shared presentational primitives for the Team Lead workspace tabs, styled to
// match the existing role dashboards (rounded-2xl cards, gray-200 borders,
// subtle shadow, gray-400 labels).

export function MetricCard({ label, value, icon, iconBg, iconColor, sub }) {
    return (
        <div className="flex flex-col gap-2.5 rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-400 dark:text-ink-3">{label}</span>
                <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${iconBg} ${iconColor}`}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={icon} /></svg>
                </span>
            </div>
            <div className="text-[28px] font-extrabold leading-none text-gray-900 dark:text-ink-1">{value}</div>
            {sub && <span className="text-[11px] text-gray-400 dark:text-ink-3">{sub}</span>}
        </div>
    );
}

export function Card({ title, subtitle, right, children, className = '' }) {
    return (
        <div className={`rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm ${className}`}>
            {(title || right) && (
                <div className="flex flex-wrap items-start justify-between gap-3 p-5 pb-0">
                    <div>
                        {title && <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{title}</h2>}
                        {subtitle && <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{subtitle}</p>}
                    </div>
                    {right}
                </div>
            )}
            <div className="p-5">{children}</div>
        </div>
    );
}

export function BarRow({ label, sub, value, pct, color = 'bg-blue-500' }) {
    return (
        <div className="flex items-center gap-3">
            <span className="w-32 shrink-0 truncate text-[13px] text-gray-700 dark:text-ink-2">
                {label}
                {sub && <span className="text-gray-400 dark:text-ink-3"> · {sub}</span>}
            </span>
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
            <span className="w-10 shrink-0 text-right text-[13px] font-bold text-gray-900 dark:text-ink-1">{value}</span>
        </div>
    );
}

export const ICON = {
    ticket: 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z M14 5v14',
    clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3',
    warning: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M22 21v-2a4 4 0 0 0-3-3.9',
    check: 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9',
    bell: 'M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4',
    trending: 'M3 17l6-6 4 4 8-8 M15 7h6v6',
    bolt: 'M13 3 5 13h6l-1 8 8-11h-6l1-7Z',
    verified: 'M12 2.5l2.3 1.7 2.8-.3 1 2.7 2.5 1.4-.8 2.8.8 2.8-2.5 1.4-1 2.7-2.8-.3L12 21.5l-2.3-1.7-2.8.3-1-2.7-2.5-1.4.8-2.8-.8-2.8 2.5-1.4 1-2.7 2.8.3Z M9 12l2 2 4-4',
    escalate: 'M12 19V5 M5 12l7-7 7 7',
};

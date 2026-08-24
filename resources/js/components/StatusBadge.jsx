import { priorityBadgeStyle } from '../lib/priority';

const STATUS_STYLES = {
    Draft: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20',
    Returned: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text ring-amber-600/20',
    Open: 'bg-indigo-50 dark:bg-accent-soft text-indigo-700 dark:text-accent-text ring-indigo-600/20',
    Assigned: 'bg-sky-50 dark:bg-accent-soft text-sky-700 dark:text-accent-text ring-sky-600/20',
    'In Progress': 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text ring-blue-600/20',
    'Waiting Approval': 'bg-violet-50 dark:bg-violet-soft text-violet-700 dark:text-violet-text ring-violet-600/20',
    'Waiting for Approval': 'bg-violet-50 dark:bg-violet-soft text-violet-700 dark:text-violet-text ring-violet-600/20',
    'Waiting for Response': 'bg-purple-50 dark:bg-violet-soft text-purple-700 dark:text-violet-text ring-purple-600/20',
    Resolved: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text ring-emerald-600/20',
    Completed: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text ring-emerald-600/20',
    Closed: 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20',
    Pending: 'bg-amber-50 dark:bg-warn-soft text-amber-700 dark:text-warn-text ring-amber-600/20',
    Approved: 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text ring-emerald-600/20',
    Rejected: 'bg-red-50 dark:bg-bad-soft text-red-700 dark:text-bad-text ring-red-600/20',
};


export function StatusBadge({ status }) {
    const style = STATUS_STYLES[status] ?? 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 ring-gray-500/20';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${style}`}>
            {status}
        </span>
    );
}

/**
 * Warna lencana diambil dari peringkat SLA prioritasnya, bukan dari peta nama.
 *
 * Peta nama hanya benar selama namanya persis 'Critical'/'High'/'Medium'/'Low'.
 * Begitu Admin mengganti "Critical" jadi "Kritikal", namanya tidak ada di peta
 * dan lencana prioritas paling genting berubah abu-abu — sama persis dengan
 * lencana prioritas paling santai.
 */
export function PriorityBadge({ priority }) {
    return (
        <span
            style={priorityBadgeStyle(priority)}
            className="priority-badge inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        >
            {priority}
        </span>
    );
}

const STATUS_STYLES = {
    Open: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    'In Progress': 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'Waiting Approval': 'bg-purple-50 text-purple-700 ring-purple-600/20',
    Resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    Closed: 'bg-gray-100 text-gray-600 ring-gray-500/20',
    Pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    Approved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    Rejected: 'bg-red-50 text-red-700 ring-red-600/20',
};

const PRIORITY_STYLES = {
    Low: 'bg-gray-100 text-gray-600',
    Medium: 'bg-blue-50 text-blue-700',
    High: 'bg-orange-50 text-orange-700',
    Urgent: 'bg-red-50 text-red-700',
};

export function StatusBadge({ status }) {
    const style = STATUS_STYLES[status] ?? 'bg-gray-100 text-gray-600 ring-gray-500/20';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${style}`}>
            {status}
        </span>
    );
}

export function PriorityBadge({ priority }) {
    const style = PRIORITY_STYLES[priority] ?? 'bg-gray-100 text-gray-600';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${style}`}>
            {priority}
        </span>
    );
}

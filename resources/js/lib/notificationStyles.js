/**
 * Warna lencana ikon per jenis notifikasi.
 *
 * Sebelumnya tiap top nav punya petanya sendiri berisi jenis yang dipakai
 * perannya saja. Gabungan ini superset dari ketiganya, jadi perilakunya sama —
 * satu peran tetap hanya menerima jenis miliknya — tanpa menambah salinan
 * keempat saat halaman riwayat notifikasi ikut membutuhkannya.
 */
export const ICON_STYLES = {
    ticket_created: { bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-700 dark:text-accent-text' },
    ticket_reopened: { bg: 'bg-rose-100', color: 'text-rose-700' },
    ticket_closed: { bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    ticket_resolved: { bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    ticket_approved: { bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    ticket_rejected: { bg: 'bg-red-50 dark:bg-bad-soft', color: 'text-red-600 dark:text-bad-text' },
    ticket_escalated: { bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-700 dark:text-accent-text' },
    discussion_message: { bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-700 dark:text-accent-text' },
    sla_warning: { bg: 'bg-amber-50 dark:bg-warn-soft', color: 'text-amber-600 dark:text-warn-text' },
    sla_breach: { bg: 'bg-red-50 dark:bg-bad-soft', color: 'text-red-600 dark:text-bad-text' },
    sla_teguran: { bg: 'bg-red-50 dark:bg-bad-soft', color: 'text-red-600 dark:text-bad-text' },
    waiting_decision: { bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-700 dark:text-accent-text' },
    decision_recorded: { bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    history_updated: { bg: 'bg-gray-100 dark:bg-panel-3', color: 'text-gray-500 dark:text-ink-2' },
};

export function iconStyle(type) {
    return ICON_STYLES[type] ?? ICON_STYLES.ticket_created;
}

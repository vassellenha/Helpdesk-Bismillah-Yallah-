import { useState } from 'react';

import { apiFetch } from '../lib/api';
import { t as trans } from '../lib/i18n';
import { iconStyle } from '../lib/notificationStyles';

/**
 * Seluruh notifikasi satu peran, dipecah per halaman.
 *
 * Lonceng hanya memuat 20 terbaru; halaman ini yang membuat sisanya terjangkau.
 * Paginasinya di server — daftar ini tumbuh tanpa batas seiring waktu, jadi
 * memuat semuanya ke browser hanya menunda masalah yang sama.
 */
export default function NotificationHistoryPage({
    items = [],
    page = 1,
    lastPage = 1,
    total = 0,
    unreadCount: unreadFromServer = 0,
    markAllReadUrl,
}) {
    const [rows, setRows] = useState(items);
    const [unreadCount, setUnreadCount] = useState(unreadFromServer);
    const [busy, setBusy] = useState(false);

    function goToPage(next) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(next));
        window.location.href = url.toString();
    }

    async function markAllRead() {
        setBusy(true);
        setRows((prev) => prev.map((n) => ({ ...n, unread: false })));
        setUnreadCount(0);
        try {
            await apiFetch(markAllReadUrl, { method: 'POST', body: JSON.stringify({}) });
        } catch {
            // Best-effort — layar sudah menampilkan "terbaca" secara lokal.
        } finally {
            setBusy(false);
        }
    }

    function open(n) {
        if (n.unread) {
            setUnreadCount((c) => Math.max(0, c - 1));
            if (n.markReadUrl) {
                apiFetch(n.markReadUrl, { method: 'POST', body: JSON.stringify({}) }).catch(() => {});
            }
        }
        if (n.href) {
            window.location.href = n.href;
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">
                        {trans('common.notifications.title')}
                    </h1>
                    <p className="mt-1 text-[13px] text-gray-400 dark:text-ink-3">
                        {trans('common.notifications.subtitle', { total, unread: unreadCount })}
                    </p>
                </div>
                {unreadCount > 0 && (
                    <button
                        type="button"
                        onClick={markAllRead}
                        disabled={busy}
                        className="rounded-full border border-gray-200 dark:border-edge-strong px-4 py-2 text-[13px] font-bold text-blue-700 dark:text-accent-text hover:bg-blue-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {trans('common.notifications.mark_all')}
                    </button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                {rows.map((n) => {
                    const style = iconStyle(n.type);
                    return (
                        <button
                            key={n.id}
                            type="button"
                            onClick={() => open(n)}
                            className={`flex w-full items-start gap-3 border-b border-gray-50 dark:border-transparent px-5 py-4 text-left last:border-0 hover:bg-blue-50/60 dark:hover:bg-panel-hover ${n.unread ? 'bg-blue-50/50 dark:bg-accent-soft' : ''}`}
                        >
                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] ${style.bg} ${style.color}`}>
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={n.icon} /></svg>
                            </span>
                            <span className="min-w-0 flex-1 space-y-1">
                                <span className="block text-[13px] leading-snug text-gray-800 dark:text-ink-1">{n.text}</span>
                                <span className="block text-[11.5px] text-gray-400 dark:text-ink-3">{n.time}</span>
                            </span>
                            {n.unread && <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-500" />}
                        </button>
                    );
                })}

                {rows.length === 0 && (
                    <p className="px-5 py-12 text-center text-sm text-gray-400 dark:text-ink-3">
                        {trans('common.notifications.empty')}
                    </p>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-4 py-3">
                    <span className="text-xs text-gray-400 dark:text-ink-3">
                        {trans('common.pagination.page', { page, total: lastPage })}
                    </span>
                    {lastPage > 1 && (
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => goToPage(page - 1)}
                                disabled={page === 1}
                                className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-[13px] font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {trans('common.pagination.prev')}
                            </button>
                            <button
                                type="button"
                                onClick={() => goToPage(page + 1)}
                                disabled={page === lastPage}
                                className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-[13px] font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {trans('common.pagination.next')}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

import { useEffect, useRef, useState } from 'react';
import ThemeToggle from '../ThemeToggle';
import { t as trans } from '../../lib/i18n';
import { apiFetch } from '../../lib/api';
import MyProfileModal from '../MyProfileModal';
import LogoutButton from '../LogoutButton';

const ICON_STYLES = {
    sla_breach: { bg: 'bg-red-50 dark:bg-bad-soft', color: 'text-red-600 dark:text-bad-text' },
    waiting_decision: { bg: 'bg-blue-50 dark:bg-accent-soft', color: 'text-blue-700 dark:text-accent-text' },
    decision_recorded: { bg: 'bg-emerald-50 dark:bg-ok-soft', color: 'text-emerald-600 dark:text-ok-text' },
    history_updated: { bg: 'bg-gray-100 dark:bg-panel-3', color: 'text-gray-500 dark:text-ink-2' },
};

export default function ApproverTopNav({ notifications = [], user = {}, inboxUrl = '/', ticketsUrl = '/', markAllReadUrl, profileUrl }) {
    const [items, setItems] = useState(notifications);
    const [notifOpen, setNotifOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [profileModalOpen, setProfileModalOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setNotifOpen(false);
                setProfileOpen(false);
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const unreadCount = items.filter((n) => n.unread).length;

    async function markAllRead() {
        setItems((prev) => prev.map((n) => ({ ...n, unread: false })));
        try {
            await apiFetch(markAllReadUrl, { method: 'POST', body: JSON.stringify({}) });
        } catch {
            // Best-effort — the bell already reflects "read" locally.
        }
    }

    async function openNotification(n) {
        setItems((prev) => prev.map((it) => (it.id === n.id ? { ...it, unread: false } : it)));
        if (n.unread && n.markReadUrl) {
            apiFetch(n.markReadUrl, { method: 'POST', body: JSON.stringify({}) }).catch(() => {});
        }
        window.location.href = n.href ?? ticketsUrl;
    }

    return (
        <div ref={ref} className="flex items-center gap-2">
            <ThemeToggle />
            <div className="relative">
                <button
                    type="button"
                    onClick={() => {
                        setNotifOpen((v) => !v);
                        setProfileOpen(false);
                    }}
                    aria-label={trans('common.notifications.title')}
                    className={`relative flex h-9 w-9 items-center justify-center rounded-[10px] text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover ${notifOpen ? 'bg-gray-100 dark:bg-panel-3' : ''}`}
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z"/><path d="M10 21h4"/></svg>
                    {unreadCount > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                            {unreadCount}
                        </span>
                    )}
                </button>

                {notifOpen && (
                    <div className="absolute right-0 top-12 z-50 w-[366px] overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-xl">
                        <div className="flex items-center justify-between border-b border-gray-100 dark:border-edge px-4 py-3.5">
                            <span className="text-sm font-bold text-gray-900 dark:text-ink-1">{trans('common.notifications.title')}</span>
                            <button onClick={markAllRead} className="text-[11px] font-semibold text-blue-600 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">
                                Tandai semua dibaca
                            </button>
                        </div>
                        <div className="max-h-[348px] overflow-y-auto">
                            {items.map((n) => {
                                const style = ICON_STYLES[n.type] ?? ICON_STYLES.waiting_decision;
                                return (
                                    <button
                                        key={n.id}
                                        onClick={() => openNotification(n)}
                                        className={`flex w-full items-start gap-2.5 border-b border-gray-50 dark:border-transparent px-4 py-3 text-left last:border-0 hover:bg-blue-50/60 dark:hover:bg-panel-hover ${n.unread ? 'bg-blue-50/50 dark:bg-accent-soft' : 'bg-white dark:bg-panel-2'}`}
                                    >
                                        <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-[9px] ${style.bg} ${style.color}`}>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={n.icon} /></svg>
                                        </span>
                                        <span className="min-w-0 flex-1 space-y-0.5">
                                            <span className="block text-[12.5px] leading-snug text-gray-800 dark:text-ink-1">{n.text}</span>
                                            <span className="block text-[11px] text-gray-400 dark:text-ink-3">{n.time}</span>
                                        </span>
                                        {n.unread && <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-500" />}
                                    </button>
                                );
                            })}
                            {items.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400 dark:text-ink-3">{trans('common.notifications.empty')}</p>}
                        </div>
                    </div>
                )}
            </div>

            <div className="relative">
                <button
                    type="button"
                    onClick={() => {
                        setProfileOpen((v) => !v);
                        setNotifOpen(false);
                    }}
                    className={`flex items-center gap-2.5 rounded-[10px] px-1.5 py-1 hover:bg-gray-100 dark:hover:bg-panel-hover ${profileOpen ? 'bg-gray-100 dark:bg-panel-3' : ''}`}
                >
                    <span className="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:text-accent-text">
                        {user.initials ?? 'U'}
                    </span>
                    <span className="hidden text-left leading-tight sm:block">
                        <span className="block text-[13px] font-semibold text-gray-900 dark:text-ink-1">{user.name ?? 'User'}</span>
                        <span className="block text-[11px] text-gray-400 dark:text-ink-3">{user.title ?? ''}</span>
                    </span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="m6 9 6 6 6-6"/></svg>
                </button>

                {profileOpen && (
                    <div className="absolute right-0 top-[54px] z-50 w-[250px] overflow-hidden rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-xl">
                        <div className="mb-1.5 flex items-center gap-2.5 border-b border-gray-50 dark:border-edge px-3 py-2.5">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:text-accent-text">
                                {user.initials ?? 'U'}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-[13px] font-bold text-gray-900 dark:text-ink-1">{user.name ?? 'User'}</span>
                                <span className="block truncate text-[11px] text-gray-400 dark:text-ink-3">{user.title ?? ''}</span>
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                setProfileModalOpen(true);
                                setProfileOpen(false);
                            }}
                            className="flex w-full items-center gap-2.5 rounded-[9px] px-3 py-2.5 text-left text-[13px] font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] hover:text-gray-900 dark:hover:text-ink-1"
                        >
                            My Profile
                        </button>
                        <a href={ticketsUrl} className="flex items-center gap-2.5 rounded-[9px] px-3 py-2.5 text-[13px] font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] hover:text-gray-900 dark:hover:text-ink-1">
                            My Tickets
                        </a>
                        <div className="mt-1.5 border-t border-gray-50 dark:border-edge pt-1.5">
                            <LogoutButton />
                        </div>
                    </div>
                )}
            </div>

            {profileModalOpen && <MyProfileModal profileUrl={profileUrl} onClose={() => setProfileModalOpen(false)} />}
        </div>
    );
}

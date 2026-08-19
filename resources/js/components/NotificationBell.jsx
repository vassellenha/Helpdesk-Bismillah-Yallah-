import { useEffect, useRef, useState } from 'react';

export default function NotificationBell({ notifications = [] }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const unreadCount = notifications.filter((n) => n.unread).length;

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover"
                aria-label="Notifikasi"
            >
                <svg viewBox="0 0 24 24" fill="none" className="h-5 w-5">
                    <path
                        d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.65V5a2 2 0 1 0-4 0v.35A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-semibold text-white">
                        {unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="liquid-glass-dense absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-xl shadow-lg">
                    <div className="flex items-center justify-between border-b border-gray-100 dark:border-edge px-4 py-3">
                        <p className="text-sm font-semibold text-gray-900 dark:text-ink-1">Notifikasi</p>
                        <span className="text-xs text-gray-400 dark:text-ink-3">{unreadCount} belum dibaca</span>
                    </div>
                    <ul className="max-h-80 overflow-y-auto">
                        {notifications.map((n) => (
                            <li key={n.id} className="border-b border-gray-50 dark:border-transparent px-4 py-3 last:border-0 hover:bg-gray-50 dark:hover:bg-panel-hover">
                                <div className="flex items-start gap-2">
                                    {n.unread && <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-500" />}
                                    <div className={n.unread ? '' : 'ml-3.5'}>
                                        <p className="text-sm text-gray-800 dark:text-ink-1">{n.title}</p>
                                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{n.time}</p>
                                    </div>
                                </div>
                            </li>
                        ))}
                        {notifications.length === 0 && (
                            <li className="px-4 py-6 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada notifikasi.</li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

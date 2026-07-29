import { useEffect, useRef } from 'react';

const TONE = {
    default: 'text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]',
    danger: 'text-red-600 dark:text-bad-text hover:bg-red-50',
    success: 'text-emerald-600 dark:text-ok-text hover:bg-emerald-50',
};

/**
 * Clamped { top, left } for a row-action menu anchored under a trigger
 * button — keeps it on-screen near the right/bottom viewport edge, and
 * flips above the trigger when there's no room below.
 */
export function menuPositionFor(triggerRect, { width = 176, height = 96 } = {}) {
    const left = Math.min(Math.max(8, triggerRect.right - width), window.innerWidth - width - 8);
    const top = triggerRect.bottom + height + 8 > window.innerHeight
        ? Math.max(8, triggerRect.top - height - 4)
        : triggerRect.bottom + 4;

    return { top, left };
}

/**
 * Small "•••" dropdown for a table row — Edit / Aktifkan / Nonaktifkan style
 * actions. Owns its own outside-click close, so callers just track the
 * anchor position and swap it for `null` in `onClose`.
 */
export default function RowActionMenu({ anchor, items, onClose, width = 176 }) {
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        }
        function onEscape(e) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('mousedown', onClickOutside);
        document.addEventListener('keydown', onEscape);
        return () => {
            document.removeEventListener('mousedown', onClickOutside);
            document.removeEventListener('keydown', onEscape);
        };
    }, [onClose]);

    return (
        <div
            ref={ref}
            style={{ top: anchor.top, left: anchor.left, width }}
            className="fixed z-50 overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-xl"
        >
            {items.map((item, i) => (
                <div key={item.label}>
                    {item.divider && <div className="my-1 h-px bg-gray-100 dark:bg-panel-3" />}
                    <button
                        type="button"
                        onClick={() => { item.onClick(); onClose(); }}
                        className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium transition-colors ${TONE[item.tone ?? 'default']}`}
                    >
                        {item.icon && (
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                                <path d={item.icon} />
                            </svg>
                        )}
                        {item.label}
                    </button>
                </div>
            ))}
        </div>
    );
}

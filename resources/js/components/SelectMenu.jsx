import { useEffect, useRef, useState } from 'react';

/**
 * Custom-styled replacement for a plain `<select>` — native option lists
 * can't be restyled cross-browser (stuck with the OS's default blue
 * highlight/font), which looks inconsistent next to the rest of the app.
 */
/**
 * The open menu is height-capped and scrolls: some lists (applications, work
 * units, requesters) run to dozens of entries and would otherwise render taller
 * than the viewport, with no way to reach the bottom of the list.
 */
export default function SelectMenu({ value, onChange, options }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const current = options.find((o) => o.value === value);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className={`flex w-full min-w-[160px] items-center justify-between gap-2 rounded-[10px] border bg-white dark:bg-panel-2 px-3 py-2.5 text-[13px] text-gray-700 dark:text-ink-2 hover:border-gray-300 dark:hover:border-ink-3 focus:outline-none ${open ? 'border-blue-400' : 'border-gray-200 dark:border-edge-strong'}`}
            >
                <span>{current?.label ?? value}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 text-gray-400 dark:text-ink-3 transition-transform ${open ? 'rotate-180' : ''}`}>
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 top-[calc(100%+4px)] z-30 max-h-[280px] w-full min-w-[180px] overflow-y-auto rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 py-1 shadow-lg">
                    {options.map((o) => (
                        <button
                            key={o.value}
                            type="button"
                            onClick={() => { onChange(o.value); setOpen(false); }}
                            className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-[13px] hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] ${o.value === value ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                        >
                            {o.label}
                            {o.value === value && (
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" className="shrink-0"><path d="M20 6 9 17l-5-5" /></svg>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

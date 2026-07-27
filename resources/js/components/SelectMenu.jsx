import { useEffect, useRef, useState } from 'react';

/**
 * Custom-styled replacement for a plain `<select>` — native option lists
 * can't be restyled cross-browser (stuck with the OS's default blue
 * highlight/font), which looks inconsistent next to the rest of the app.
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
                className={`flex w-full min-w-[160px] items-center justify-between gap-2 rounded-[10px] border bg-white px-3 py-2.5 text-[13px] text-gray-700 hover:border-gray-300 focus:outline-none ${open ? 'border-blue-400' : 'border-gray-200'}`}
            >
                <span>{current?.label ?? value}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}>
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 top-[calc(100%+4px)] z-30 w-full min-w-[180px] overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    {options.map((o) => (
                        <button
                            key={o.value}
                            type="button"
                            onClick={() => { onChange(o.value); setOpen(false); }}
                            className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-[13px] hover:bg-gray-50 ${o.value === value ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-700'}`}
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

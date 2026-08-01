import { useEffect, useRef, useState } from 'react';

// Inline SVG, not emoji flags — Windows' emoji font renders 🇮🇩/🇺🇸 as plain
// "ID"/"US" letter tiles instead of an actual flag, so this is the only way
// to guarantee a real flag renders regardless of OS/font.
function FlagID({ className }) {
    return (
        <svg viewBox="0 0 3 2" className={className} aria-hidden="true">
            <rect width="3" height="1" fill="#CE1126" />
            <rect width="3" height="1" y="1" fill="#FFFFFF" />
        </svg>
    );
}

function FlagUS({ className }) {
    return (
        <svg viewBox="0 0 26 13" className={className} aria-hidden="true">
            <rect width="26" height="13" fill="#FFFFFF" />
            {[0, 2, 4, 6, 8, 10, 12].map((y) => (
                <rect key={y} x="0" y={y} width="26" height="1" fill="#B22234" />
            ))}
            <rect width="10.4" height="7" fill="#3C3B6E" />
        </svg>
    );
}

const LANGUAGES = [
    { code: 'ID', Flag: FlagID, label: 'Bahasa Indonesia' },
    { code: 'EN', Flag: FlagUS, label: 'English' },
];

/**
 * UI-only for now — picks a language and remembers it, but nothing on the
 * page actually retranslates yet. Wiring real i18n (Blade + every React
 * component) is a separate, much larger piece of work.
 */
export default function LanguageSwitcher() {
    const [open, setOpen] = useState(false);
    const [lang, setLang] = useState('ID');
    const ref = useRef(null);

    useEffect(() => {
        const saved = localStorage.getItem('helpdesk_locale');
        if (saved === 'ID' || saved === 'EN') setLang(saved);
    }, []);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    function select(code) {
        setLang(code);
        localStorage.setItem('helpdesk_locale', code);
        setOpen(false);
    }

    const current = LANGUAGES.find((l) => l.code === lang) ?? LANGUAGES[0];

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex h-9 items-center gap-1.5 rounded-full px-2 hover:bg-gray-100 dark:hover:bg-panel-hover"
                aria-label="Ganti bahasa"
            >
                <current.Flag className="h-4 w-[22px] shrink-0 overflow-hidden rounded-[3px] object-cover ring-1 ring-black/10 dark:ring-white/10" />
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            {open && (
                <ul className="absolute right-0 top-[46px] z-50 w-48 overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 py-1 shadow-xl">
                    {LANGUAGES.map((l) => (
                        <li key={l.code}>
                            <button
                                type="button"
                                onClick={() => select(l.code)}
                                className={`flex w-full items-center justify-between gap-2.5 px-3.5 py-2 text-left text-[13px] font-medium hover:bg-gray-50 dark:hover:bg-panel-hover ${lang === l.code ? 'text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                            >
                                <span className="flex items-center gap-2.5">
                                    <l.Flag className="h-4 w-[22px] shrink-0 overflow-hidden rounded-[3px] object-cover ring-1 ring-black/10 dark:ring-white/10" />
                                    {l.label}
                                </span>
                                {lang === l.code && (
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

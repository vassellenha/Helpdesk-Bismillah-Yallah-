import { useEffect, useMemo, useRef, useState } from 'react';
import SelectMenu from '../SelectMenu';
import { t as trans } from '../../lib/i18n';

/**
 * Filter dropdown milik layar Service Catalog, dipakai bersama oleh tabel
 * Subjek dan tabel Team Lead per Sub Kategori.
 *
 * Dipisahkan ke modul sendiri, bukan diekspor dari ServiceCatalogConsole:
 * tabel Sub Kategori adalah anak Console, jadi mengimpor balik dari sana
 * membuat lingkaran impor. Yang dibagi di sini juga termasuk sentinel ALL —
 * nilainya dibandingkan dengan isi katalog yang sesungguhnya, jadi ia harus
 * satu nilai yang sama di semua pemakainya.
 */

// Language-independent sentinel: this is compared against real catalog values.
export const ALL = '__all';

export function Select({ value, onChange, label, options }) {
    const opts = useMemo(() => [
        { value: ALL, label },
        ...options.map((opt) => {
            const [val, text] = Array.isArray(opt) ? opt : [opt, opt];
            return { value: val, label: text };
        }),
    ], [label, options]);

    return <SelectMenu value={value} onChange={onChange} options={opts} />;
}

// Layanan/Sub Category/Issue Category can run into dozens of options —
// a plain <select> makes those unscannable, so this swaps in a search box
// over a styled, height-capped list instead (same pattern as the PIC
// filter in Admin Ticket Management).
export function SearchableSelect({ value, onChange, label, options, searchPlaceholder = trans('admin.common.search') }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const normalized = options.map((opt) => (Array.isArray(opt) ? opt : [opt, opt]));
    const filtered = normalized.filter(([, text]) => text.toLowerCase().includes(query.toLowerCase()));
    const selectedText = normalized.find(([val]) => val === value)?.[1];

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex min-w-[160px] items-center justify-between gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-ink-2 hover:border-gray-300 dark:hover:border-ink-3 focus:border-blue-400 focus:outline-none"
            >
                <span className="truncate">{value === ALL ? label : selectedText ?? label}</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 text-gray-400 dark:text-ink-3"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            {open && (
                <div className="absolute left-0 top-[calc(100%+4px)] z-30 w-64 overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg">
                    <div className="border-b border-gray-100 dark:border-edge p-2">
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="w-full rounded-md border border-gray-200 dark:border-edge-strong px-2.5 py-1.5 text-sm outline-none focus:border-blue-400"
                        />
                    </div>
                    <ul className="max-h-64 overflow-y-auto py-1">
                        <li>
                            <button
                                type="button"
                                onClick={() => { onChange(ALL); setOpen(false); setQuery(''); }}
                                className={`block w-full px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-panel-hover ${value === ALL ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                            >
                                {label}
                            </button>
                        </li>
                        {filtered.map(([val, text]) => (
                            <li key={val}>
                                <button
                                    type="button"
                                    onClick={() => { onChange(val); setOpen(false); setQuery(''); }}
                                    className={`block w-full truncate px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-panel-hover ${val === value ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                                >
                                    {text}
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && <li className="px-3 py-4 text-center text-xs text-gray-400 dark:text-ink-3">{trans('admin.common.no_result')}</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}

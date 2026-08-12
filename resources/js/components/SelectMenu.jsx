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
/**
 * `searchable` sengaja opt-in, bukan otomatis untuk semua dropdown.
 *
 * Sebagian besar daftar di app ini pendek dan tetap — status, prioritas, peran —
 * dan kotak cari di atas empat pilihan hanya menambah satu hal yang harus
 * dilewati. Yang benar-benar membutuhkannya adalah daftar yang tumbuh mengikuti
 * data: filter pelaku di Audit Trail kini berisi ribuan pegawai dari direktori
 * perusahaan, dan menemukan satu nama di sana berarti menggulir ribuan baris.
 */
export default function SelectMenu({ value, onChange, options, searchable = false, searchPlaceholder = 'Cari…' }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);
    const searchRef = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    // Kata kunci lama dibuang setiap menu ditutup. Kalau tidak, membuka menu
    // berikutnya menampilkan daftar yang sudah tersaring tanpa alasan yang
    // terlihat — pilihan yang dicari seolah hilang.
    useEffect(() => {
        if (! open) {
            setQuery('');
            return;
        }
        if (searchable) searchRef.current?.focus();
    }, [open, searchable]);

    const current = options.find((o) => o.value === value);
    const visible = searchable && query.trim() !== ''
        ? options.filter((o) => String(o.label).toLowerCase().includes(query.trim().toLowerCase()))
        : options;

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
                    {searchable && (
                        // Menempel di atas saat daftar digulir — pada ribuan
                        // baris, kotak cari yang ikut menggulir hilang setelah
                        // beberapa putaran dan tidak bisa ditemukan lagi.
                        <div className="sticky top-0 z-10 border-b border-gray-100 dark:border-edge bg-white dark:bg-panel-2 p-2">
                            <input
                                ref={searchRef}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={searchPlaceholder}
                                className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-2.5 py-1.5 text-[13px] text-gray-700 dark:text-ink-2 focus:border-blue-400 focus:outline-none"
                            />
                        </div>
                    )}
                    {visible.length === 0 && (
                        <p className="px-3 py-4 text-center text-[13px] text-gray-400 dark:text-ink-3">Tidak ada yang cocok.</p>
                    )}
                    {visible.map((o) => (
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

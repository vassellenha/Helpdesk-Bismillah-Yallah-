import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import Portal from './Portal';

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
/**
 * Panel dropdown-nya di-portal ke <body> dan diposisikan `fixed` lewat
 * getBoundingClientRect() — bukan `absolute` relatif ke wrapper sendiri
 * seperti sebelumnya.
 *
 * Dua alasan sekaligus, sama seperti Portal.jsx: (1) wrapper ini bisa duduk di
 * dalam kontainer yang men-scroll sendiri (mis. isi popup Ekspor Pengguna) —
 * `absolute` biasa terpotong begitu panelnya melebihi area yang sedang
 * terlihat, persis bug yang terlihat waktu dropdown "Jabatan" dibuka dekat
 * dasar popup itu. (2) Panel yang memakai backdrop-blur (gaya "liquid glass")
 * menjadi containing block untuk descendant `position: fixed`, jadi tanpa
 * Portal ke <body>, `fixed` di sini akan relatif ke panel kaca itu, bukan ke
 * viewport — dan posisinya meleset.
 */
export default function SelectMenu({ value, onChange, options, searchable = false, searchPlaceholder = 'Cari…' }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [rect, setRect] = useState(null);
    const triggerRef = useRef(null);
    const panelRef = useRef(null);
    const searchRef = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (triggerRef.current?.contains(e.target)) return;
            if (panelRef.current?.contains(e.target)) return;
            setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    // Posisi dihitung ulang tiap dibuka, plus saat halaman digulir/diubah
    // ukuran selama menu terbuka — kalau tidak, menu yang sudah terbuka akan
    // tertinggal di posisi lama begitu leluhurnya (mis. body modal) digulir.
    //
    // useLayoutEffect, BUKAN useEffect: keduanya jalan setelah DOM ter-mutasi,
    // tapi useEffect jalan setelah browser SUDAH mengecat layar, jadi ada satu
    // frame di mana panel sempat tergambar di posisi lama (atau di 0,0) sebelum
    // lompat ke posisi benar — kedipan yang di layar lambat/refresh rate
    // rendah bisa terlihat seperti dua elemen bertumpuk sesaat. useLayoutEffect
    // jalan SEBELUM cat layar, jadi posisi yang salah tidak pernah sempat
    // terlihat sama sekali.
    useLayoutEffect(() => {
        if (! open) return;

        function updateRect() {
            if (triggerRef.current) setRect(triggerRef.current.getBoundingClientRect());
        }

        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);
        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [open]);

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

    const PANEL_MAX_HEIGHT = 280;
    const openUpward = rect ? rect.bottom + PANEL_MAX_HEIGHT + 8 > window.innerHeight : false;

    return (
        <div className="relative">
            <button
                ref={triggerRef}
                type="button"
                onClick={() => setOpen((v) => !v)}
                className={`flex w-full min-w-[160px] items-center justify-between gap-2 rounded-[10px] border bg-white dark:bg-panel-2 px-3 py-2.5 text-[13px] text-gray-700 dark:text-ink-2 hover:border-gray-300 dark:hover:border-ink-3 focus:outline-none ${open ? 'border-blue-400' : 'border-gray-200 dark:border-edge-strong'}`}
            >
                <span>{current?.label ?? value}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={`shrink-0 text-gray-400 dark:text-ink-3 transition-transform ${open ? 'rotate-180' : ''}`}>
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>

            {open && rect && (
                <Portal>
                    {/*
                        Kotak cari dan daftar pilihan dipisah jadi DUA kotak flex,
                        bukan satu kontainer `overflow-y-auto` dengan kotak cari
                        `sticky` di dalamnya seperti sebelumnya. `sticky` cuma
                        tinggal di tempat SELAMA elemen sebelum/sesudahnya tidak
                        pernah salah ukur tinggi — begitu itu meleset (pernah
                        terlihat: baris pilihan pertama muncul di ATAS kotak cari,
                        bukan di bawahnya), sticky jadi sumber bug yang sulit
                        dilacak. Flex-column dengan kotak cari `shrink-0` di atas
                        dan daftar `overflow-y-auto` terpisah di bawahnya membuat
                        urutan visualnya taat urutan DOM, tidak ada cara keduanya
                        bertukar tempat.
                    */}
                    <div
                        ref={panelRef}
                        style={{
                            position: 'fixed',
                            ...(openUpward
                                ? { bottom: window.innerHeight - rect.top + 4, maxHeight: Math.max(120, rect.top - 12) }
                                : { top: rect.bottom + 4, maxHeight: Math.min(PANEL_MAX_HEIGHT, window.innerHeight - rect.bottom - 12) }),
                            right: Math.max(8, window.innerWidth - rect.right),
                            width: Math.max(rect.width, 180),
                        }}
                        className="z-50 flex flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg"
                    >
                        {searchable && (
                            <div className="shrink-0 border-b border-gray-100 dark:border-edge p-2">
                                <input
                                    ref={searchRef}
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder={searchPlaceholder}
                                    className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-2.5 py-1.5 text-[13px] text-gray-700 dark:text-ink-2 focus:border-blue-400 focus:outline-none"
                                />
                            </div>
                        )}
                        <div className="min-h-0 flex-1 overflow-y-auto py-1">
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
                    </div>
                </Portal>
            )}
        </div>
    );
}

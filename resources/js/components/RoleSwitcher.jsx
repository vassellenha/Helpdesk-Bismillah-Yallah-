import { useEffect, useRef, useState } from 'react';

export default function RoleSwitcher({ roles = [], current = null, portalUrl = '/' }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

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
        // `group` menyalakan varian group-hover/group-focus-within di bawah:
        // tombolnya menciut jadi ikon yang menyempil di tepi kanan, dan baru
        // memanjang ke kiri saat disentuh kursor. Sebelumnya ia selalu tampil
        // penuh dan menutupi apa pun yang kebetulan ada di pojok kanan bawah —
        // paling sering tombol "berikutnya" pada daftar berhalaman.
        <div ref={ref} className="group fixed bottom-6 right-6 z-50 flex flex-col items-end">
            {open && (
                <div className="mb-2 w-72 overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg">
                    <div className="border-b border-gray-100 dark:border-edge px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Pilih Role</p>
                    </div>
                    <ul className="max-h-80 overflow-y-auto py-1">
                        {roles.map((role) => (
                            <li key={role.key}>
                                <a
                                    href={role.url}
                                    className={`flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] ${
                                        role.key === current ? 'bg-blue-50/60 dark:bg-accent-soft' : ''
                                    }`}
                                >
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-accent-soft text-xs font-bold text-blue-700 dark:text-accent-text">
                                        {role.initials}
                                    </span>
                                    <span className="flex-1">
                                        <span className="block font-medium text-gray-900 dark:text-ink-1">{role.label}</span>
                                    </span>
                                    {role.key === current && (
                                        <span className="text-xs font-medium text-blue-600 dark:text-accent-text">Aktif</span>
                                    )}
                                </a>
                            </li>
                        ))}
                        <li>
                            <a
                                href={portalUrl}
                                className="flex items-center gap-3 border-t border-gray-100 dark:border-edge px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                            >
                                ← Kembali ke Mockup Review
                            </a>
                        </li>
                    </ul>
                </div>
            )}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                title="Switch Role"
                /*
                    Kaca bertint biru.

                    MEKANIKA kacanya diambil apa adanya dari navbar (lihat
                    header di layouts/*.blade.php): backdrop-blur-lg +
                    saturate-150, border tipis, dan bayangan lebar yang sama
                    persis. Yang berbeda hanya TINT-nya — navbar netral
                    (bg-white/65), tombol ini biru — supaya ia tetap terbaca
                    sebagai tombol aksi, bukan sepotong panel yang mengambang.

                    Biru dipakai sebagai lapisan semi-transparan, BUKAN warna
                    padat: latar yang pekat akan menutup backdrop-blur di
                    belakangnya dan kacanya hilang sama sekali — yang tersisa
                    cuma tombol biru biasa dengan bayangan mahal.
                */
                className={`flex items-center rounded-full border border-white/20 dark:border-white/10 bg-blue-600/70 dark:bg-blue-500/25 py-2.5 pl-3.5 pr-3.5 text-sm font-semibold text-white dark:text-ink-1 shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 ease-out hover:bg-blue-600/85 dark:hover:bg-blue-500/40 ${
                    // Saat panelnya terbuka tombolnya WAJIB tetap penuh: ia jadi
                    // sandaran panel di atasnya, dan menciut di tengah interaksi
                    // membuat panel tampak menggantung tanpa asal.
                    open
                        ? 'translate-x-0 opacity-100'
                        // 80%, bukan 60% seperti saat tombolnya masih biru
                        // pekat: kaca sudah tembus pandang dengan sendirinya,
                        // jadi meredupkannya sekuat dulu membuatnya nyaris
                        // hilang di atas latar terang.
                        : 'translate-x-3 opacity-80 group-hover:translate-x-0 group-hover:opacity-100 group-focus-within:translate-x-0 group-focus-within:opacity-100'
                }`}
            >
                <span aria-hidden="true">⇄</span>
                {/*
                    Labelnya diciutkan lewat max-width + overflow-hidden, BUKAN
                    dilepas dari DOM: teksnya tetap terbaca pembaca layar dalam
                    keadaan menciut, dan lebarnya bisa dianimasikan — `w-auto`
                    tidak bisa. Padding kirinya ikut terpotong saat max-w-0,
                    jadi tidak ada celah menganga di sebelah ikon.
                */}
                <span
                    className={`overflow-hidden whitespace-nowrap transition-all duration-200 ease-out ${
                        open
                            ? 'max-w-[8rem] pl-2'
                            : 'max-w-0 pl-0 group-hover:max-w-[8rem] group-hover:pl-2 group-focus-within:max-w-[8rem] group-focus-within:pl-2'
                    }`}
                >
                    Switch Role
                </span>
            </button>
        </div>
    );
}

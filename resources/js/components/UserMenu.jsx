import { useEffect, useRef, useState } from 'react';
import LogoutButton from './LogoutButton';
import MyProfileModal from './MyProfileModal';

/**
 * Menu identitas di pojok kanan atas.
 *
 * `dashboardUrl` boleh kosong, dan saat kosong butirnya TIDAK dirender.
 * Sebelumnya di tempat ini ada butir "Pengaturan" dengan href="#" dan
 * preventDefault() — tombol yang terlihat bisa diklik tapi tidak pernah
 * membawa ke mana pun. Layar pengaturannya sendiri tidak pernah dibuat, jadi
 * yang ada hanyalah janji kosong yang setiap kali diklik terasa seperti
 * aplikasi yang rusak. Lebih baik butirnya tidak ada sama sekali.
 */
export default function UserMenu({ name, title, initials, profileUrl, dashboardUrl = null }) {
    const [open, setOpen] = useState(false);
    const [profileModalOpen, setProfileModalOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    return (
        <div ref={ref} className="relative">
            <button type="button" onClick={() => setOpen((v) => !v)} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:text-accent-text">
                    {initials}
                </span>
                <span className="hidden text-left sm:block">
                    <span className="block text-sm font-semibold text-gray-900 dark:text-ink-1">{name}</span>
                    <span className="block text-xs text-gray-400 dark:text-ink-3">{title}</span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400 dark:text-ink-3">
                    <path d="m6 9 6 6 6-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-2 w-[250px] overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 py-1 shadow-lg">
                    {/*
                        Kepala menu: siapa yang sedang masuk.
                        Bukan hiasan — di layar lebar nama itu memang sudah
                        terlihat di tombolnya, tapi di layar sempit tombolnya
                        menyusut jadi avatar saja (lihat `hidden sm:block` di
                        atas). Tanpa blok ini, satu-satunya tempat nama pemakai
                        muncul ikut hilang di layar kecil.
                    */}
                    <div className="mb-1 flex items-center gap-2.5 border-b border-gray-50 dark:border-edge px-3 py-2.5">
                        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:text-accent-text">
                            {initials}
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-[13px] font-bold text-gray-900 dark:text-ink-1">{name}</span>
                            <span className="block truncate text-[11px] text-gray-400 dark:text-ink-3">{title}</span>
                        </span>
                    </div>

                    <a
                        href="#"
                        onClick={(e) => {
                            e.preventDefault();
                            setProfileModalOpen(true);
                            setOpen(false);
                        }}
                        className="block px-4 py-2.5 text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                    >
                        My Profile
                    </a>
                    {dashboardUrl && (
                        <a href={dashboardUrl} className="block px-4 py-2.5 text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                            Dashboard
                        </a>
                    )}
                    <div className="my-1 border-t border-gray-100 dark:border-edge" />
                    <LogoutButton
                        className="block w-full px-4 py-2.5 text-left text-sm text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft"
                    />
                </div>
            )}

            {profileModalOpen && <MyProfileModal profileUrl={profileUrl} onClose={() => setProfileModalOpen(false)} />}
        </div>
    );
}

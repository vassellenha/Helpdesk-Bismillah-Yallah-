import { useEffect, useRef, useState } from 'react';

export default function UserMenu({ name, title, initials }) {
    const [open, setOpen] = useState(false);
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
            <button type="button" onClick={() => setOpen((v) => !v)} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-700 text-sm font-semibold text-white">
                    {initials}
                </span>
                <span className="hidden text-left sm:block">
                    <span className="block text-sm font-semibold text-gray-900">{name}</span>
                    <span className="block text-xs text-gray-400">{title}</span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
                    <path d="m6 9 6 6 6-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    <a href="#" onClick={(e) => e.preventDefault()} className="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Profil Saya</a>
                    <a href="#" onClick={(e) => e.preventDefault()} className="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Pengaturan</a>
                    <div className="my-1 border-t border-gray-100" />
                    <a href="#" onClick={(e) => e.preventDefault()} className="block px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Keluar</a>
                </div>
            )}
        </div>
    );
}

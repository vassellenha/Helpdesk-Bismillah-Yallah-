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
        <div ref={ref} className="fixed bottom-6 right-6 z-50">
            {open && (
                <div className="mb-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                    <div className="border-b border-gray-100 px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">Pilih Role</p>
                    </div>
                    <ul className="max-h-80 overflow-y-auto py-1">
                        {roles.map((role) => (
                            <li key={role.key}>
                                <a
                                    href={role.url}
                                    className={`flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 ${
                                        role.key === current ? 'bg-blue-50/60' : ''
                                    }`}
                                >
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-bold text-blue-700">
                                        {role.initials}
                                    </span>
                                    <span className="flex-1">
                                        <span className="block font-medium text-gray-900">{role.label}</span>
                                    </span>
                                    {role.key === current && (
                                        <span className="text-xs font-medium text-blue-600">Aktif</span>
                                    )}
                                </a>
                            </li>
                        ))}
                        <li>
                            <a
                                href={portalUrl}
                                className="flex items-center gap-3 border-t border-gray-100 px-4 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-50"
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
                className="flex items-center gap-2 rounded-full bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-blue-800"
            >
                <span aria-hidden="true">⇄</span>
                Switch Role
            </button>
        </div>
    );
}

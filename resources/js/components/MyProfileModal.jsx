import { useEffect, useState } from 'react';
import { apiFetch } from '../lib/api';
import useLockBodyScroll from '../lib/useLockBodyScroll';
import { SkeletonBar } from './Spinner';
import Portal from './Portal';

/**
 * Read-only "My Profile" popup, reused by every role's top nav. Mirrors the
 * "Detail Pengguna" tab from Admin's Kelola Pengguna modal, minus the
 * Edit/Role tabs — lazy-fetches from that role's own /profile endpoint so no
 * page controller needs to embed the full profile shape up front.
 */
export default function MyProfileModal({ profileUrl, onClose }) {
    useLockBodyScroll();
    const [profile, setProfile] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        apiFetch(profileUrl)
            .then(setProfile)
            .catch((e) => setError(e.message || 'Gagal memuat profil.'));
    }, [profileUrl]);

    return (
        <Portal>
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
                <div
                    className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-xl"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-6 py-4">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900 dark:text-ink-1">Profil Saya</h2>
                            <p className="mt-0.5 text-sm text-gray-500 dark:text-ink-2">{profile?.name ?? ' '}</p>
                        </div>
                        <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600" aria-label="Tutup">
                            ✕
                        </button>
                    </div>

                    <div className="overflow-y-auto px-6 py-5">
                        {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}
                        {!profile && !error && (
                            <div>
                                <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-panel-3 p-4">
                                    <SkeletonBar className="h-11 w-11 shrink-0 rounded-full" />
                                    <SkeletonBar className="h-4 w-32" />
                                    <SkeletonBar className="ml-auto h-6 w-20 rounded-full" />
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {Array.from({ length: 12 }).map((_, i) => (
                                        <div key={i} className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3">
                                            <SkeletonBar className="h-2.5 w-20" />
                                            <SkeletonBar className="mt-2 h-3.5 w-28" />
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-4 rounded-lg bg-gray-50 dark:bg-panel-3 p-4">
                                    <SkeletonBar className="h-2.5 w-20" />
                                    <div className="mt-2.5 flex gap-2">
                                        <SkeletonBar className="h-6 w-20 rounded-full" />
                                        <SkeletonBar className="h-6 w-24 rounded-full" />
                                    </div>
                                </div>
                            </div>
                        )}

                        {profile && (
                            <div>
                                <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-panel-3 p-4">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-700 dark:bg-blue-500 text-sm font-semibold text-white">
                                        {profile.initials}
                                    </span>
                                    <span className="font-semibold text-gray-900 dark:text-ink-1">{profile.name}</span>
                                    <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-ok-soft px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-ok-text">
                                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                        {profile.status}
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <Detail label="Nama Lengkap" value={profile.name} />
                                    <Detail label="Email Korporat" value={profile.email} />
                                    <Detail label="Username" value={profile.username} />
                                    <Detail label="NPP" value={profile.nip} />
                                    <Detail label="Alamat" value={profile.address} span />
                                    <Detail label="Nomor Telepon" value={profile.phone} />
                                    <Detail label="Status Akun" value={profile.status} hint={profile.statusReason} />
                                    <Detail label="Jabatan" value={profile.jabatan} span />
                                    <Detail label="Kode Departemen" value={profile.kodeDepartemen} />
                                    <Detail label="Kode Divisi" value={profile.kodeDivisi} />
                                    <Detail label="Kode Proyek" value={profile.kodeProyek} />
                                    <Detail label="Terakhir Login" value={profile.lastLogin} />
                                </div>

                                <div className="mt-4 rounded-lg bg-gray-50 dark:bg-panel-3 p-4">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Role Aktif</p>
                                    <div className="flex flex-wrap gap-2">
                                        {profile.roles.map((r) => (
                                            <span key={r} className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:text-accent-text">{r}</span>
                                        ))}
                                        {profile.roles.length === 0 && <span className="text-sm text-gray-400 dark:text-ink-3">Belum ada role.</span>}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-4">
                        <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </Portal>
    );
}

function Detail({ label, value, span, hint }) {
    return (
        <div className={`rounded-lg bg-gray-50 dark:bg-panel-3 p-3 ${span ? 'col-span-2' : ''}`}>
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-ink-1">{value}</p>
            {hint && <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{hint}</p>}
        </div>
    );
}

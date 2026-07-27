import { useEffect, useState } from 'react';
import { apiFetch } from '../lib/api';

/**
 * Read-only "My Profile" popup, reused by every role's top nav. Mirrors the
 * "Detail Pengguna" tab from Admin's Kelola Pengguna modal, minus the
 * Edit/Role tabs — lazy-fetches from that role's own /profile endpoint so no
 * page controller needs to embed the full profile shape up front.
 */
export default function MyProfileModal({ profileUrl, onClose }) {
    const [profile, setProfile] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        apiFetch(profileUrl)
            .then(setProfile)
            .catch((e) => setError(e.message || 'Gagal memuat profil.'));
    }, [profileUrl]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div
                className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">My Profile</h2>
                        <p className="mt-0.5 text-sm text-gray-500">{profile?.name ?? ' '}</p>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">
                        ✕
                    </button>
                </div>

                <div className="overflow-y-auto px-6 py-5">
                    {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
                    {!profile && !error && <p className="py-8 text-center text-sm text-gray-400">Memuat profil…</p>}

                    {profile && (
                        <div>
                            <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-50 p-4">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-700 text-sm font-semibold text-white">
                                    {profile.initials}
                                </span>
                                <span className="font-semibold text-gray-900">{profile.name}</span>
                                <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                    {profile.status}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Detail label="Nama Lengkap" value={profile.name} />
                                <Detail label="Unit Kerja" value={profile.unit} />
                                <Detail label="NIP" value={profile.nip} />
                                <Detail label="Email Korporat" value={profile.email} />
                                <Detail label="Status Akun" value={profile.status} />
                                <Detail label="Nomor WhatsApp" value={profile.whatsapp} />
                                <Detail label="Bergabung" value={profile.joinedAt} />
                                <Detail label="Terakhir Login" value={profile.lastLogin} />
                            </div>

                            <div className="mt-4 rounded-lg bg-gray-50 p-4">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Role Aktif</p>
                                <div className="flex flex-wrap gap-2">
                                    {profile.roles.map((r) => (
                                        <span key={r} className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{r}</span>
                                    ))}
                                    {profile.roles.length === 0 && <span className="text-sm text-gray-400">Belum ada role.</span>}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    );
}

function Detail({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900">{value}</p>
        </div>
    );
}

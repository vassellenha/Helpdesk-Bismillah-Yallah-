import { useEffect, useMemo, useRef, useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import { apiFetch } from '../../lib/api';

export default function RoleManagerModal({ roles, onClose, onAddRole, onRoleSaved }) {
    const [search, setSearch] = useState('');
    const [menu, setMenu] = useState(null); // { role, top, left }
    const [editingRole, setEditingRole] = useState(null);
    const [error, setError] = useState('');
    const menuRef = useRef(null);

    useEffect(() => {
        if (!menu) return;
        function onClickOutside(e) {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenu(null);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [menu]);

    const filtered = useMemo(
        () => roles.filter((r) => r.name.toLowerCase().includes(search.toLowerCase())),
        [roles, search]
    );

    function openMenu(e, role) {
        if (menu?.role.id === role.id) {
            setMenu(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        setMenu({ role, top: rect.bottom + 4, left: rect.right - 176 });
    }

    async function toggleStatus(role) {
        setMenu(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/roles/${role.id}/toggle`, { method: 'POST' });
            onRoleSaved(updated);
        } catch (e) {
            setError(e.message || 'Gagal memperbarui status role.');
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-2xl">
            <ModalHeader title="Manajemen Role" subtitle="Role utama, role support spesifik, dan role kustom untuk Helpdesk 2.0." onClose={onClose} />

            <div className="overflow-y-auto px-6 py-4">
                <div className="mb-4 flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-xs text-blue-900">
                    <span className="mt-0.5 h-full w-1 shrink-0 rounded bg-blue-600" />
                    <p>
                        <strong className="block">Struktur role</strong>
                        Role Utama adalah level akses besar (System Role, tidak dapat dihapus). Role Support Spesifik diturunkan otomatis dari
                        assignment Support di Service Catalog. Role Kustom dibuat administrator untuk kebutuhan khusus organisasi.
                    </p>
                </div>

                {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

                <div className="mb-4 flex items-center justify-between gap-3">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama role"
                        className="w-full max-w-xs rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <button
                        onClick={onAddRole}
                        className="shrink-0 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                    >
                        + Tambah Role
                    </button>
                </div>

                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th className="px-3 py-2">Nama Role</th>
                            <th className="px-3 py-2">Jumlah Pengguna</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {filtered.map((r) => (
                            <tr key={r.id}>
                                <td className="px-3 py-3">
                                    <p className="font-semibold text-gray-900">{r.name}</p>
                                    <span className="mt-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{r.type}</span>
                                </td>
                                <td className="px-3 py-3 text-gray-700">{r.user_count ?? 0}</td>
                                <td className="px-3 py-3">
                                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${r.status === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                        <span className={`h-1.5 w-1.5 rounded-full ${r.status === 'Aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                        {r.status}
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-right">
                                    <button onClick={(e) => openMenu(e, r)} className="rounded-full border border-gray-200 px-2.5 py-1 text-gray-500 hover:bg-gray-100">
                                        •••
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {menu && (
                <div ref={menuRef} style={{ top: menu.top, left: menu.left }} className="fixed z-50 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg">
                    <button onClick={() => { setEditingRole(menu.role); setMenu(null); }} className="block w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        Edit
                    </button>
                    <button onClick={() => toggleStatus(menu.role)} className="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">
                        {menu.role.status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan'}
                    </button>
                </div>
            )}

            {editingRole && (
                <EditRoleModal role={editingRole} onClose={() => setEditingRole(null)} onSaved={(saved) => { onRoleSaved(saved); setEditingRole(null); }} />
            )}

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Tutup
                </button>
            </ModalFooter>
        </Modal>
    );
}

function EditRoleModal({ role, onClose, onSaved }) {
    const [name, setName] = useState(role.name);
    const [status, setStatus] = useState(role.status_raw);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function save() {
        setError('');
        setSaving(true);
        try {
            const updated = await apiFetch(`/admin/roles/${role.id}`, { method: 'PUT', body: JSON.stringify({ name, status }) });
            onSaved(updated);
        } catch (e) {
            setError(e.message || 'Gagal menyimpan role.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="text-base font-bold text-gray-900">Edit Role</h3>
                </div>
                <div className="space-y-4 px-5 py-4">
                    {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Nama Role</label>
                        <input value={name} onChange={(e) => setName(e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700">Status</label>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <button onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-white">Batal</button>
                    <button onClick={save} disabled={saving || !name} className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50">
                        {saving ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

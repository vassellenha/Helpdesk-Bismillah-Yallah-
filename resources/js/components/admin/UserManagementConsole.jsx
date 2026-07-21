import { useMemo, useState } from 'react';
import RoleManagerModal from './RoleManagerModal';
import AddRoleWizard from './AddRoleWizard';
import AddUserModal from './AddUserModal';
import ManageUserModal from './ManageUserModal';
import { apiFetch } from '../../lib/api';

export default function UserManagementConsole({ users: initialUsers, roles: initialRoles, permissionModules, permissionActions, unitOrganisasi }) {
    const [users, setUsers] = useState(initialUsers);
    const [roles, setRoles] = useState(initialRoles);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('Semua Status');
    const [roleFilter, setRoleFilter] = useState('Semua Role');
    const [unitFilter, setUnitFilter] = useState('Semua Unit Kerja');
    const [openMenuId, setOpenMenuId] = useState(null);
    const [modal, setModal] = useState(null); // 'role' | 'addRole' | 'addUser' | { type: 'manageUser', user }
    const [error, setError] = useState('');

    const roleNames = useMemo(() => Array.from(new Set(users.flatMap((u) => u.roles))), [users]);

    const filtered = useMemo(() => {
        return users.filter((u) => {
            const q = search.toLowerCase();
            const matchesSearch =
                q === '' || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.unit.toLowerCase().includes(q);
            const matchesStatus = statusFilter === 'Semua Status' || u.status === statusFilter;
            const matchesRole = roleFilter === 'Semua Role' || u.roles.includes(roleFilter);
            const matchesUnit = unitFilter === 'Semua Unit Kerja' || u.unit === unitFilter;
            return matchesSearch && matchesStatus && matchesRole && matchesUnit;
        });
    }, [users, search, statusFilter, roleFilter, unitFilter]);

    async function toggleUserStatus(id) {
        setOpenMenuId(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/users/${id}/toggle`, { method: 'POST' });
            setUsers((prev) => prev.map((u) => (u.id === id ? updated : u)));
        } catch (e) {
            setError(e.message || 'Gagal memperbarui status user.');
        }
    }

    function saveUser(updated) {
        setUsers((prev) => prev.map((u) => (u.id === updated.id ? updated : u)));
        setModal(null);
    }

    function addUser(created) {
        setUsers((prev) => [created, ...prev]);
        setModal(null);
    }

    function upsertRole(saved) {
        setRoles((prev) => (prev.some((r) => r.id === saved.id) ? prev.map((r) => (r.id === saved.id ? saved : r)) : [...prev, saved]));
    }

    return (
        <div>
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900">User &amp; Role Management</h1>
                    <p className="mt-1 text-sm text-gray-500">Kelola pengguna aplikasi, multi-role, akses autentikasi, dan penugasan support.</p>
                </div>
                <div className="flex shrink-0 gap-2">
                    <button onClick={() => setModal('role')} className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        ⚙ Kelola Role
                    </button>
                    <button onClick={() => setModal('addUser')} className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        + Tambah User
                    </button>
                </div>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Stat label="TOTAL USER" value={users.length} color="text-blue-600" bg="bg-blue-50" />
                <Stat label="USER AKTIF" value={users.filter((u) => u.status === 'Aktif').length} color="text-emerald-600" bg="bg-emerald-50" />
                <Stat label="USER NONAKTIF" value={users.filter((u) => u.status === 'Nonaktif').length} color="text-gray-500" bg="bg-gray-100" />
                <Stat label="TOTAL ROLE" value={roles.length} color="text-amber-600" bg="bg-amber-50" />
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama, email, atau unit kerja"
                        className="w-full max-w-sm rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option>Semua Status</option>
                            <option>Aktif</option>
                            <option>Nonaktif</option>
                        </select>
                        <select value={roleFilter} onChange={(e) => setRoleFilter(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option>Semua Role</option>
                            {roleNames.map((r) => (
                                <option key={r}>{r}</option>
                            ))}
                        </select>
                        <select value={unitFilter} onChange={(e) => setUnitFilter(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
                            <option>Semua Unit Kerja</option>
                            {unitOrganisasi.map((u) => (
                                <option key={u}>{u}</option>
                            ))}
                        </select>
                        <button
                            onClick={() => { setSearch(''); setStatusFilter('Semua Status'); setRoleFilter('Semua Role'); setUnitFilter('Semua Unit Kerja'); }}
                            className="text-sm font-medium text-blue-700 hover:text-blue-800"
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400">Menampilkan {filtered.length} dari {users.length} pengguna</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <th className="px-4 py-3">Nama</th>
                                <th className="px-4 py-3">NIP</th>
                                <th className="px-4 py-3">Email / WhatsApp</th>
                                <th className="px-4 py-3">Unit Kerja</th>
                                <th className="px-4 py-3">Jabatan</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Terakhir Login</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {filtered.map((u) => (
                                <tr key={u.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 font-semibold text-gray-900">{u.name}</td>
                                    <td className="px-4 py-3 text-gray-600">{u.nip}</td>
                                    <td className="px-4 py-3">
                                        <p className="text-gray-700">{u.email}</p>
                                        <p className="text-xs text-gray-400">{u.whatsapp}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{u.unit}</td>
                                    <td className="px-4 py-3 text-gray-600">{u.jabatan}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.map((r) => (
                                                <span key={r} className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{r}</span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${u.status === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${u.status === 'Aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {u.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{u.last_login}</td>
                                    <td className="relative px-4 py-3 text-right">
                                        <button
                                            onClick={() => setOpenMenuId(openMenuId === u.id ? null : u.id)}
                                            className="rounded-full border border-gray-200 px-2.5 py-1 text-gray-500 hover:bg-gray-100"
                                        >
                                            •••
                                        </button>
                                        {openMenuId === u.id && (
                                            <div className="absolute right-4 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg">
                                                <button onClick={() => { setModal({ type: 'manageUser', user: u }); setOpenMenuId(null); }} className="block w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                    Edit
                                                </button>
                                                <button onClick={() => toggleUserStatus(u.id)} className="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                                    {u.status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan'}
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-gray-400">Tidak ada pengguna yang cocok dengan filter.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {modal === 'role' && (
                <RoleManagerModal roles={roles} onClose={() => setModal(null)} onAddRole={() => setModal('addRole')} onRoleSaved={upsertRole} />
            )}
            {modal === 'addRole' && (
                <AddRoleWizard
                    unitOrganisasi={unitOrganisasi}
                    modules={permissionModules}
                    actions={permissionActions}
                    onClose={() => setModal(null)}
                    onSave={(role) => { upsertRole(role); setModal('role'); }}
                />
            )}
            {modal === 'addUser' && <AddUserModal onClose={() => setModal(null)} onSave={addUser} />}
            {modal?.type === 'manageUser' && (
                <ManageUserModal user={modal.user} roles={roles} onClose={() => setModal(null)} onSave={saveUser} />
            )}
        </div>
    );
}

function Stat({ label, value, color, bg }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2 w-2 rounded-full bg-current" />
            </span>
            <p className="mt-2 text-xl font-bold text-gray-900">{value}</p>
            <p className="text-xs font-medium text-gray-400">{label}</p>
        </div>
    );
}

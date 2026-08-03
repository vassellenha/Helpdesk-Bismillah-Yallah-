import { useMemo, useState } from 'react';
import RoleManagerModal from './RoleManagerModal';
import AddRoleWizard from './AddRoleWizard';
import AddUserModal from './AddUserModal';
import ManageUserModal from './ManageUserModal';
import RowActionMenu, { menuPositionFor } from '../RowActionMenu';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

const ICON_EDIT = 'M12 20h9 M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z';
const ICON_DEACTIVATE = 'M12 2v10 M18.4 5.6a8 8 0 1 1-12.8 0';
const ICON_ACTIVATE = 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9';

// Language-independent filter sentinels. A translated label here would be
// compared against real status/role/unit values and match nothing.
const ALL_STATUS = '__all_status';
const ALL_ROLE = '__all_role';
const ALL_UNIT = '__all_unit';

export default function UserManagementConsole({ users: initialUsers, roles: initialRoles, permissionModules, permissionActions, unitOrganisasi }) {
    const [users, setUsers] = useState(initialUsers);
    const [roles, setRoles] = useState(initialRoles);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState(ALL_STATUS);
    const [roleFilter, setRoleFilter] = useState(ALL_ROLE);
    const [unitFilter, setUnitFilter] = useState(ALL_UNIT);
    const [menu, setMenu] = useState(null); // { user, top, left }
    const [modal, setModal] = useState(null); // 'role' | 'addRole' | 'addUser' | { type: 'manageUser', user }
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [syncing, setSyncing] = useState(false);
    const [syncResult, setSyncResult] = useState(null);

    function openMenu(e, user) {
        if (menu?.user.id === user.id) {
            setMenu(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        // The employment-status note adds a third line, so the flip-above
        // calculation needs the taller estimate or the menu clips off-screen.
        const height = user.employment_status === 'Nonaktif' ? 150 : 96;
        setMenu({ user, ...menuPositionFor(rect, { height }) });
    }

    const roleNames = useMemo(() => Array.from(new Set(users.flatMap((u) => u.roles))), [users]);

    const statusOptions = useMemo(() => [[ALL_STATUS, trans('admin.users.all_status')], ['Aktif', trans('admin.common.active')], ['Nonaktif', trans('admin.common.inactive')]].map(([value, label]) => ({ value, label })), []);
    const roleOptions = useMemo(() => [{ value: ALL_ROLE, label: trans('admin.users.all_role') }, ...roleNames.map((r) => ({ value: r, label: r }))], [roleNames]);
    const unitOptions = useMemo(() => [{ value: ALL_UNIT, label: trans('admin.users.all_unit') }, ...unitOrganisasi.map((u) => ({ value: u, label: u }))], [unitOrganisasi]);

    const filtered = useMemo(() => {
        return users.filter((u) => {
            const q = search.toLowerCase();
            const matchesSearch =
                q === '' || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || (u.unit ?? '').toLowerCase().includes(q);
            const matchesStatus = statusFilter === ALL_STATUS || u.status === statusFilter;
            const matchesRole = roleFilter === ALL_ROLE || u.roles.includes(roleFilter);
            const matchesUnit = unitFilter === ALL_UNIT || u.unit === unitFilter;
            return matchesSearch && matchesStatus && matchesRole && matchesUnit;
        });
    }, [users, search, statusFilter, roleFilter, unitFilter]);

    async function toggleUserStatus(id) {
        setMenu(null);
        setError('');
        setNotice('');
        try {
            const updated = await apiFetch(`/admin/users/${id}/toggle`, { method: 'POST' });
            setUsers((prev) => prev.map((u) => (u.id === id ? updated : u)));

            // Enabling access on someone the company API reports as no longer
            // employed succeeds but changes nothing visible — say so, otherwise
            // the row just stays "Nonaktif" and the click looks broken.
            if (updated.helpdesk_access === 'enabled' && updated.status === 'Nonaktif') {
                setNotice(
                    `Akses helpdesk ${updated.name} sudah diaktifkan, tetapi akunnya tetap nonaktif karena ${updated.status_reason.toLowerCase()}. ` +
                    'Status kepegawaian hanya bisa berubah dari API perusahaan.'
                );
            }
        } catch (e) {
            setError(e.message || trans('admin.users.status_failed'));
        }
    }

    async function syncEmployees() {
        setError('');
        setSyncResult(null);
        setSyncing(true);
        try {
            const { summary, users: fresh } = await apiFetch('/admin/users/sync', { method: 'POST' });
            setUsers(fresh);
            setSyncResult(summary);
        } catch (e) {
            setError(e.message || trans('admin.users.sync_failed'));
        } finally {
            setSyncing(false);
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
                    <h1 className="text-3xl font-extrabold text-gray-900 dark:text-ink-1">{trans('admin.users.title')}</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">{trans('admin.users.subtitle')}</p>
                </div>
                <div className="flex shrink-0 gap-2">
                    <button
                        onClick={syncEmployees}
                        disabled={syncing}
                        title={trans('admin.users.sync_title')}
                        className="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            className={`h-4 w-4 ${syncing ? 'animate-spin' : ''}`}
                            aria-hidden="true"
                        >
                            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                            <path d="M21 3v6h-6" />
                        </svg>
                        {syncing ? trans('admin.users.syncing') : trans('admin.users.sync')}
                    </button>
                    <button onClick={() => setModal('role')} className="rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        ⚙ Kelola Role
                    </button>
                    <button onClick={() => setModal('addUser')} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                        {trans('admin.users.add_user')}
                    </button>
                </div>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            {notice && (
                <div className="mb-4 flex items-start justify-between gap-3 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-sm text-amber-800 dark:text-warn-text">
                    <p>{notice}</p>
                    <button
                        onClick={() => setNotice('')}
                        className="shrink-0 rounded p-0.5 text-amber-600 dark:text-warn-text hover:bg-amber-100 dark:hover:bg-panel-hover"
                        aria-label={trans('admin.common.close')}
                    >
                        ✕
                    </button>
                </div>
            )}

            {syncResult && (
                <div className="mb-4 rounded-lg bg-emerald-50 dark:bg-ok-soft p-3 text-sm text-emerald-800 dark:text-ok-text">
                    <div className="flex items-start justify-between gap-3">
                        <p>
                            <span className="font-semibold">{trans('admin.users.sync_done')}</span>{' '}
                            {syncResult.fetched} data diterima — {syncResult.created} dibuat, {syncResult.updated} diperbarui,{' '}
                            {syncResult.unchanged} tidak berubah
                            {syncResult.deactivated > 0 && <>, {syncResult.deactivated} dinonaktifkan</>}
                            {syncResult.skipped.length > 0 && <>, {syncResult.skipped.length} dilewati</>}.
                        </p>
                        <button
                            onClick={() => setSyncResult(null)}
                            className="shrink-0 rounded p-0.5 text-emerald-600 dark:text-ok-text hover:bg-emerald-100 dark:hover:bg-panel-hover"
                            aria-label={trans('admin.common.close')}
                        >
                            ✕
                        </button>
                    </div>
                    {syncResult.changes.length > 0 && (
                        <ul className="mt-2 list-inside list-disc space-y-0.5 text-xs">
                            {syncResult.changes.map((c) => (
                                <li key={c.name}>
                                    <span className="font-medium">{c.name}</span> — {c.fields.join(', ')}
                                </li>
                            ))}
                        </ul>
                    )}
                    {syncResult.skipped.length > 0 && (
                        <ul className="mt-2 list-inside list-disc space-y-0.5 text-xs">
                            {syncResult.skipped.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    )}
                    {syncResult.kept_empty > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            {trans('admin.users.kept_empty_note', { count: syncResult.kept_empty })}
                        </p>
                    )}
                    {syncResult.kept_admin_override > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            {syncResult.kept_admin_override} field dipertahankan karena pernah diedit manual lewat Edit
                            Pengguna — sync tidak akan menimpanya lagi selama field itu masih ditandai sebagai perubahan Admin.
                        </p>
                    )}
                    {(syncResult.not_in_source ?? []).length > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            Tidak ada di data API, jadi dibiarkan apa adanya:{' '}
                            <span className="font-medium">{syncResult.not_in_source.join(', ')}</span>.
                            Akun yang dibuat manual lewat Admin tidak ikut disinkronkan.
                        </p>
                    )}
                    {(syncResult.key_mismatch ?? []).length > 0 && (
                        <div className="mt-2 rounded-lg bg-amber-50 dark:bg-warn-soft p-2.5 text-xs text-amber-800 dark:text-warn-text">
                            <p className="font-semibold">
                                {syncResult.key_mismatch.length} pegawai NIP-nya berbeda antara API dan helpdesk.
                            </p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {syncResult.key_mismatch.map((m) => <li key={m}>{m}</li>)}
                            </ul>
                            <p className="mt-1.5">
                                Datanya tetap tersinkron lewat email, tapi NIP-nya sengaja tidak diubah — NIP adalah kunci
                                identitas, jadi perbaikannya harus diputuskan manusia.
                            </p>
                        </div>
                    )}
                    <p className="mt-2 text-xs opacity-80">Tercatat di Audit Trail — modul “Integrasi”.</p>
                </div>
            )}

            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Stat label={trans('admin.users.stat_total_user')} value={users.length} color="text-blue-600 dark:text-accent-text" bg="bg-blue-50 dark:bg-accent-soft" />
                <Stat label={trans('admin.users.stat_active')} value={users.filter((u) => u.status === 'Aktif').length} color="text-emerald-600 dark:text-ok-text" bg="bg-emerald-50 dark:bg-ok-soft" />
                <Stat label={trans('admin.users.stat_inactive')} value={users.filter((u) => u.status === 'Nonaktif').length} color="text-gray-500 dark:text-ink-2" bg="bg-gray-100 dark:bg-panel-3" />
                <Stat label={trans('admin.users.stat_total_role')} value={roles.length} color="text-amber-600 dark:text-warn-text" bg="bg-amber-50 dark:bg-warn-soft" />
            </div>

            <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={trans('admin.users.search')}
                        className="w-full max-w-sm rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <SelectMenu value={statusFilter} onChange={setStatusFilter} options={statusOptions} />
                        <SelectMenu value={roleFilter} onChange={setRoleFilter} options={roleOptions} />
                        <SelectMenu value={unitFilter} onChange={setUnitFilter} options={unitOptions} />
                        <button
                            onClick={() => { setSearch(''); setStatusFilter(ALL_STATUS); setRoleFilter(ALL_ROLE); setUnitFilter(ALL_UNIT); }}
                            className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300"
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">{trans('admin.users.showing', { shown: filtered.length, total: users.length })}</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3">{trans('admin.users.col_name')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_nip')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_contact')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_unit')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_jabatan')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_role')}</th>
                                <th className="px-4 py-3">{trans('admin.common.status')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_last_login')}</th>
                                <th className="px-4 py-3 text-right">{trans('admin.common.action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                            {filtered.map((u) => (
                                <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    <td className="px-4 py-3 font-semibold text-gray-900 dark:text-ink-1">{u.name}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{u.nip}</td>
                                    <td className="px-4 py-3">
                                        <p className="text-gray-700 dark:text-ink-2">{u.email}</p>
                                        <p className="text-xs text-gray-400 dark:text-ink-3">{u.phone}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{u.unit}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{u.jabatan}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.map((r) => (
                                                <span key={r} className="rounded-full bg-blue-50 dark:bg-accent-soft px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-accent-text">{r}</span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${u.status === 'Aktif' ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${u.status === 'Aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {u.status === 'Aktif' ? trans('admin.common.active') : trans('admin.common.inactive')}
                                        </span>
                                        {u.status_reason && (
                                            <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">{u.status_reason}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-ink-2">{u.last_login}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={(e) => openMenu(e, u)}
                                            className="rounded-full border border-gray-200 dark:border-edge-strong px-2.5 py-1 text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover"
                                        >
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada pengguna yang cocok dengan filter.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {menu && (
                <RowActionMenu
                    anchor={menu}
                    onClose={() => setMenu(null)}
                    items={[
                        { label: trans('admin.users.edit'), icon: ICON_EDIT, onClick: () => setModal({ type: 'manageUser', user: menu.user }) },
                        {
                            // Toggles helpdesk access only — employment status comes
                            // from the company API and cannot be changed from here.
                            label: menu.user.helpdesk_access === 'enabled' ? trans('admin.users.disable_access') : trans('admin.users.enable_access'),
                            icon: menu.user.helpdesk_access === 'enabled' ? ICON_DEACTIVATE : ICON_ACTIVATE,
                            tone: menu.user.helpdesk_access === 'enabled' ? 'danger' : 'success',
                            note: menu.user.employment_status === 'Nonaktif'
                                ? trans('admin.users.account_locked_note')
                                : null,
                            divider: true,
                            onClick: () => toggleUserStatus(menu.user.id),
                        },
                    ]}
                />
            )}

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
            {modal === 'addUser' && <AddUserModal roles={roles} onClose={() => setModal(null)} onSave={addUser} />}
            {modal?.type === 'manageUser' && (
                <ManageUserModal user={modal.user} roles={roles} onClose={() => setModal(null)} onSave={saveUser} />
            )}
        </div>
    );
}

function Stat({ label, value, color, bg }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm">
            <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2 w-2 rounded-full bg-current" />
            </span>
            <p className="mt-2 text-xl font-bold text-gray-900 dark:text-ink-1">{value}</p>
            <p className="text-xs font-medium text-gray-400 dark:text-ink-3">{label}</p>
        </div>
    );
}

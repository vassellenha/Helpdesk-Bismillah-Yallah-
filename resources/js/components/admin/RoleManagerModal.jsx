import { useMemo, useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import RowActionMenu, { menuPositionFor } from '../RowActionMenu';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

const ICON_EDIT = 'M12 20h9 M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z';
const ICON_DEACTIVATE = 'M12 2v10 M18.4 5.6a8 8 0 1 1-12.8 0';
const ICON_ACTIVATE = 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9';

export default function RoleManagerModal({ roles, onClose, onAddRole, onRoleSaved }) {
    const [search, setSearch] = useState('');
    const [menu, setMenu] = useState(null); // { role, top, left }
    const [editingRole, setEditingRole] = useState(null);
    const [error, setError] = useState('');

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
        setMenu({ role, ...menuPositionFor(rect) });
    }

    async function toggleStatus(role) {
        setMenu(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/roles/${role.id}/toggle`, { method: 'POST' });
            onRoleSaved(updated);
        } catch (e) {
            setError(e.message || trans('admin.roles.status_failed'));
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-2xl">
            <ModalHeader title={trans('admin.roles.title')} subtitle={trans('admin.roles.subtitle')} onClose={onClose} />

            <div className="overflow-y-auto px-6 py-4">
                <div className="mb-4 flex items-start gap-2 rounded-lg bg-blue-50 dark:bg-accent-soft p-3 text-xs text-blue-900 dark:text-accent-text">
                    <span className="mt-0.5 h-full w-1 shrink-0 rounded bg-blue-600 dark:bg-blue-500" />
                    <p>
                        <strong className="block">{trans('admin.roles.col_structure')}</strong>
                        {trans('admin.roles.structure_body')}
                    </p>
                </div>

                {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                <div className="mb-4 flex items-center justify-between gap-3">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={trans('admin.roles.search')}
                        className="w-full max-w-xs rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <button
                        onClick={onAddRole}
                        className="shrink-0 rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400"
                    >
                        + Tambah Role
                    </button>
                </div>

                <div className="w-full overflow-x-auto"><table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                    <thead>
                        <tr className="bg-gray-50 dark:bg-panel-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-3 py-2">{trans('admin.roles.col_name')}</th>
                            <th className="px-3 py-2">{trans('admin.roles.col_users')}</th>
                            <th className="px-3 py-2">{trans('admin.common.status')}</th>
                            <th className="px-3 py-2 text-right">{trans('admin.common.action')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                        {filtered.map((r) => (
                            <tr key={r.id} className="dark:even:bg-white/[0.03]">
                                <td className="px-3 py-3">
                                    <p className="font-semibold text-gray-900 dark:text-ink-1">{r.name}</p>
                                    <span className="mt-1 inline-block rounded-full bg-gray-100 dark:bg-panel-3 px-2 py-0.5 text-xs text-gray-500 dark:text-ink-2">{r.type}</span>
                                </td>
                                <td className="px-3 py-3 text-gray-700 dark:text-ink-2">{r.user_count ?? 0}</td>
                                <td className="px-3 py-3">
                                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${r.status === 'Aktif' ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2'}`}>
                                        <span className={`h-1.5 w-1.5 rounded-full ${r.status === 'Aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                        {r.status === 'Aktif' ? trans('admin.common.active') : trans('admin.common.inactive')}
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-right">
                                    <button onClick={(e) => openMenu(e, r)} className="rounded-full border border-gray-200 dark:border-edge-strong px-2.5 py-1 text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">
                                        •••
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table></div>
            </div>

            {menu && (
                <RowActionMenu
                    anchor={menu}
                    onClose={() => setMenu(null)}
                    items={[
                        { label: trans('admin.roles.edit'), icon: ICON_EDIT, onClick: () => setEditingRole(menu.role) },
                        {
                            label: menu.role.status === 'Aktif' ? trans('admin.common.deactivate') : trans('admin.common.activate'),
                            icon: menu.role.status === 'Aktif' ? ICON_DEACTIVATE : ICON_ACTIVATE,
                            tone: menu.role.status === 'Aktif' ? 'danger' : 'success',
                            divider: true,
                            onClick: () => toggleStatus(menu.role),
                        },
                    ]}
                />
            )}

            {editingRole && (
                <EditRoleModal role={editingRole} onClose={() => setEditingRole(null)} onSaved={(saved) => { onRoleSaved(saved); setEditingRole(null); }} />
            )}

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">
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
            setError(e.message || trans('admin.roles.save_failed'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onClick={onClose}>
            <div className="liquid-glass-dense w-full max-w-sm rounded-2xl shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="border-b border-gray-100 dark:border-edge px-5 py-4">
                    <h3 className="text-base font-bold text-gray-900 dark:text-ink-1">{trans('admin.roles.edit')}</h3>
                </div>
                <div className="space-y-4 px-5 py-4">
                    {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-ink-2">{trans('admin.roles.col_name')}</label>
                        <input value={name} onChange={(e) => setName(e.target.value)} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-ink-2">{trans('admin.common.status')}</label>
                        <SelectMenu
                            value={status}
                            onChange={setStatus}
                            options={[{ value: 'active', label: trans('admin.common.active') }, { value: 'inactive', label: trans('admin.common.inactive') }]}
                        />
                    </div>
                </div>
                <div className="flex justify-end gap-2 border-t border-gray-100 dark:border-edge px-5 py-4">
                    <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-4 py-2 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.cancel')}</button>
                    <button onClick={save} disabled={saving || !name} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                        {saving ? trans('admin.common.saving') : trans('admin.common.save')}
                    </button>
                </div>
            </div>
        </div>
    );
}

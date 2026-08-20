import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

// Stable keys, not labels: the active tab is compared in six places, so a
// translated string here would silently break tab switching in English.
const TABS = ['detail', 'edit', 'roles'];

const EDIT_FIELDS = [
    ['name', 'full_name'],
    ['nip', 'nip'],
    ['email', 'email'],
    ['username', 'username', 'username_note'],
    ['address', 'address'],
    ['phone', 'phone'],
    ['unit', 'unit'],
    ['jabatan', 'jabatan'],
    ['kode_departemen', 'kode_departemen'],
    ['kode_divisi', 'kode_divisi'],
    ['kode_proyek', 'kode_proyek'],
];

const ROLE_DESCRIPTIONS = {
    Requester: 'Membuat & memantau tiket sendiri, mengakses Knowledge Base, memberi rating/feedback.',
    Approver: 'Meninjau & memutuskan tiket yang membutuhkan persetujuan sebelum diteruskan ke Support.',
    'Support IT': 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Tim IT.',
    'Support BPO': 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Business Process Owner (unit bisnis).',
    'Team Lead': 'Mengawasi layanan dan memantau seluruh tiket pada layanan yang menjadi tanggung jawabnya.',
    Administrator: 'Akses konfigurasi penuh: user, role, SLA, service catalog, kategori, approval matrix, audit, integrasi.',
    'Knowledge Administrator': 'Mengelola artikel, FAQ, approval knowledge, AI knowledge training, dan analytics.',
};

export default function ManageUserModal({ user, roles, onClose, onSave }) {
    const [tab, setTab] = useState('detail');
    const [form, setForm] = useState({
        name: user.name, nip: user.nip, email: user.email, username: user.username,
        phone: user.phone, address: dash(user.address), unit: user.unit,
        jabatan: user.jabatan, kode_departemen: dash(user.kode_departemen),
        kode_divisi: dash(user.kode_divisi), kode_proyek: dash(user.kode_proyek),
        helpdesk_access: user.helpdesk_access ?? 'enabled',
    });
    const [roleIds, setRoleIds] = useState(user.role_ids ?? []);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function toggleRole(id) {
        setRoleIds((prev) => (prev.includes(id) ? prev.filter((r) => r !== id) : [...prev, id]));
    }

    async function saveProfile() {
        setError('');
        setSaving(true);
        try {
            const updated = await apiFetch(`/admin/users/${user.id}`, { method: 'PUT', body: JSON.stringify(form) });
            onSave(updated);
        } catch (e) {
            setError(e.message || trans('admin.user_form.save_failed'));
        } finally {
            setSaving(false);
        }
    }

    async function saveRoles() {
        setError('');
        setSaving(true);
        try {
            const updated = await apiFetch(`/admin/users/${user.id}/roles`, { method: 'PATCH', body: JSON.stringify({ role_ids: roleIds }) });
            onSave(updated);
        } catch (e) {
            setError(e.message || trans('admin.user_form.role_failed'));
        } finally {
            setSaving(false);
        }
    }

    function save() {
        if (tab === 'edit') return saveProfile();
        if (tab === 'roles') return saveRoles();
        onClose();
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-2xl">
            <ModalHeader title={trans('admin.user_form.manage_title')} subtitle={user.name} onClose={onClose} />

            <div className="overflow-y-auto px-6 py-5">
                {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-panel-3 p-4">
                    <span className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-700 dark:bg-blue-500 text-sm font-semibold text-white">
                        {initials(user.name)}
                    </span>
                    <span className="font-semibold text-gray-900 dark:text-ink-1">{user.name}</span>
                    <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-ok-soft px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-ok-text">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        {user.status === 'Aktif' ? trans('admin.common.active') : trans('admin.common.inactive')}
                    </span>
                </div>

                <div className="mb-5 flex gap-1 rounded-lg bg-gray-100 dark:bg-panel-3 p-1">
                    {TABS.map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium ${
                                tab === t ? 'bg-white dark:bg-panel-2 text-gray-900 dark:text-ink-1 shadow-sm' : 'text-gray-500 dark:text-ink-2 hover:text-gray-700 dark:hover:text-ink-1'
                            }`}
                        >
                            {trans(`admin.user_form.tab_${t}`)}
                        </button>
                    ))}
                </div>

                {tab === 'detail' && (
                    <div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Detail label={trans('admin.user_form.full_name')} value={user.name} />
                            <Detail label={trans('admin.user_form.email')} value={user.email} />
                            <Detail label={trans('admin.user_form.username')} value={user.username} />
                            <Detail label={trans('admin.user_form.nip')} value={user.nip} />
                            <Detail label={trans('admin.user_form.address')} value={user.address} span />
                            <Detail label={trans('admin.user_form.phone')} value={user.phone} />
                            <Detail label={trans('admin.user_form.employment_status')} value={user.employment_status} hint={trans('admin.user_form.from_api')} />
                            <Detail label={trans('admin.user_form.helpdesk_access')} value={user.helpdesk_access === 'enabled' ? trans('admin.common.active') : trans('admin.common.inactive')} hint={trans('admin.user_form.set_by_admin')} />
                            <Detail label={trans('admin.user_form.jabatan')} value={user.jabatan} />
                            <Detail label={trans('admin.user_form.unit')} value={user.unit} />
                            <Detail label={trans('admin.user_form.kode_departemen')} value={user.kode_departemen_display} />
                            <Detail label={trans('admin.user_form.kode_divisi')} value={user.kode_divisi_display} />
                            <Detail label={trans('admin.user_form.kode_proyek')} value={user.kode_proyek} />
                            <Detail label={trans('admin.user_form.last_login')} value={user.last_login} />
                        </div>
                        <div className="mt-4 rounded-lg bg-gray-50 dark:bg-panel-3 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('admin.user_form.active_roles')}</p>
                            <div className="flex flex-wrap gap-2">
                                {user.roles.map((r) => (
                                    <span key={r} className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:text-accent-text">{r}</span>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {tab === 'edit' && (
                    <div className="space-y-4">
                        {EDIT_FIELDS.map(([key, k, hintKey]) => (
                            <Field key={key} label={trans(`admin.user_form.${k}`)}>
                                <input
                                    value={form[key] ?? ''}
                                    onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                                    className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                                />
                                {hintKey && <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">{trans(`admin.user_form.${hintKey}`)}</p>}
                            </Field>
                        ))}
                        <Field label={trans('admin.user_form.helpdesk_access')}>
                            <SelectMenu
                                value={form.helpdesk_access}
                                onChange={(v) => setForm({ ...form, helpdesk_access: v })}
                                options={[{ value: 'enabled', label: trans('admin.common.active') }, { value: 'disabled', label: trans('admin.common.inactive') }]}
                            />
                            <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">
                                {trans('admin.user_form.employment_note', { status: user.employment_status })}
                                {user.employment_status === 'Nonaktif' && trans('admin.user_form.employment_locked_note')}
                            </p>
                        </Field>
                    </div>
                )}

                {tab === 'roles' && (
                    <div>
                        <p className="mb-3 text-sm font-semibold text-gray-700 dark:text-ink-2">{trans('admin.user_form.user_roles')}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {roles.map((r) => (
                                <label
                                    key={r.id}
                                    className={`flex cursor-pointer items-start gap-2 rounded-xl border p-3 text-sm ${
                                        roleIds.includes(r.id) ? 'border-blue-400 bg-blue-50 dark:bg-accent-soft' : 'border-gray-200 dark:border-edge-strong'
                                    } ${r.locked ? 'cursor-not-allowed opacity-60' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={roleIds.includes(r.id)}
                                        disabled={r.locked}
                                        onChange={() => toggleRole(r.id)}
                                        className="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-edge-strong"
                                    />
                                    <span>
                                        <span className="block font-semibold text-gray-900 dark:text-ink-1">{r.name}</span>
                                        <span className="text-xs text-gray-500 dark:text-ink-2">{trans(`admin.role_desc.${r.name}`, {}, trans('admin.user_form.custom_role'))}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.cancel')}</button>
                <button onClick={save} disabled={saving} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                    {saving ? trans('admin.common.saving') : tab === 'roles' ? trans('admin.user_form.save_roles') : tab === 'edit' ? trans('admin.common.save_changes') : trans('admin.common.close')}
                </button>
            </ModalFooter>
        </Modal>
    );
}

function initials(name) {
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

/** presentUser() renders empty optional fields as "-"; edit inputs want them blank. */
function dash(value) {
    return !value || value === '-' ? '' : value;
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

function Field({ label, children }) {
    return (
        <div>
            <label className="mb-1.5 block text-sm font-medium text-gray-700 dark:text-ink-2">{label}</label>
            {children}
        </div>
    );
}

import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';

const TABS = ['Detail Pengguna', 'Edit Pengguna', 'Role & Hak Akses'];

const ROLE_DESCRIPTIONS = {
    Requester: 'Membuat & memantau tiket sendiri, mengakses Knowledge Base, memberi rating/feedback.',
    Approver: 'Dikelola melalui Service Catalog',
    'Support IT': 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Tim IT.',
    'Support BPO': 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Business Process Owner (unit bisnis).',
    'Team Lead': 'Mengawasi layanan dan memantau seluruh tiket pada layanan yang menjadi tanggung jawabnya.',
    Administrator: 'Akses konfigurasi penuh: user, role, SLA, service catalog, kategori, approval matrix, audit, integrasi.',
    'Knowledge Administrator': 'Mengelola artikel, FAQ, approval knowledge, AI knowledge training, dan analytics.',
};

export default function ManageUserModal({ user, roles, onClose, onSave }) {
    const [tab, setTab] = useState('Detail Pengguna');
    const [form, setForm] = useState({
        name: user.name, nip: user.nip, email: user.email, whatsapp: user.whatsapp,
        unit: user.unit, jabatan: user.jabatan, status: user.status === 'Aktif' ? 'active' : 'inactive',
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
            setError(e.message || 'Gagal menyimpan perubahan.');
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
            setError(e.message || 'Gagal menyimpan role.');
        } finally {
            setSaving(false);
        }
    }

    function save() {
        if (tab === 'Edit Pengguna') return saveProfile();
        if (tab === 'Role & Hak Akses') return saveRoles();
        onClose();
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-2xl">
            <ModalHeader title="Kelola Pengguna" subtitle={user.name} onClose={onClose} />

            <div className="overflow-y-auto px-6 py-5">
                {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                <div className="mb-4 flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-panel-3 p-4">
                    <span className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-700 dark:bg-blue-500 text-sm font-semibold text-white">
                        {initials(user.name)}
                    </span>
                    <span className="font-semibold text-gray-900 dark:text-ink-1">{user.name}</span>
                    <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-ok-soft px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-ok-text">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        {user.status}
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
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'Detail Pengguna' && (
                    <div>
                        <div className="grid grid-cols-2 gap-4">
                            <Detail label="Nama Lengkap" value={user.name} />
                            <Detail label="Unit Kerja" value={user.unit} />
                            <Detail label="NIP" value={user.nip} />
                            <Detail label="Email Korporat" value={user.email} />
                            <Detail label="Status Akun" value={user.status} />
                            <Detail label="Nomor WhatsApp" value={user.whatsapp} />
                            <Detail label="Bergabung" value={user.joined_at} />
                            <Detail label="Terakhir Login" value={user.last_login} />
                        </div>
                        <div className="mt-4 rounded-lg bg-gray-50 dark:bg-panel-3 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Role Aktif</p>
                            <div className="flex flex-wrap gap-2">
                                {user.roles.map((r) => (
                                    <span key={r} className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:text-accent-text">{r}</span>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {tab === 'Edit Pengguna' && (
                    <div className="space-y-4">
                        <Field label="Nama"><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="NIP"><input value={form.nip ?? ''} onChange={(e) => setForm({ ...form, nip: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="Email"><input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="Nomor WhatsApp"><input value={form.whatsapp ?? ''} onChange={(e) => setForm({ ...form, whatsapp: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="Unit Kerja"><input value={form.unit ?? ''} onChange={(e) => setForm({ ...form, unit: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="Jabatan"><input value={form.jabatan ?? ''} onChange={(e) => setForm({ ...form, jabatan: e.target.value })} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" /></Field>
                        <Field label="Status">
                            <SelectMenu
                                value={form.status}
                                onChange={(v) => setForm({ ...form, status: v })}
                                options={[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]}
                            />
                        </Field>
                    </div>
                )}

                {tab === 'Role & Hak Akses' && (
                    <div>
                        <p className="mb-3 text-sm font-semibold text-gray-700 dark:text-ink-2">Role Pengguna</p>
                        <div className="grid grid-cols-2 gap-3">
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
                                        <span className="text-xs text-gray-500 dark:text-ink-2">{ROLE_DESCRIPTIONS[r.name] ?? 'Role kustom.'}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">Batal</button>
                <button onClick={save} disabled={saving} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                    {saving ? 'Menyimpan...' : tab === 'Role & Hak Akses' ? 'Simpan Role' : tab === 'Edit Pengguna' ? 'Simpan Perubahan' : 'Tutup'}
                </button>
            </ModalFooter>
        </Modal>
    );
}

function initials(name) {
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

function Detail({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-ink-1">{value}</p>
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

import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';

// Split around the phone input, which needs its own +62 prefix markup.
const FIELDS_BEFORE_PHONE = [
    ['name', 'Nama Lengkap', 'Nama lengkap pengguna'],
    ['nip', 'NIP', 'Nomor Induk Pegawai'],
    ['email', 'Email Korporat', 'nama@adhi.co.id'],
];

const FIELDS_AFTER_PHONE = [
    ['address', 'Alamat', 'Alamat lengkap pengguna'],
    ['unit', 'Unit Kerja', 'Unit / divisi'],
    ['jabatan', 'Jabatan', 'Jabatan pengguna'],
    ['kode_departemen', 'Kode Departemen', 'Kode departemen (jika ada)'],
    ['kode_divisi', 'Kode Divisi', 'Kode divisi (jika ada)'],
    ['kode_proyek', 'Kode Proyek', 'Kode proyek (jika ada)'],
    ['nama_proyek', 'Nama Proyek', 'Nama proyek (jika ada)'],
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

export default function AddUserModal({ roles, onClose, onSave }) {
    const [form, setForm] = useState({
        name: '', nip: '', email: '', username: '', phone: '', address: '',
        unit: '', jabatan: '', kode_departemen: '', kode_divisi: '',
        kode_proyek: '', nama_proyek: '', helpdesk_access: 'enabled',
    });
    // Username mirrors the email until the admin edits it directly.
    const [usernameEdited, setUsernameEdited] = useState(false);
    const [roleIds, setRoleIds] = useState(() => {
        const requester = roles.find((r) => r.name === 'Requester');
        return requester ? [requester.id] : [];
    });
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function set(key, value) {
        setForm((prev) => ({
            ...prev,
            [key]: value,
            ...(key === 'email' && !usernameEdited ? { username: value } : {}),
        }));
    }

    function toggleRole(id) {
        setRoleIds((prev) => (prev.includes(id) ? prev.filter((r) => r !== id) : [...prev, id]));
    }

    async function save() {
        setError('');
        setSaving(true);
        try {
            const created = await apiFetch('/admin/users', { method: 'POST', body: JSON.stringify({ ...form, role_ids: roleIds }) });
            onSave(created);
        } catch (e) {
            setError(e.message || 'Gagal menambahkan user.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-lg">
            <ModalHeader title="Tambah User" subtitle="Lengkapi data pengguna dan penugasan role." onClose={onClose} />

            <div className="space-y-4 overflow-y-auto px-6 py-5">
                {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                {FIELDS_BEFORE_PHONE.map(([key, label, placeholder]) => (
                    <Field key={key} label={label}>
                        <input
                            value={form[key]}
                            onChange={(e) => set(key, e.target.value)}
                            placeholder={placeholder}
                            className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                        />
                    </Field>
                ))}

                <Field label="Username">
                    <input
                        value={form.username}
                        onChange={(e) => { setUsernameEdited(true); set('username', e.target.value); }}
                        placeholder="Otomatis mengikuti email"
                        className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                    />
                    <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">Dikosongkan berarti memakai email korporat.</p>
                </Field>

                <Field label="Nomor Telepon">
                    <div className="flex overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 focus-within:border-blue-400 focus-within:bg-white dark:focus-within:bg-panel-hover">
                        <span className="flex items-center px-3 text-sm text-gray-500 dark:text-ink-2">+62</span>
                        <input
                            value={form.phone}
                            onChange={(e) => set('phone', e.target.value)}
                            placeholder="Contoh: 0812xxxxxxx"
                            className="w-full bg-transparent px-1 py-2.5 text-sm focus:outline-none"
                        />
                    </div>
                </Field>

                {FIELDS_AFTER_PHONE.map(([key, label, placeholder]) => (
                    <Field key={key} label={label}>
                        <input
                            value={form[key]}
                            onChange={(e) => set(key, e.target.value)}
                            placeholder={placeholder}
                            className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                        />
                    </Field>
                ))}

                <Field label="Role">
                    <div className="space-y-2">
                        {roles.filter((r) => r.status_raw === 'active').map((r) => (
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
                </Field>

                <Field label="Akses Helpdesk">
                    <SelectMenu
                        value={form.helpdesk_access}
                        onChange={(v) => set('helpdesk_access', v)}
                        options={[{ value: 'enabled', label: 'Aktif' }, { value: 'disabled', label: 'Nonaktif' }]}
                    />
                    <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">
                        Status kepegawaian diisi otomatis dari API perusahaan saat sinkronisasi berikutnya.
                    </p>
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">Batal</button>
                <button
                    onClick={save}
                    disabled={saving || !form.name || !form.email || roleIds.length === 0}
                    className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {saving ? 'Menyimpan...' : 'Simpan'}
                </button>
            </ModalFooter>
        </Modal>
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

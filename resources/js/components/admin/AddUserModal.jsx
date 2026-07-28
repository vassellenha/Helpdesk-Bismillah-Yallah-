import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';

const FIELDS = [
    ['name', 'Nama Lengkap', 'Nama lengkap pengguna'],
    ['nip', 'NIP', 'Nomor Induk Pegawai'],
    ['email', 'Email Korporat', 'nama@adhi.co.id'],
    ['unit', 'Unit Kerja', 'Unit / divisi'],
    ['jabatan', 'Jabatan', 'Jabatan pengguna'],
    ['kode_proyek', 'Kode Proyek', 'Kode proyek (jika ada)'],
    ['nama_proyek', 'Nama Proyek', 'Nama proyek (jika ada)'],
];

export default function AddUserModal({ onClose, onSave }) {
    const [form, setForm] = useState({ name: '', nip: '', email: '', whatsapp: '', unit: '', jabatan: '', kode_proyek: '', nama_proyek: '', status: 'active' });
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function set(key, value) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    async function save() {
        setError('');
        setSaving(true);
        try {
            const created = await apiFetch('/admin/users', { method: 'POST', body: JSON.stringify(form) });
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

                {FIELDS.slice(0, 3).map(([key, label, placeholder]) => (
                    <Field key={key} label={label}>
                        <input
                            value={form[key]}
                            onChange={(e) => set(key, e.target.value)}
                            placeholder={placeholder}
                            className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                        />
                    </Field>
                ))}

                <Field label="Nomor WhatsApp">
                    <div className="flex overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 focus-within:border-blue-400 focus-within:bg-white dark:focus-within:bg-panel-hover">
                        <span className="flex items-center px-3 text-sm text-gray-500 dark:text-ink-2">+62</span>
                        <input
                            value={form.whatsapp}
                            onChange={(e) => set('whatsapp', e.target.value)}
                            placeholder="Contoh: 0812xxxxxxx"
                            className="w-full bg-transparent px-1 py-2.5 text-sm focus:outline-none"
                        />
                    </div>
                </Field>

                {FIELDS.slice(3).map(([key, label, placeholder]) => (
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
                    <div className="flex flex-wrap gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5">
                        <span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 dark:text-accent-text">Requester</span>
                    </div>
                </Field>

                <Field label="Status Akun">
                    <SelectMenu
                        value={form.status}
                        onChange={(v) => set('status', v)}
                        options={[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]}
                    />
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">Batal</button>
                <button
                    onClick={save}
                    disabled={saving || !form.name || !form.email}
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

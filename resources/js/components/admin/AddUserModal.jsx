import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
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
                {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

                {FIELDS.slice(0, 3).map(([key, label, placeholder]) => (
                    <Field key={key} label={label}>
                        <input
                            value={form[key]}
                            onChange={(e) => set(key, e.target.value)}
                            placeholder={placeholder}
                            className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                        />
                    </Field>
                ))}

                <Field label="Nomor WhatsApp">
                    <div className="flex overflow-hidden rounded-lg border border-gray-200 bg-gray-50 focus-within:border-blue-400 focus-within:bg-white">
                        <span className="flex items-center px-3 text-sm text-gray-500">+62</span>
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
                            className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                        />
                    </Field>
                ))}

                <Field label="Role">
                    <div className="flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
                        <span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Requester</span>
                    </div>
                </Field>

                <Field label="Status Akun">
                    <select
                        value={form.status}
                        onChange={(e) => set('status', e.target.value)}
                        className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                    >
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-white">Batal</button>
                <button
                    onClick={save}
                    disabled={saving || !form.name || !form.email}
                    className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
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
            <label className="mb-1.5 block text-sm font-medium text-gray-700">{label}</label>
            {children}
        </div>
    );
}

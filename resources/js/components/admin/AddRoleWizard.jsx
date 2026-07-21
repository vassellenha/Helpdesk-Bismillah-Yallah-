import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import { apiFetch } from '../../lib/api';

export default function AddRoleWizard({ onClose, onSave, unitOrganisasi = [], modules = [], actions = [] }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState({ name: '', code: '', unit: '', status: 'active', isDefault: false });
    const [permissions, setPermissions] = useState({});
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function togglePermission(moduleName, action) {
        const key = `${moduleName}:${action}`;
        setPermissions((prev) => ({ ...prev, [key]: !prev[key] }));
    }

    const permissionCount = Object.values(permissions).filter(Boolean).length;

    async function save() {
        setError('');
        setSaving(true);
        try {
            const created = await apiFetch('/admin/roles', { method: 'POST', body: JSON.stringify({ name: form.name, status: form.status }) });
            onSave(created);
        } catch (e) {
            setError(e.message || 'Gagal membuat role.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-3xl">
            <ModalHeader title="Tambah Role" subtitle="Definisikan informasi, cakupan layanan, dan hak akses role ini." onClose={onClose} />

            <div className="flex items-center gap-3 px-6 pt-4">
                <StepBadge active={step === 1} done={step > 1} number={1} label="Informasi Role" />
                <div className="h-px flex-1 bg-gray-200" />
                <StepBadge active={step === 2} done={false} number={2} label="Hak Akses" />
            </div>

            <div className="overflow-y-auto px-6 py-5">
                {step === 1 ? (
                    <div className="space-y-4">
                        <Field label="Nama Role">
                            <input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder="mis. Support IT Hardware"
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                            />
                        </Field>
                        <Field label="Kode Role">
                            <input
                                value={form.code}
                                onChange={(e) => setForm({ ...form, code: e.target.value })}
                                placeholder="mis. ROLE-SUP-HW"
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                            />
                        </Field>
                        <Field label="Unit Organisasi">
                            <select
                                value={form.unit}
                                onChange={(e) => setForm({ ...form, unit: e.target.value })}
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                            >
                                <option value="">Pilih...</option>
                                {unitOrganisasi.map((u) => (
                                    <option key={u}>{u}</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Status Role">
                            <select
                                value={form.status}
                                onChange={(e) => setForm({ ...form, status: e.target.value })}
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                            >
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </Field>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                checked={form.isDefault}
                                onChange={(e) => setForm({ ...form, isDefault: e.target.checked })}
                                className="h-4 w-4 rounded border-gray-300"
                            />
                            <span className="font-medium">Jadikan Default Role</span>
                            <span className="text-gray-400">— otomatis ditawarkan saat menambah user baru</span>
                        </label>
                    </div>
                ) : (
                    <div>
                        {error && <p className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
                        <p className="mb-3 text-sm text-gray-500">Atur permission berdasarkan modul untuk role ini. Geser ke kanan untuk melihat seluruh aksi.</p>
                        <div className="overflow-x-auto rounded-xl border border-gray-200">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead>
                                    <tr className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        <th className="px-4 py-2.5">Modul</th>
                                        {actions.map((a) => (
                                            <th key={a} className="px-3 py-2.5 text-center">{a}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {modules.map((m) => (
                                        <tr key={m}>
                                            <td className="px-4 py-2.5 font-medium text-gray-800">{m}</td>
                                            {actions.map((a) => (
                                                <td key={a} className="px-3 py-2.5 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={!!permissions[`${m}:${a}`]}
                                                        onChange={() => togglePermission(m, a)}
                                                        className="h-4 w-4 rounded border-gray-300"
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 rounded-lg bg-blue-50 p-4 text-sm">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-900">Role Summary</p>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <p className="text-xs text-gray-500">Nama Role</p>
                                    <p className="font-semibold text-gray-900">{form.name || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Kategori</p>
                                    <p className="font-semibold text-gray-900">Role Kustom</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500">Layanan</p>
                                    <p className="font-semibold text-gray-900">{permissionCount}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <ModalFooter>
                {step === 1 ? (
                    <>
                        <button onClick={onClose} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-white">Batal</button>
                        <button onClick={() => setStep(2)} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">Lanjut</button>
                    </>
                ) : (
                    <>
                        <button onClick={onClose} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-white">Batal</button>
                        <button onClick={() => setStep(1)} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-gray-600 hover:bg-white">Kembali</button>
                        <button onClick={save} disabled={saving || !form.name} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50">
                            {saving ? 'Menyimpan...' : 'Simpan Role'}
                        </button>
                    </>
                )}
            </ModalFooter>
        </Modal>
    );
}

function StepBadge({ active, done, number, label }) {
    return (
        <div className="flex items-center gap-2">
            <span
                className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                    active || done ? 'bg-blue-700 text-white' : 'bg-gray-200 text-gray-500'
                }`}
            >
                {done ? '✓' : number}
            </span>
            <span className={`text-sm font-medium ${active ? 'text-gray-900' : 'text-gray-400'}`}>{label}</span>
        </div>
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

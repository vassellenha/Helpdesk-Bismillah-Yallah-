import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';

const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];

export default function SlaPolicyModal({ policy, onClose, onSaved }) {
    const isEdit = !!policy;
    const [form, setForm] = useState({
        policy_name: policy?.policy_name ?? '',
        priority: policy?.priority ?? '',
        response_time_minutes: policy?.response_time_minutes ?? '',
        resolution_time_minutes: policy?.resolution_time_minutes ?? '',
        warning_threshold_percent: policy?.warning_threshold_percent ?? '',
        status: policy?.status ?? 'active',
    });
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function set(key, value) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    async function save() {
        setError('');
        setSaving(true);
        try {
            const payload = {
                ...form,
                response_time_minutes: Number(form.response_time_minutes),
                resolution_time_minutes: Number(form.resolution_time_minutes),
                warning_threshold_percent: Number(form.warning_threshold_percent),
            };
            const saved = isEdit
                ? await apiFetch(`/admin/sla-policies/${policy.id}`, { method: 'PUT', body: JSON.stringify(payload) })
                : await apiFetch('/admin/sla-policies', { method: 'POST', body: JSON.stringify(payload) });
            onSaved(saved);
        } catch (e) {
            setError(e.message || 'Gagal menyimpan SLA Policy.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-md">
            <ModalHeader title={isEdit ? 'Edit SLA Policy' : 'Tambah SLA Policy'} subtitle="Tetapkan target waktu dan aturan eskalasi." onClose={onClose} />

            <div className="space-y-4 overflow-y-auto px-6 py-5">
                {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                <Field label="Nama Policy">
                    <input value={form.policy_name} onChange={(e) => set('policy_name', e.target.value)} placeholder="Nama policy SLA" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                </Field>

                <Field label="Prioritas">
                    <SelectMenu
                        value={form.priority}
                        onChange={(v) => set('priority', v)}
                        options={[{ value: '', label: 'Pilih...' }, ...PRIORITIES.map((p) => ({ value: p, label: p }))]}
                    />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Response Time (menit)">
                        <input type="number" min="1" value={form.response_time_minutes} onChange={(e) => set('response_time_minutes', e.target.value)} placeholder="mis. 120" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    </Field>
                    <Field label="Resolution Time (menit)">
                        <input type="number" min="1" value={form.resolution_time_minutes} onChange={(e) => set('resolution_time_minutes', e.target.value)} placeholder="mis. 480" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    </Field>
                </div>

                <Field label="Warning Threshold (%)">
                    <input type="number" min="1" max="100" value={form.warning_threshold_percent} onChange={(e) => set('warning_threshold_percent', e.target.value)} placeholder="mis. 80" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                </Field>

                <Field label="Status">
                    <SelectMenu
                        value={form.status}
                        onChange={(v) => set('status', v)}
                        options={[{ value: 'active', label: 'Aktif' }, { value: 'inactive', label: 'Nonaktif' }]}
                    />
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">Batal</button>
                <button onClick={save} disabled={saving} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
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

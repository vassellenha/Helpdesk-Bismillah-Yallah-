import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { t as trans } from '../../lib/i18n';
import { apiFetch } from '../../lib/api';

const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];

// The API stores everything in minutes (fine-grained enough for any future
// need), but typing "480" to mean "8 jam" is exactly the friction admins
// complained about — so this form collects hours and converts at the edges.
function minutesToHours(minutes) {
    return minutes || minutes === 0 ? minutes / 60 : '';
}

export default function SlaPolicyModal({ policy, onClose, onSaved }) {
    const isEdit = !!policy;
    const [form, setForm] = useState({
        policy_name: policy?.policy_name ?? '',
        priority: policy?.priority ?? '',
        response_time_hours: minutesToHours(policy?.response_time_minutes),
        resolution_time_hours: minutesToHours(policy?.resolution_time_minutes),
        escalation_extension_hours: minutesToHours(policy?.escalation_extension_minutes),
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
                policy_name: form.policy_name,
                priority: form.priority,
                response_time_minutes: Math.round(Number(form.response_time_hours) * 60),
                resolution_time_minutes: Math.round(Number(form.resolution_time_hours) * 60),
                escalation_extension_minutes: Math.round(Number(form.escalation_extension_hours) * 60),
                warning_threshold_percent: Number(form.warning_threshold_percent),
                status: form.status,
            };
            const saved = isEdit
                ? await apiFetch(`/admin/sla-policies/${policy.id}`, { method: 'PUT', body: JSON.stringify(payload) })
                : await apiFetch('/admin/sla-policies', { method: 'POST', body: JSON.stringify(payload) });
            onSaved(saved);
        } catch (e) {
            setError(e.message || trans('admin.sla.save_failed'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-md">
            <ModalHeader title={isEdit ? trans('admin.sla.edit_title') : trans('admin.sla.add_title')} subtitle={trans('admin.sla.form_subtitle')} onClose={onClose} />

            <div className="space-y-4 overflow-y-auto px-6 py-5">
                {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                <Field label={trans('admin.sla.policy_name')}>
                    <input value={form.policy_name} onChange={(e) => set('policy_name', e.target.value)} placeholder={trans('admin.sla.policy_name_hint')} className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                </Field>

                <Field label={trans('admin.sla.col_priority')}>
                    <SelectMenu
                        value={form.priority}
                        onChange={(v) => set('priority', v)}
                        options={[{ value: '', label: trans('admin.common.choose') }, ...PRIORITIES.map((p) => ({ value: p, label: p }))]}
                    />
                </Field>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={trans('admin.sla.response_hours')}>
                        <input type="number" min="0.5" step="0.5" value={form.response_time_hours} onChange={(e) => set('response_time_hours', e.target.value)} placeholder="mis. 2" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    </Field>
                    <Field label={trans('admin.sla.resolution_hours')}>
                        <input type="number" min="0.5" step="0.5" value={form.resolution_time_hours} onChange={(e) => set('resolution_time_hours', e.target.value)} placeholder="mis. 8" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    </Field>
                </div>

                <Field label={trans('admin.sla.escalated_hours')}>
                    <input type="number" min="0" step="0.5" value={form.escalation_extension_hours} onChange={(e) => set('escalation_extension_hours', e.target.value)} placeholder="mis. 4" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                    <p className="mt-1.5 text-xs text-gray-400 dark:text-ink-3">Tambahan waktu resolusi yang diberikan begitu tiket dengan priority ini dieskalasi.</p>
                </Field>

                <Field label={trans('admin.sla.warning_pct')}>
                    <input type="number" min="1" max="100" value={form.warning_threshold_percent} onChange={(e) => set('warning_threshold_percent', e.target.value)} placeholder="mis. 80" className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none" />
                </Field>

                <Field label={trans('admin.common.status')}>
                    <SelectMenu
                        value={form.status}
                        onChange={(v) => set('status', v)}
                        options={[{ value: 'active', label: trans('admin.common.active') }, { value: 'inactive', label: trans('admin.common.inactive') }]}
                    />
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.cancel')}</button>
                <button onClick={save} disabled={saving} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                    {saving ? trans('admin.common.saving') : trans('admin.common.save_changes')}
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

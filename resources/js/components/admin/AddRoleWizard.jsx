import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

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
            setError(e.message || trans('admin.roles.create_failed'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-3xl">
            <ModalHeader title={trans('admin.roles.add_title')} subtitle={trans('admin.roles.add_subtitle')} onClose={onClose} />

            <div className="flex items-center gap-3 px-6 pt-4">
                <StepBadge active={step === 1} done={step > 1} number={1} label={trans('admin.roles.step_info')} />
                <div className="h-px flex-1 bg-gray-200 dark:bg-edge-strong" />
                <StepBadge active={step === 2} done={false} number={2} label={trans('admin.roles.step_access')} />
            </div>

            <div className="overflow-y-auto px-6 py-5">
                {step === 1 ? (
                    <div className="space-y-4">
                        <Field label={trans('admin.roles.name')}>
                            <input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                placeholder={trans('admin.roles.name_hint')}
                                className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                            />
                        </Field>
                        <Field label={trans('admin.roles.code')}>
                            <input
                                value={form.code}
                                onChange={(e) => setForm({ ...form, code: e.target.value })}
                                placeholder={trans('admin.roles.code_hint')}
                                className="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                            />
                        </Field>
                        <Field label={trans('admin.roles.step_scope')}>
                            <SelectMenu
                                value={form.unit}
                                onChange={(v) => setForm({ ...form, unit: v })}
                                options={[{ value: '', label: trans('admin.common.choose') }, ...unitOrganisasi.map((u) => ({ value: u, label: u }))]}
                            />
                        </Field>
                        <Field label={trans('admin.roles.role_status')}>
                            <SelectMenu
                                value={form.status}
                                onChange={(v) => setForm({ ...form, status: v })}
                                options={[{ value: 'active', label: trans('admin.common.active') }, { value: 'inactive', label: trans('admin.common.inactive') }]}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-ink-2">
                            <input
                                type="checkbox"
                                checked={form.isDefault}
                                onChange={(e) => setForm({ ...form, isDefault: e.target.checked })}
                                className="h-4 w-4 rounded border-gray-300 dark:border-edge-strong"
                            />
                            <span className="font-medium">{trans('admin.roles.make_default')}</span>
                            <span className="text-gray-400 dark:text-ink-3">{trans('admin.roles.default_hint')}</span>
                        </label>
                    </div>
                ) : (
                    <div>
                        {error && <p className="mb-3 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}
                        <p className="mb-3 text-sm text-gray-500 dark:text-ink-2">{trans('admin.roles.permission_hint')}</p>
                        <div className="overflow-x-auto rounded-xl border border-gray-200 dark:border-edge-strong">
                            <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                                <thead>
                                    <tr className="bg-gray-50 dark:bg-panel-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                        <th className="px-4 py-2.5">{trans('admin.roles.module')}</th>
                                        {actions.map((a) => (
                                            <th key={a} className="px-3 py-2.5 text-center">{a}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                                    {modules.map((m) => (
                                        <tr key={m} className="dark:even:bg-white/[0.03]">
                                            <td className="px-4 py-2.5 font-medium text-gray-800 dark:text-ink-1">{m}</td>
                                            {actions.map((a) => (
                                                <td key={a} className="px-3 py-2.5 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={!!permissions[`${m}:${a}`]}
                                                        onChange={() => togglePermission(m, a)}
                                                        className="h-4 w-4 rounded border-gray-300 dark:border-edge-strong"
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 rounded-lg bg-blue-50 dark:bg-accent-soft p-4 text-sm">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-900 dark:text-accent-text">{trans('admin.roles.summary')}</p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-ink-2">{trans('admin.roles.name')}</p>
                                    <p className="font-semibold text-gray-900 dark:text-ink-1">{form.name || '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-ink-2">{trans('admin.roles.category')}</p>
                                    <p className="font-semibold text-gray-900 dark:text-ink-1">{trans('admin.roles.custom')}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 dark:text-ink-2">{trans('admin.roles.service')}</p>
                                    <p className="font-semibold text-gray-900 dark:text-ink-1">{permissionCount}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <ModalFooter>
                {step === 1 ? (
                    <>
                        <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.cancel')}</button>
                        <button onClick={() => setStep(2)} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">{trans('admin.common.next')}</button>
                    </>
                ) : (
                    <>
                        <button onClick={onClose} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-blue-700 dark:text-accent-text hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.cancel')}</button>
                        <button onClick={() => setStep(1)} className="rounded-lg border border-gray-200 dark:border-edge-strong px-5 py-2 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-white dark:hover:bg-panel-hover">{trans('admin.common.back')}</button>
                        <button onClick={save} disabled={saving || !form.name} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 disabled:opacity-50">
                            {saving ? trans('admin.common.saving') : trans('admin.roles.save')}
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
                    active || done ? 'bg-blue-700 dark:bg-blue-500 text-white' : 'bg-gray-200 dark:bg-panel-3 text-gray-500 dark:text-ink-2'
                }`}
            >
                {done ? '✓' : number}
            </span>
            <span className={`text-sm font-medium ${active ? 'text-gray-900 dark:text-ink-1' : 'text-gray-400 dark:text-ink-3'}`}>{label}</span>
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

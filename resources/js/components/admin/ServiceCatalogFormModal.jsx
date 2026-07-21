import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import { apiFetch } from '../../lib/api';
import { LEVEL_DESCRIPTIONS } from '../../lib/formatters';

const ISSUE_CATEGORIES = ['Incident', 'Service Request', 'Access Request'];

export default function ServiceCatalogFormModal({ subject, services, subcategories, supportAgents, onClose, onSaved }) {
    const isEdit = !!subject && !subject.__duplicate;
    const [form, setForm] = useState({
        issue_category: subject?.issue_category ?? 'Incident',
        layanan: subject?.layanan ?? '',
        subcategory: subject?.subcategory ?? '',
        subject: subject?.subject ?? '',
        requires_approval: subject ? String(subject.requires_approval) : 'false',
        support_level: subject?.support_level ?? 1,
        support_agent_id: subject?.support_agent_id ?? '',
        status: subject?.status ?? 'active',
    });
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function set(key, value) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    const agentType = Number(form.support_level) === 1 ? 'bpo' : 'it';
    const filteredAgents = supportAgents.filter((a) => a.type === agentType);

    async function save() {
        setError('');
        setSaving(true);
        try {
            const basePayload = {
                issue_category: form.issue_category,
                layanan: form.layanan,
                subcategory: form.subcategory,
                subject: form.subject,
                requires_approval: form.requires_approval === 'true',
                support_agent_id: form.support_agent_id ? Number(form.support_agent_id) : null,
                support_level: Number(form.support_level),
                status: form.status,
            };

            let saved;
            if (isEdit) {
                saved = await apiFetch(`/admin/service-catalog/subjects/${subject.id}`, { method: 'PUT', body: JSON.stringify(basePayload) });
                const supportChanged = saved.support_agent_id !== (form.support_agent_id ? Number(form.support_agent_id) : null) || saved.support_level !== Number(form.support_level);
                if (supportChanged) {
                    saved = await apiFetch(`/admin/service-catalog/subjects/${subject.id}/support`, {
                        method: 'PATCH',
                        body: JSON.stringify({ support_agent_id: basePayload.support_agent_id, support_level: basePayload.support_level }),
                    });
                }
            } else {
                saved = await apiFetch('/admin/service-catalog/subjects', { method: 'POST', body: JSON.stringify(basePayload) });
            }
            onSaved(saved);
        } catch (e) {
            setError(e.message || 'Gagal menyimpan layanan.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-lg">
            <ModalHeader
                title={isEdit ? 'Edit Layanan' : 'Tambah Layanan'}
                subtitle="Definisikan Issue Category, Layanan, Sub Category, dan Subject."
                onClose={onClose}
            />

            <div className="space-y-4 overflow-y-auto px-6 py-5">
                {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

                <Field label="Issue Category">
                    <select value={form.issue_category} onChange={(e) => set('issue_category', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                        {ISSUE_CATEGORIES.map((c) => <option key={c}>{c}</option>)}
                    </select>
                </Field>

                <Field label="Layanan">
                    <input list="layanan-options" value={form.layanan} onChange={(e) => set('layanan', e.target.value)} placeholder="mis. SAP, VPN, Printer" className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
                    <datalist id="layanan-options">
                        {services.map((s) => <option key={s.id} value={s.name} />)}
                    </datalist>
                </Field>

                <Field label="Sub Category">
                    <input list="subcategory-options" value={form.subcategory} onChange={(e) => set('subcategory', e.target.value)} placeholder="mis. Login SAP" className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
                    <datalist id="subcategory-options">
                        {subcategories.map((s) => <option key={s.id} value={s.name} />)}
                    </datalist>
                </Field>

                <Field label="Subject">
                    <input value={form.subject} onChange={(e) => set('subject', e.target.value)} placeholder="mis. Password Expired" className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
                </Field>

                <Field label="Requires Approval">
                    <select value={form.requires_approval} onChange={(e) => set('requires_approval', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                        <option value="false">No</option>
                        <option value="true">Yes</option>
                    </select>
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Level">
                        <select
                            value={form.support_level}
                            onChange={(e) => setForm((prev) => ({ ...prev, support_level: e.target.value, support_agent_id: '' }))}
                            className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                        >
                            {[1, 2, 3].map((lvl) => (
                                <option key={lvl} value={lvl}>Level {lvl} — {LEVEL_DESCRIPTIONS[lvl]}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Support">
                        <select value={form.support_agent_id} onChange={(e) => set('support_agent_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                            <option value="">Pilih...</option>
                            {filteredAgents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Status">
                    <select value={form.status} onChange={(e) => set('status', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </Field>
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-white">Batal</button>
                <button onClick={save} disabled={saving || !form.layanan || !form.subcategory || !form.subject} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
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

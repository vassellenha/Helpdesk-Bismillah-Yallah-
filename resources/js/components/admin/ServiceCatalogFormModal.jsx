import { useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './Modal';
import { apiFetch } from '../../lib/api';

const ISSUE_CATEGORIES = ['Incident', 'Service Request', 'Access Request'];

// Level 2 means the Subject is handled by *two people at once* — one BPO
// agent and one IT agent working it together (sequentially) — not "pick
// either team from a combined pool". Level 1 means a single person handles
// it alone, from whichever team is chosen.
const LEVEL_OPTIONS = [
    { key: 'l1-bpo', level: 1, label: 'Level 1 — Support BPO' },
    { key: 'l1-it', level: 1, label: 'Level 1 — Support IT' },
    { key: 'l2-both', level: 2, label: 'Level 2 — Support BPO & IT' },
];

function deriveLevelKey(level, hasBpoAgent, hasItAgent) {
    if (Number(level) === 2) return 'l2-both';
    return hasItAgent && !hasBpoAgent ? 'l1-it' : 'l1-bpo';
}

export default function ServiceCatalogFormModal({ subject, supportAgents, onClose, onSaved }) {
    const isEdit = !!subject && !subject.__duplicate;
    const [form, setForm] = useState({
        issue_category: subject?.issue_category ?? 'Incident',
        layanan: subject?.layanan ?? '',
        subcategory: subject?.subcategory ?? '',
        subject: subject?.subject ?? '',
        requires_approval: subject ? String(subject.requires_approval) : 'false',
        levelKey: deriveLevelKey(subject?.support_level ?? 1, !!subject?.support_agent_id, !!subject?.it_agent_id),
        support_agent_id: subject?.support_agent_id ?? '',
        it_agent_id: subject?.it_agent_id ?? '',
        status: subject?.status ?? 'active',
    });
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    function set(key, value) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    const bpoAgents = supportAgents.filter((a) => a.type === 'bpo');
    const itAgents = supportAgents.filter((a) => a.type === 'it');
    const selectedLevel = LEVEL_OPTIONS.find((o) => o.key === form.levelKey) ?? LEVEL_OPTIONS[0];
    const showBpoField = selectedLevel.key === 'l1-bpo' || selectedLevel.key === 'l2-both';
    const showItField = selectedLevel.key === 'l1-it' || selectedLevel.key === 'l2-both';

    // Switching Level changes which agent field(s) are shown — clear
    // whichever field is no longer applicable so a stale pick can't sneak
    // into the save payload for a team that's not handling this Subject.
    function selectLevel(key) {
        const opt = LEVEL_OPTIONS.find((o) => o.key === key);
        const willShowBpo = key === 'l1-bpo' || key === 'l2-both';
        const willShowIt = key === 'l1-it' || key === 'l2-both';
        setForm((prev) => ({
            ...prev,
            levelKey: opt.key,
            support_agent_id: willShowBpo ? prev.support_agent_id : '',
            it_agent_id: willShowIt ? prev.it_agent_id : '',
        }));
    }

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
                support_agent_id: showBpoField && form.support_agent_id ? Number(form.support_agent_id) : null,
                it_agent_id: showItField && form.it_agent_id ? Number(form.it_agent_id) : null,
                support_level: selectedLevel.level,
                status: form.status,
            };

            let saved;
            if (isEdit) {
                saved = await apiFetch(`/admin/service-catalog/subjects/${subject.id}`, { method: 'PUT', body: JSON.stringify(basePayload) });
                const supportChanged = saved.support_agent_id !== basePayload.support_agent_id
                    || saved.it_agent_id !== basePayload.it_agent_id
                    || saved.support_level !== selectedLevel.level;
                if (supportChanged) {
                    saved = await apiFetch(`/admin/service-catalog/subjects/${subject.id}/support`, {
                        method: 'PATCH',
                        body: JSON.stringify({
                            support_agent_id: basePayload.support_agent_id,
                            it_agent_id: basePayload.it_agent_id,
                            support_level: basePayload.support_level,
                        }),
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
                    <input value={form.layanan} onChange={(e) => set('layanan', e.target.value)} placeholder="mis. SAP, VPN, Printer" className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
                </Field>

                <Field label="Sub Category">
                    <input value={form.subcategory} onChange={(e) => set('subcategory', e.target.value)} placeholder="mis. Login SAP" className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none" />
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

                <Field label="Level">
                    <select
                        value={form.levelKey}
                        onChange={(e) => selectLevel(e.target.value)}
                        className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                    >
                        {LEVEL_OPTIONS.map((opt) => (
                            <option key={opt.key} value={opt.key}>{opt.label}</option>
                        ))}
                    </select>
                </Field>

                <div className={selectedLevel.key === 'l2-both' ? 'grid grid-cols-2 gap-4' : ''}>
                    {showBpoField && (
                        <Field label="Support BPO">
                            <select value={form.support_agent_id} onChange={(e) => set('support_agent_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                                <option value="">Pilih...</option>
                                {bpoAgents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </Field>
                    )}
                    {showItField && (
                        <Field label="Support IT">
                            <select value={form.it_agent_id} onChange={(e) => set('it_agent_id', e.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none">
                                <option value="">Pilih...</option>
                                {itAgents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                        </Field>
                    )}
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

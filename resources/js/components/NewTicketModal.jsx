import { useEffect, useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './admin/Modal';
import { apiFetch } from '../lib/api';

function formatMinutes(minutes) {
    if (minutes % 1440 === 0) return `${minutes / 1440} Hari`;
    if (minutes % 60 === 0) return `${minutes / 60} Jam`;
    return `${minutes} Menit`;
}

export default function NewTicketModal({ categories = [] }) {
    const [open, setOpen] = useState(false);
    const [policies, setPolicies] = useState(null); // null = loading
    const [form, setForm] = useState({ title: '', category: categories[0] ?? '', sla_policy_id: '' });
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [created, setCreated] = useState(null);

    useEffect(() => {
        if (!open) return;
        setPolicies(null);
        setCreated(null);
        setError('');
        apiFetch('/api/sla-policies/active')
            .then((data) => {
                setPolicies(data);
                setForm((f) => ({ ...f, sla_policy_id: data[0]?.id ?? '' }));
            })
            .catch(() => setPolicies([]));
    }, [open]);

    const selectedPolicy = policies?.find((p) => p.id === Number(form.sla_policy_id));

    async function submit() {
        setError('');
        setSubmitting(true);
        try {
            const ticket = await apiFetch('/api/tickets', {
                method: 'POST',
                body: JSON.stringify({
                    title: form.title,
                    category: form.category,
                    sla_policy_id: Number(form.sla_policy_id),
                }),
            });
            setCreated(ticket);
        } catch (e) {
            setError(e.message || 'Gagal membuat tiket.');
        } finally {
            setSubmitting(false);
        }
    }

    function close() {
        setOpen(false);
    }

    return (
        <>
            <button onClick={() => setOpen(true)} className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                + Buat Tiket Baru
            </button>

            {open && (
                <Modal onClose={close} maxWidth="max-w-md">
                    <ModalHeader title="Buat Tiket Baru" subtitle="Lengkapi detail permintaan Anda." onClose={close} />

                    <div className="space-y-4 overflow-y-auto px-6 py-5">
                        {created ? (
                            <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">
                                <p className="font-semibold">Tiket {created.ticket_no} berhasil dibuat.</p>
                                <p className="mt-1">
                                    SLA: Response {formatMinutes(created.response_time_minutes)} · Resolution {formatMinutes(created.resolution_time_minutes)} · Status saat ini: {created.sla_status}
                                </p>
                            </div>
                        ) : (
                            <>
                                {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

                                <Field label="Judul Tiket">
                                    <input
                                        value={form.title}
                                        onChange={(e) => setForm({ ...form, title: e.target.value })}
                                        placeholder="Contoh: VPN tidak bisa connect"
                                        className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                                    />
                                </Field>

                                <Field label="Kategori">
                                    <select
                                        value={form.category}
                                        onChange={(e) => setForm({ ...form, category: e.target.value })}
                                        className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                                    >
                                        {categories.map((c) => <option key={c}>{c}</option>)}
                                    </select>
                                </Field>

                                <Field label="Priority">
                                    {policies === null ? (
                                        <p className="text-sm text-gray-400">Memuat priority...</p>
                                    ) : policies.length === 0 ? (
                                        <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                            Belum ada SLA Policy aktif. Hubungi Administrator.
                                        </p>
                                    ) : (
                                        <select
                                            value={form.sla_policy_id}
                                            onChange={(e) => setForm({ ...form, sla_policy_id: e.target.value })}
                                            className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white focus:outline-none"
                                        >
                                            {policies.map((p) => (
                                                <option key={p.id} value={p.id}>{p.priority}</option>
                                            ))}
                                        </select>
                                    )}
                                </Field>

                                {selectedPolicy && (
                                    <p className="rounded-lg bg-blue-50 p-3 text-xs text-blue-900">
                                        Response Time: <strong>{formatMinutes(selectedPolicy.response_time_minutes)}</strong> · Resolution
                                        Time: <strong>{formatMinutes(selectedPolicy.resolution_time_minutes)}</strong> · Warning:{' '}
                                        <strong>{selectedPolicy.warning_threshold_percent}%</strong>
                                    </p>
                                )}
                            </>
                        )}
                    </div>

                    <ModalFooter>
                        {created ? (
                            <button onClick={close} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">Tutup</button>
                        ) : (
                            <>
                                <button onClick={close} className="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-white">Batal</button>
                                <button
                                    onClick={submit}
                                    disabled={submitting || !form.title || !policies?.length}
                                    className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {submitting ? 'Mengirim...' : 'Kirim Tiket'}
                                </button>
                            </>
                        )}
                    </ModalFooter>
                </Modal>
            )}
        </>
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

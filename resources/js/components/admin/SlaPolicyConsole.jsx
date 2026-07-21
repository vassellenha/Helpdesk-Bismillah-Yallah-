import { useEffect, useMemo, useRef, useState } from 'react';
import SlaPolicyModal from './SlaPolicyModal';
import { apiFetch } from '../../lib/api';

function formatMinutes(minutes) {
    if (minutes % 1440 === 0) return `${minutes / 1440} Hari`;
    if (minutes % 60 === 0) return `${minutes / 60} Jam`;
    return `${minutes} Menit`;
}

export default function SlaPolicyConsole({ policies: initialPolicies, ticketSlaBreakdown }) {
    const [policies, setPolicies] = useState(initialPolicies);
    const [modal, setModal] = useState(null); // 'add' | { type: 'edit', policy } | { type: 'detail', policy }
    const [menu, setMenu] = useState(null); // { policy, top, left }
    const [error, setError] = useState('');
    const menuRef = useRef(null);

    const activeCount = useMemo(() => policies.filter((p) => p.status === 'active').length, [policies]);

    useEffect(() => {
        if (!menu) return;
        function onClickOutside(e) {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenu(null);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [menu]);

    function openMenu(e, policy) {
        if (menu?.policy.id === policy.id) {
            setMenu(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        setMenu({ policy, top: rect.bottom + 4, left: rect.right - 160 });
    }

    function upsertPolicy(saved) {
        setPolicies((prev) => {
            const exists = prev.some((p) => p.id === saved.id);
            return exists ? prev.map((p) => (p.id === saved.id ? saved : p)) : [...prev, saved];
        });
        setModal(null);
    }

    async function toggleStatus(policy) {
        setMenu(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/sla-policies/${policy.id}/toggle`, { method: 'POST' });
            setPolicies((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
        } catch (e) {
            setError(e.message || 'Gagal memperbarui status.');
        }
    }

    return (
        <div>
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900">Konfigurasi SLA</h1>
                    <p className="mt-1 text-sm text-gray-500">Atur target SLA, peringatan, notifikasi pelanggaran, dan eskalasi otomatis.</p>
                </div>
                <button onClick={() => setModal('add')} className="shrink-0 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
                    + Tambah SLA Policy
                </button>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat label="SLA POLICY AKTIF" value={activeCount} bg="bg-blue-50" color="text-blue-600" />
                <Stat label="TICKET WITHIN SLA" value={`${ticketSlaBreakdown[0]?.percent ?? 0}%`} bg="bg-emerald-50" color="text-emerald-600" />
                <Stat label="SLA WARNING" value={`${ticketSlaBreakdown[1]?.percent ?? 0}%`} bg="bg-amber-50" color="text-amber-600" />
                <Stat label="SLA BREACH" value={`${ticketSlaBreakdown[2]?.percent ?? 0}%`} bg="bg-red-50" color="text-red-600" />
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <th className="px-5 py-3">Nama SLA</th>
                                <th className="px-5 py-3">Prioritas</th>
                                <th className="px-5 py-3">Response Time</th>
                                <th className="px-5 py-3">Resolution Time</th>
                                <th className="px-5 py-3">SLA Warning</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {policies.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-5 py-3 font-semibold text-gray-900">{p.policy_name}</td>
                                    <td className="px-5 py-3">
                                        <PriorityBadge priority={p.priority} />
                                    </td>
                                    <td className="px-5 py-3 text-gray-600">{formatMinutes(p.response_time_minutes)}</td>
                                    <td className="px-5 py-3 text-gray-600">{formatMinutes(p.resolution_time_minutes)}</td>
                                    <td className="px-5 py-3 text-gray-600">{p.warning_threshold_percent}%</td>
                                    <td className="px-5 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${p.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${p.status === 'active' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {p.status === 'active' ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <button onClick={(e) => openMenu(e, p)} className="rounded-full border border-gray-200 px-2.5 py-1 text-gray-500 hover:bg-gray-100">
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {menu && (
                <div
                    ref={menuRef}
                    style={{ top: menu.top, left: menu.left }}
                    className="fixed z-50 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg"
                >
                    <button onClick={() => { setModal({ type: 'detail', policy: menu.policy }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <SearchIcon /> Lihat Detail
                    </button>
                    <button onClick={() => { setModal({ type: 'edit', policy: menu.policy }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <EditIcon /> Edit
                    </button>
                    <button onClick={() => toggleStatus(menu.policy)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">
                        <ToggleIcon /> {menu.policy.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}
                    </button>
                </div>
            )}

            <div className="mt-6 flex items-start gap-2 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                <span className="mt-0.5 h-full w-1 shrink-0 rounded bg-blue-600" />
                <p>
                    <strong className="block">Bagaimana SLA dihitung</strong>
                    Sistem menghitung SLA secara otomatis sejak tiket dibuat. Notifikasi SLA Warning dikirim sebelum tenggat tercapai, dan notifikasi SLA
                    Breach dikirim setelah tenggat terlewati, sekaligus memicu eskalasi otomatis sesuai aturan yang ditetapkan.
                </p>
            </div>

            {(modal === 'add' || modal?.type === 'edit') && (
                <SlaPolicyModal policy={modal?.policy} onClose={() => setModal(null)} onSaved={upsertPolicy} />
            )}
            {modal?.type === 'detail' && (
                <PolicyDetailModal policy={modal.policy} onClose={() => setModal(null)} />
            )}
        </div>
    );
}

function SearchIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function EditIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
            <path d="M12 20h9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function ToggleIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-red-500">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function Stat({ label, value, bg, color }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2.5 w-2.5 rounded-full bg-current" />
            </span>
            <p className="mt-3 text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs font-medium text-gray-400">{label}</p>
        </div>
    );
}

const PRIORITY_STYLES = {
    Critical: 'bg-red-50 text-red-700',
    High: 'bg-orange-50 text-orange-700',
    Medium: 'bg-blue-50 text-blue-700',
    Low: 'bg-gray-100 text-gray-500',
};

function PriorityBadge({ priority }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${PRIORITY_STYLES[priority] ?? 'bg-gray-100 text-gray-600'}`}>{priority}</span>;
}

function PolicyDetailModal({ policy, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">Detail SLA Policy</h2>
                        <p className="mt-0.5 text-sm text-gray-500">{policy.policy_name}</p>
                    </div>
                    <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">✕</button>
                </div>
                <div className="grid grid-cols-2 gap-4 px-6 py-5 text-sm">
                    <Detail label="Prioritas" value={policy.priority} />
                    <Detail label="Response Time" value={formatMinutes(policy.response_time_minutes)} />
                    <Detail label="Resolution Time" value={formatMinutes(policy.resolution_time_minutes)} />
                    <Detail label="Warning Threshold" value={`${policy.warning_threshold_percent}%`} />
                    <Detail label="Status" value={policy.status === 'active' ? 'Aktif' : 'Nonaktif'} />
                </div>
                <div className="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <button onClick={onClose} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">Tutup</button>
                </div>
            </div>
        </div>
    );
}

function Detail({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900">{value}</p>
        </div>
    );
}

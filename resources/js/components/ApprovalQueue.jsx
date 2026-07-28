import { useMemo, useState } from 'react';
import { StatusBadge } from './StatusBadge';
import useLockBodyScroll from '../lib/useLockBodyScroll';

export default function ApprovalQueue({ queue = [] }) {
    const [items, setItems] = useState(queue);
    const [filter, setFilter] = useState('Pending');
    const [confirming, setConfirming] = useState(null);
    useLockBodyScroll(!!confirming);

    const filtered = useMemo(
        () => items.filter((i) => filter === 'Semua' || i.status === filter),
        [items, filter]
    );

    function decide(id, decision) {
        setItems((prev) => prev.map((i) => (i.id === id ? { ...i, status: decision } : i)));
        setConfirming(null);
    }

    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-edge p-4">
                <h2 className="text-sm font-semibold text-gray-900 dark:text-ink-1">Antrian Approval</h2>
                <div className="flex gap-2">
                    {['Pending', 'Approved', 'Rejected', 'Semua'].map((f) => (
                        <button
                            key={f}
                            onClick={() => setFilter(f)}
                            className={`rounded-full px-3 py-1.5 text-xs font-medium ${
                                filter === f ? 'bg-blue-700 dark:bg-blue-500 text-white' : 'bg-gray-100 dark:bg-panel-3 text-gray-600 dark:text-ink-2 hover:bg-gray-200'
                            }`}
                        >
                            {f}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                    <thead>
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-4 py-3">Permintaan</th>
                            <th className="px-4 py-3">Requester</th>
                            <th className="px-4 py-3">Level Approval</th>
                            <th className="px-4 py-3">Diajukan</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                        {filtered.map((item) => (
                            <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-gray-900 dark:text-ink-1">{item.title}</p>
                                    <p className="text-xs text-gray-400 dark:text-ink-3">{item.id} · {item.ticket_id}</p>
                                </td>
                                <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{item.requester}</td>
                                <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{item.level}</td>
                                <td className="px-4 py-3 text-gray-500 dark:text-ink-2">{item.submitted_at}</td>
                                <td className="px-4 py-3"><StatusBadge status={item.status} /></td>
                                <td className="px-4 py-3">
                                    {item.status === 'Pending' ? (
                                        <div className="flex justify-end gap-2">
                                            <button
                                                onClick={() => setConfirming({ id: item.id, decision: 'Rejected' })}
                                                className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]"
                                            >
                                                Tolak
                                            </button>
                                            <button
                                                onClick={() => setConfirming({ id: item.id, decision: 'Approved' })}
                                                className="rounded-lg bg-blue-700 dark:bg-blue-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400"
                                            >
                                                Setujui
                                            </button>
                                        </div>
                                    ) : (
                                        <span className="block text-right text-xs text-gray-400 dark:text-ink-3">Selesai</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-sm text-gray-400 dark:text-ink-3">
                                    Tidak ada permintaan pada status ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {confirming && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={() => setConfirming(null)}>
                    <div className="w-full max-w-sm rounded-xl bg-white dark:bg-panel-2 p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-sm font-semibold text-gray-900 dark:text-ink-1">
                            {confirming.decision === 'Approved' ? 'Setujui permintaan ini?' : 'Tolak permintaan ini?'}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">Aksi ini akan mengubah status pada antrian approval.</p>
                        <div className="mt-4 flex justify-end gap-2">
                            <button onClick={() => setConfirming(null)} className="rounded-lg border border-gray-200 dark:border-edge-strong px-4 py-2 text-sm font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                Batal
                            </button>
                            <button
                                onClick={() => decide(confirming.id, confirming.decision)}
                                className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${
                                    confirming.decision === 'Approved' ? 'bg-blue-700 dark:bg-blue-500 hover:bg-blue-800 dark:hover:bg-blue-400' : 'bg-red-600 hover:bg-red-700'
                                }`}
                            >
                                Ya, {confirming.decision === 'Approved' ? 'Setujui' : 'Tolak'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

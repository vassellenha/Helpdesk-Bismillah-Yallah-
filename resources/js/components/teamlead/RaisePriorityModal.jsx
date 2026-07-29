import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import useLockBodyScroll from '../../lib/useLockBodyScroll';

const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];

/** Change a ticket's priority (Team-Lead-only corrective action). */
export default function RaisePriorityModal({ ticket, remindUrlBase, onClose, onSaved }) {
    useLockBodyScroll();
    const [priority, setPriority] = useState(ticket.priority);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    async function submit() {
        if (priority === ticket.priority) {
            setError('Pilih prioritas yang berbeda.');
            return;
        }
        setSaving(true);
        setError('');
        try {
            const res = await apiFetch(`${remindUrlBase}/${ticket.id}/raise-priority`, {
                method: 'POST',
                body: JSON.stringify({ priority }),
            });
            onSaved?.(res);
        } catch (e) {
            setError(e.message || 'Gagal mengubah prioritas.');
            setSaving(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onMouseDown={onClose}>
            <div className="w-full max-w-sm overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-5 py-4">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">Ubah Prioritas</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{ticket.id} · sekarang {ticket.priority}</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-700 dark:hover:text-ink-1">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div className="px-5 py-4">
                    <div className="grid grid-cols-2 gap-2">
                        {PRIORITIES.map((p) => (
                            <button
                                key={p}
                                onClick={() => setPriority(p)}
                                className={`rounded-xl border px-3 py-2.5 text-[13px] font-semibold transition ${
                                    priority === p ? 'border-blue-500 bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'border-gray-200 dark:border-edge-strong text-gray-700 dark:text-ink-2 hover:border-gray-300'
                                }`}
                            >
                                {p}
                            </button>
                        ))}
                    </div>
                    {error && <p className="mt-3 rounded-lg bg-red-50 dark:bg-bad-soft px-3 py-2 text-[12px] font-medium text-red-600 dark:text-bad-text">{error}</p>}
                </div>
                <div className="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-edge px-5 py-3.5">
                    <button onClick={onClose} className="rounded-[10px] px-4 py-2.5 text-[13px] font-semibold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">Batal</button>
                    <button onClick={submit} disabled={saving} className="rounded-[10px] bg-blue-600 dark:bg-blue-500 px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-blue-700 dark:hover:bg-blue-400 disabled:opacity-60">
                        {saving ? 'Menyimpan…' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

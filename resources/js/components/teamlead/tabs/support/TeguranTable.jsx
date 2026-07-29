import { useEffect, useMemo, useState } from 'react';
import { apiFetch } from '../../../../lib/api';

const TABS = [['all', 'Semua'], ['email', 'Email'], ['whatsapp', 'WhatsApp'], ['none', 'Belum']];

const CHANNEL = {
    email: { label: 'Email', style: 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text', icon: 'M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z M4 7l8 6 8-6' },
    whatsapp: { label: 'WhatsApp', style: 'bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text', icon: 'M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H5a1 1 0 0 1-1-1Z M8 9h8 M8 12h5' },
};

export default function TeguranTable({ rows = [], remindUrlBase, onOpenTicket, onSent }) {
    const [items, setItems] = useState(rows);
    const [tab, setTab] = useState('all');
    const [sending, setSending] = useState(null); // `${id}:${channel}`
    const [toast, setToast] = useState(null);

    // Re-sync when the parent refetches after a corrective action.
    useEffect(() => { setItems(rows); }, [rows]);

    function flash(msg) {
        setToast(msg);
        setTimeout(() => setToast(null), 3000);
    }

    const filtered = useMemo(() => items.filter((t) => {
        if (tab === 'all') return true;
        if (tab === 'none') return t.channels.length === 0;
        return t.channels.includes(tab);
    }), [items, tab]);

    async function send(row, channel) {
        setSending(`${row.id}:${channel}`);
        try {
            const res = await apiFetch(`${remindUrlBase}/${row.id}/remind`, {
                method: 'POST',
                body: JSON.stringify({
                    channels: ['inapp', channel],
                    message: `Halo ${row.pic}, mohon segera tindak lanjuti tiket ${row.id} "${row.subject}" agar tidak melewati batas SLA. Terima kasih.`,
                }),
            });
            setItems((prev) => prev.map((t) => (t.id === row.id ? { ...t, channels: Array.from(new Set([...t.channels, channel])) } : t)));
            flash(res?.message ?? `Teguran ${CHANNEL[channel].label} terkirim.`);
            // Refetch the dashboard so the teguran shows up live in Riwayat.
            onSent?.();
        } catch (e) {
            flash(e.message || 'Gagal mengirim teguran.');
        } finally {
            setSending(null);
        }
    }

    return (
        <div className="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-accent-soft text-blue-600 dark:text-accent-text">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">Notifikasi &amp; Teguran Tiket</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">Tiket aktif yang bisa ditegur ke PIC via Email atau WhatsApp</p>
                    </div>
                </div>
                <div className="flex gap-1 rounded-xl bg-gray-100 dark:bg-panel-3 p-1">
                    {TABS.map(([k, l]) => (
                        <button key={k} onClick={() => setTab(k)} className={`rounded-lg px-3 py-1.5 text-[12px] font-bold transition ${tab === k ? 'bg-white dark:bg-panel-2 text-gray-900 dark:text-ink-1 shadow-sm' : 'text-gray-500 dark:text-ink-2'}`}>{l}</button>
                    ))}
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[860px]">
                    <div className="grid grid-cols-[100px_1fr_130px_120px_190px_96px] gap-3 border-y border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                        <span>ID Tiket</span><span>Subjek</span><span>PIC</span><span>Dibuat</span><span>Teguran</span><span className="text-right">Tegur</span>
                    </div>
                    <div className="max-h-[460px] overflow-y-auto">
                        {filtered.map((t) => (
                            <div key={t.id} onClick={() => onOpenTicket?.(t.id)} className="group grid cursor-pointer grid-cols-[100px_1fr_130px_120px_190px_96px] items-center gap-3 border-b border-gray-50 dark:border-transparent dark:even:bg-white/[0.03] px-6 py-3.5 last:border-0 hover:bg-blue-50/30 dark:hover:bg-panel-hover">
                                <span className="text-left text-[12px] font-bold text-blue-600 dark:text-accent-text group-hover:underline">{t.id}</span>
                                <span className="truncate text-[13px] font-semibold text-gray-900 dark:text-ink-1">{t.subject}</span>
                                <span className="truncate text-[12.5px] text-gray-700 dark:text-ink-2">{t.pic}</span>
                                <span className="text-[12px] text-gray-400 dark:text-ink-3">{t.created}</span>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {t.channels.length === 0 && (
                                        <span className="flex items-center gap-1.5 text-[11.5px] font-semibold text-gray-400 dark:text-ink-3"><span className="h-1.5 w-1.5 rounded-full bg-gray-300" />Belum ditegur</span>
                                    )}
                                    {t.channels.map((c) => (
                                        <span key={c} className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-bold ${CHANNEL[c].style}`}>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={CHANNEL[c].icon} /></svg>
                                            {CHANNEL[c].label}
                                        </span>
                                    ))}
                                </div>
                                <div className="flex justify-end gap-1.5">
                                    <button onClick={(e) => { e.stopPropagation(); send(t, 'email'); }} disabled={sending} title="Kirim teguran via Email" className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-accent-soft text-blue-600 dark:text-accent-text hover:bg-blue-100 dark:hover:bg-panel-hover disabled:opacity-50">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z M4 7l8 6 8-6"/></svg>
                                    </button>
                                    <button onClick={(e) => { e.stopPropagation(); send(t, 'whatsapp'); }} disabled={sending} title="Kirim teguran via WhatsApp" className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-ok-soft text-emerald-600 dark:text-ok-text hover:bg-emerald-100 dark:hover:bg-ok-soft disabled:opacity-50">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H5a1 1 0 0 1-1-1Z M8 9h8 M8 12h5"/></svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                        {filtered.length === 0 && <div className="px-6 py-12 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada tiket pada filter ini.</div>}
                    </div>
                </div>
            </div>

            {toast && (
                <div className="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2 rounded-xl bg-gray-900 dark:bg-panel-selected px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>
            )}
        </div>
    );
}

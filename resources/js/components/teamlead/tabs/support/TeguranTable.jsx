import { useMemo, useState } from 'react';
import { apiFetch } from '../../../../lib/api';

const TABS = [['all', 'Semua'], ['email', 'Email'], ['whatsapp', 'WhatsApp'], ['none', 'Belum']];

const CHANNEL = {
    email: { label: 'Email', style: 'bg-blue-50 text-blue-700', icon: 'M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z M4 7l8 6 8-6' },
    whatsapp: { label: 'WhatsApp', style: 'bg-emerald-50 text-emerald-600', icon: 'M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H5a1 1 0 0 1-1-1Z M8 9h8 M8 12h5' },
};

export default function TeguranTable({ rows = [], remindUrlBase, onOpenTicket }) {
    const [items, setItems] = useState(rows);
    const [tab, setTab] = useState('all');
    const [sending, setSending] = useState(null); // `${id}:${channel}`
    const [toast, setToast] = useState(null);

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
                    channels: [channel],
                    message: `Halo ${row.pic}, mohon segera tindak lanjuti tiket ${row.id} "${row.subject}" agar tidak melewati batas SLA. Terima kasih.`,
                }),
            });
            setItems((prev) => prev.map((t) => (t.id === row.id ? { ...t, channels: Array.from(new Set([...t.channels, channel])) } : t)));
            flash(res?.message ?? `Teguran ${CHANNEL[channel].label} terkirim.`);
        } catch (e) {
            flash(e.message || 'Gagal mengirim teguran.');
        } finally {
            setSending(null);
        }
    }

    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18.4 5.6a8 8 0 0 1 1.9 8.9c-.5 1.2-.3 2.6.5 3.6l.2.3H3l.2-.3c.8-1 1-2.4.5-3.6a8 8 0 0 1 14.7-8.9Z M10 21h4"/></svg>
                    </span>
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900">Notifikasi &amp; Teguran Tiket</h2>
                        <p className="mt-0.5 text-xs text-gray-400">Tiket aktif yang bisa ditegur ke PIC via Email atau WhatsApp</p>
                    </div>
                </div>
                <div className="flex gap-1 rounded-xl bg-gray-100 p-1">
                    {TABS.map(([k, l]) => (
                        <button key={k} onClick={() => setTab(k)} className={`rounded-lg px-3 py-1.5 text-[12px] font-bold transition ${tab === k ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}>{l}</button>
                    ))}
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[860px]">
                    <div className="grid grid-cols-[100px_1fr_130px_120px_190px_96px] gap-3 border-y border-gray-100 bg-gray-50 px-6 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <span>ID Tiket</span><span>Subjek</span><span>PIC</span><span>Dibuat</span><span>Teguran</span><span className="text-right">Tegur</span>
                    </div>
                    <div className="max-h-[460px] overflow-y-auto">
                        {filtered.map((t) => (
                            <div key={t.id} className="grid grid-cols-[100px_1fr_130px_120px_190px_96px] items-center gap-3 border-b border-gray-50 px-6 py-3.5 last:border-0 hover:bg-blue-50/30">
                                <button onClick={() => onOpenTicket?.(t.id)} className="text-left text-[12px] font-bold text-blue-600 hover:underline">{t.id}</button>
                                <span className="truncate text-[13px] font-semibold text-gray-900">{t.subject}</span>
                                <span className="truncate text-[12.5px] text-gray-700">{t.pic}</span>
                                <span className="text-[12px] text-gray-400">{t.created}</span>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {t.channels.length === 0 && (
                                        <span className="flex items-center gap-1.5 text-[11.5px] font-semibold text-gray-400"><span className="h-1.5 w-1.5 rounded-full bg-gray-300" />Belum ditegur</span>
                                    )}
                                    {t.channels.map((c) => (
                                        <span key={c} className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-bold ${CHANNEL[c].style}`}>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={CHANNEL[c].icon} /></svg>
                                            {CHANNEL[c].label}
                                        </span>
                                    ))}
                                </div>
                                <div className="flex justify-end gap-1.5">
                                    <button onClick={() => send(t, 'email')} disabled={sending} title="Kirim teguran via Email" className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 disabled:opacity-50">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z M4 7l8 6 8-6"/></svg>
                                    </button>
                                    <button onClick={() => send(t, 'whatsapp')} disabled={sending} title="Kirim teguran via WhatsApp" className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 disabled:opacity-50">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H5a1 1 0 0 1-1-1Z M8 9h8 M8 12h5"/></svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                        {filtered.length === 0 && <div className="px-6 py-12 text-center text-sm text-gray-400">Tidak ada tiket pada filter ini.</div>}
                    </div>
                </div>
            </div>

            {toast && (
                <div className="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2 rounded-xl bg-gray-900 px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg">{toast}</div>
            )}
        </div>
    );
}

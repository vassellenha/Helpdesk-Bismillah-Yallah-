import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import useLockBodyScroll from '../../lib/useLockBodyScroll';
import { t as trans } from '../../lib/i18n';

const CHANNELS = [
    { key: 'inapp', labelKey: 'teamlead.remind.inapp', hintKey: 'teamlead.remind.inapp_hint' },
    { key: 'email', labelKey: 'teamlead.remind.email', hintKey: 'teamlead.remind.email_hint' },
    { key: 'whatsapp', labelKey: 'teamlead.remind.whatsapp', hintKey: 'teamlead.remind.whatsapp_hint' },
];

/**
 * Compose + send an SLA teguran to a ticket's assigned support agent across
 * the selected channels. Mirrors the modal styling used by the admin/approver
 * consoles (rounded-2xl card, gray overlay, blue primary button).
 */
export default function RemindModal({ ticket, remindUrlBase, onClose, onSent }) {
    useLockBodyScroll();
    const [message, setMessage] = useState(
        trans('teamlead.remind.default_message', { agent: ticket.agent, id: ticket.id, subject: ticket.subject, sla: ticket.sla }),
    );
    const [channels, setChannels] = useState({ inapp: true, email: true, whatsapp: true });
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');

    function toggle(key) {
        setChannels((c) => ({ ...c, [key]: !c[key] }));
    }

    async function submit() {
        const selected = Object.keys(channels).filter((k) => channels[k]);
        if (selected.length === 0) {
            setError(trans('teamlead.remind.no_channel'));
            return;
        }
        if (!message.trim()) {
            setError(trans('teamlead.remind.empty_message'));
            return;
        }

        setSending(true);
        setError('');
        try {
            const res = await apiFetch(`${remindUrlBase}/${ticket.id}/remind`, {
                method: 'POST',
                body: JSON.stringify({ message, channels: selected }),
            });
            onSent?.(res);
        } catch (e) {
            setError(e.message || trans('teamlead.remind.failed'));
            setSending(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onMouseDown={onClose}>
            <div className="liquid-glass-dense w-full max-w-lg overflow-hidden rounded-2xl shadow-2xl" onMouseDown={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-5 py-4">
                    <div>
                        <h2 className="text-[15px] font-bold text-gray-900 dark:text-ink-1">{trans('teamlead.remind.title')}</h2>
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-ink-3">{trans('teamlead.remind.pic_line', { id: ticket.id, agent: ticket.agent })}</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-700 dark:hover:text-ink-1">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div className="space-y-4 px-5 py-4">
                    <div>
                        <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.remind.channel')}</label>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            {CHANNELS.map((ch) => (
                                <button
                                    key={ch.key}
                                    type="button"
                                    onClick={() => toggle(ch.key)}
                                    className={`flex flex-col items-start gap-0.5 rounded-xl border px-3 py-2.5 text-left transition ${
                                        channels[ch.key] ? 'border-blue-500 bg-blue-50 dark:bg-accent-soft' : 'border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 hover:border-gray-300'
                                    }`}
                                >
                                    <span className={`text-[13px] font-bold ${channels[ch.key] ? 'text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}>{trans(ch.labelKey)}</span>
                                    <span className="text-[10px] leading-tight text-gray-400 dark:text-ink-3">{trans(ch.hintKey)}</span>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{trans('teamlead.remind.message')}</label>
                        <textarea
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            rows={5}
                            className="w-full resize-none rounded-xl border border-gray-200 dark:border-edge-strong px-3.5 py-3 text-[13px] text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400"
                        />
                    </div>

                    {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft px-3 py-2 text-[12px] font-medium text-red-600 dark:text-bad-text">{error}</p>}
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-edge px-5 py-3.5">
                    <button onClick={onClose} className="rounded-[10px] px-4 py-2.5 text-[13px] font-semibold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">
                        {trans('teamlead.common.cancel')}
                    </button>
                    <button
                        onClick={submit}
                        disabled={sending}
                        className="rounded-[10px] bg-blue-600 dark:bg-blue-500 px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-blue-700 dark:hover:bg-blue-400 disabled:opacity-60"
                    >
                        {sending ? trans('teamlead.common.sending') : trans('teamlead.remind.send')}
                    </button>
                </div>
            </div>
        </div>
    );
}

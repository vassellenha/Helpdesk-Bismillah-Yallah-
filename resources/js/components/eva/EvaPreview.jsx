import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '../../lib/api';
import { PAGE, PageHeader, Card, Badge, Button, ErrorBanner, inputStyle } from './ui';

/*
 | EVA Preview — mencoba EVA persis seperti yang dilihat karyawan.
 |
 | Memakai endpoint yang sama dengan jalur produksi, jadi setiap pertanyaan di
 | sini benar-benar tercatat di kb_answer_logs. Itu disengaja: mencoba EVA
 | otomatis mengisi daftar pertanyaan yang belum terjawab.
 |
 | Tiga perilaku yang wajib dipertahankan:
 |   - Pertanyaan ambigu → EVA BERTANYA BALIK, tidak menebak.
 |   - Di bawah ambang keyakinan → EVA mengaku belum tahu, tidak mengarang.
 |   - Tiket berhenti di DRAF. Nomor tiket terbit di form Requester.
 */

const QUICK_QUESTIONS = [
    'cara reset password SAP',
    'vpn forticlient tidak bisa connect',
    'tidak bisa login',
    'akun SAP saya terkunci',
];

export default function EvaPreview({ endpoints, thresholds }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [conversationId, setConversationId] = useState(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    const scrollRef = useRef(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages, pending]);

    async function ask(text) {
        const question = (text ?? input).trim();
        if (!question || pending) return;

        setError(null);
        setInput('');
        setMessages((current) => [...current, { key: `u-${current.length}`, from: 'user', text: question }]);
        setPending(true);

        try {
            const reply = await apiFetch(endpoints.ask, {
                method: 'POST',
                body: JSON.stringify({ question, conversation_id: conversationId }),
            });

            setConversationId(reply.conversation_id);
            setMessages((current) => [
                ...current,
                { key: `e-${reply.answer_log_id}-${current.length}`, from: 'eva', question, ...reply },
            ]);
        } catch (e) {
            setError(`EVA tidak bisa menjawab karena galat teknis: ${e.message}`);
        } finally {
            setPending(false);
        }
    }

    async function rate(message, stars) {
        setError(null);
        try {
            await apiFetch(endpoints.rate, {
                method: 'POST',
                body: JSON.stringify({ answer_log_id: message.answer_log_id, stars }),
            });
            markMessage(message.key, { stars });
        } catch (e) {
            // Sekali nilai per jawaban ditegakkan di database; tandai tetap
            // terkunci supaya tampilan tidak menjanjikan hal yang ditolak server.
            markMessage(message.key, { stars: message.stars ?? stars, rateError: e.message });
        }
    }

    async function requestTicketDraft(message) {
        setError(null);
        try {
            const result = await apiFetch(endpoints.ticketDraft, {
                method: 'POST',
                body: JSON.stringify({ answer_log_id: message.answer_log_id, question: message.question }),
            });

            setMessages((current) => [
                ...current,
                { key: `d-${current.length}`, from: 'eva', type: 'ticket_draft', ...result },
            ]);
        } catch (e) {
            setError(`Gagal menyiapkan draf tiket: ${e.message}`);
        }
    }

    const markMessage = (key, patch) =>
        setMessages((current) => current.map((m) => (m.key === key ? { ...m, ...patch } : m)));

    return (
        <div style={PAGE}>
            <PageHeader
                title="EVA Preview"
                subtitle={`Uji jawaban EVA. Di bawah keyakinan ${thresholds.min_confidence}, EVA menyatakan belum memiliki jawabannya.`}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,320px)', gap: '16px', alignItems: 'start' }}>
                <Card style={{ display: 'flex', flexDirection: 'column', height: '620px', overflow: 'hidden' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '13px 17px', borderBottom: '1px solid var(--border-soft)' }}>
                        <span style={{ width: '30px', height: '30px', borderRadius: '10px', background: 'linear-gradient(145deg,var(--blue-500),var(--blue-ink))', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: '12px', fontWeight: 700 }}>
                            E
                        </span>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: '13px', fontWeight: 700 }}>EVA</div>
                            <div style={{ fontSize: '11px', color: 'var(--slate-500)' }}>Asisten Knowledge Base</div>
                        </div>
                    </div>

                    <div ref={scrollRef} style={{ flex: 1, overflowY: 'auto', padding: '18px 17px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        {messages.length === 0 && (
                            <div style={{ fontSize: '13px', color: 'var(--slate-500)', lineHeight: 1.6 }}>
                                Ajukan pertanyaan sebagaimana karyawan menuliskannya. Pertanyaan yang kurang
                                jelas akan dijawab EVA dengan pertanyaan balik.
                            </div>
                        )}

                        {messages.map((message) =>
                            message.from === 'user'
                                ? <UserBubble key={message.key} text={message.text} />
                                : <EvaBubble
                                    key={message.key}
                                    message={message}
                                    thresholds={thresholds}
                                    onClarifyPick={(app) => ask(`${message.question} ${app}`)}
                                    onRate={(stars) => rate(message, stars)}
                                    onTicketDraft={() => requestTicketDraft(message)}
                                />,
                        )}

                        {pending && (
                            <div style={{ fontSize: '12.5px', color: 'var(--slate-500)' }}>
                                <span className="eva-typing-dot">•</span> EVA sedang mencari…
                            </div>
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: '9px', padding: '13px 15px', borderTop: '1px solid var(--border-soft)' }}>
                        <input
                            style={{ ...inputStyle, flex: 1 }}
                            value={input}
                            placeholder="Tulis pertanyaan…"
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') ask(); }}
                        />
                        <Button onClick={() => ask()} disabled={pending || !input.trim()}>Kirim</Button>
                    </div>
                </Card>

                <Card style={{ padding: '16px 18px' }}>
                    <div style={{ fontSize: '13px', fontWeight: 700, marginBottom: '10px' }}>Pertanyaan cepat</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        {QUICK_QUESTIONS.map((question) => (
                            <button
                                key={question}
                                type="button"
                                onClick={() => ask(question)}
                                disabled={pending}
                                style={{
                                    textAlign: 'left', fontSize: '12.5px', padding: '9px 12px', cursor: 'pointer',
                                    borderRadius: 'var(--r-md)', border: '1px solid var(--border)',
                                    background: 'var(--white)', color: 'var(--ink-700)',
                                }}
                            >
                                {question}
                            </button>
                        ))}
                    </div>
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', lineHeight: 1.6, margin: '14px 0 0' }}>
                        Pertanyaan pada halaman ini ikut tercatat di log jawaban, termasuk yang belum
                        terjawab. Pertanyaan yang belum terjawab akan muncul di Coverage Dashboard.
                    </p>
                </Card>
            </div>
        </div>
    );
}

function UserBubble({ text }) {
    return (
        <div style={{ alignSelf: 'flex-end', maxWidth: '78%', background: 'var(--blue-500)', color: '#fff', padding: '10px 13px', borderRadius: '14px 14px 4px 14px', fontSize: '13px', lineHeight: 1.55 }}>
            {text}
        </div>
    );
}

function EvaBubble({ message, thresholds, onClarifyPick, onRate, onTicketDraft }) {
    const base = {
        alignSelf: 'flex-start',
        maxWidth: '84%',
        background: 'var(--white)',
        border: '1px solid var(--border)',
        padding: '12px 14px',
        borderRadius: '14px 14px 14px 4px',
        fontSize: '13px',
        lineHeight: 1.6,
    };

    if (message.type === 'ticket_draft') {
        return (
            <div className="eva-pop" style={base}>
                <div style={{ marginBottom: '8px' }}>{message.draft.note}</div>
                <div style={{ background: 'var(--blue-050)', border: '1px solid var(--blue-050-alt)', borderRadius: '10px', padding: '10px 12px', fontSize: '12.5px' }}>
                    <div style={{ fontWeight: 700, marginBottom: '3px' }}>Draf tiket</div>
                    {message.draft.description}
                </div>
                <a href={message.submit_url} style={{ display: 'inline-block', marginTop: '9px', fontSize: '12.5px', fontWeight: 600 }}>
                    Buka form Buat Tiket →
                </a>
            </div>
        );
    }

    if (message.type === 'clarify') {
        return (
            <div className="eva-pop" style={base}>
                <div>{message.text}</div>
                <div style={{ display: 'flex', gap: '7px', flexWrap: 'wrap', marginTop: '10px' }}>
                    {message.clarify_options.map((app) => (
                        <button
                            key={app}
                            type="button"
                            onClick={() => onClarifyPick(app)}
                            style={{
                                fontSize: '12px', fontWeight: 600, padding: '6px 12px', cursor: 'pointer',
                                borderRadius: '999px', border: '1px solid var(--blue-050-alt)',
                                background: 'var(--blue-050)', color: 'var(--blue-ink)',
                            }}
                        >
                            {app}
                        </button>
                    ))}
                </div>
            </div>
        );
    }

    if (message.type === 'no_answer') {
        return (
            <div className="eva-pop" style={base}>
                <div>{message.text}</div>
                <div style={{ marginTop: '10px' }}>
                    <Button onClick={onTicketDraft}>Siapkan draf tiket</Button>
                </div>
            </div>
        );
    }

    return (
        <div className="eva-pop" style={base}>
            {message.is_hedged && (
                <div style={{ fontSize: '12px', color: 'var(--slate-500)', marginBottom: '6px' }}>
                    Jawaban berikut kemungkinan sesuai, namun tingkat keyakinannya belum penuh.
                </div>
            )}

            <div>{message.text}</div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '9px', marginTop: '10px', flexWrap: 'wrap' }}>
                <Badge tone="blue">{message.hit.title}</Badge>
                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                    keyakinan {message.hit.confidence} (ambang {thresholds.min_confidence})
                </span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '10px' }}>
                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>Membantu?</span>
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        onClick={() => onRate(star)}
                        disabled={Boolean(message.stars)}
                        aria-label={`Beri ${star} bintang`}
                        style={{
                            border: 'none', background: 'none', cursor: message.stars ? 'default' : 'pointer',
                            fontSize: '15px', padding: 0, lineHeight: 1,
                            color: message.stars >= star ? 'var(--amber-500)' : 'var(--border)',
                        }}
                    >
                        ★
                    </button>
                ))}
            </div>

            {message.rateError && (
                <div style={{ fontSize: '11.5px', color: 'var(--red-600)', marginTop: '6px' }}>{message.rateError}</div>
            )}
        </div>
    );
}

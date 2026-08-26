import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '../../lib/api';
import { PAGE, PageHeader, Card, ErrorBanner } from './ui';
import { EvaBubble, UserBubble, QUICK_QUESTIONS } from './widget/EvaChatMessages';
import EvaSourceModal from './widget/EvaSourceModal';

/*
 | EVA Preview — mencoba EVA persis seperti yang dilihat user.
 |
 | Memakai endpoint yang sama dengan jalur produksi, jadi setiap pertanyaan di
 | sini benar-benar tercatat di kb_answer_logs. Itu disengaja: mencoba EVA
 | otomatis mengisi daftar pertanyaan yang belum terjawab.
 |
 | Tiga perilaku yang wajib dipertahankan:
 |   - Pertanyaan ambigu → EVA BERTANYA BALIK, tidak menebak.
 |   - Di bawah ambang keyakinan → EVA mengaku belum tahu, tidak mengarang.
 |   - Tiket berhenti di DRAF. Nomor tiket terbit di form Requester.
 |
 | GELEMBUNG PERCAKAPANNYA DIPINJAM DARI WIDGET PORTAL, bukan ditulis ulang di
 | sini. Sebelumnya layar ini punya renderer sendiri, dan keduanya sudah
 | melenceng dalam empat hal: kotak ulasan tertulis tidak ada, subject tebakan
 | pada draf tiket tidak ditampilkan, kalimat peringatan ragu-ragu berbeda, dan
 | daftar pertanyaan contohnya tidak sama. Semuanya melenceng diam-diam, karena
 | tidak ada satu pun yang memaksa keduanya sejalan.
 |
 | Itu kegagalan yang serius pada layar yang gunanya justru MEMPERLIHATKAN apa
 | yang dialami user: admin menyimpulkan sesuatu di sini, lalu kesimpulan itu
 | tidak berlaku di portal. Dengan memakai komponen yang sama, perbedaan
 | semacam itu tidak mungkin lahir lagi tanpa sengaja.
 |
 | Satu-satunya yang boleh beda: prop `thresholds` menyalakan tampilan angka
 | keyakinan. Itu alat kerja admin dan memang tidak boleh ada di portal.
 */

export default function EvaPreview({ endpoints, thresholds }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [conversationId, setConversationId] = useState(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    // Materi rujukan yang sedang dibuka popup-nya. null = tidak ada popup.
    const [openSource, setOpenSource] = useState(null);
    const scrollRef = useRef(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages, pending]);

    const patchMessage = (key, patch) =>
        setMessages((current) => current.map((m) => (m.key === key ? { ...m, ...patch } : m)));

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
            setError(`EVA belum dapat menjawab karena gangguan teknis: ${e.message}`);
        } finally {
            setPending(false);
        }
    }

    async function rate(message, stars) {
        setError(null);

        // Ditandai lebih dulu supaya bintangnya terkunci seketika — sama persis
        // dengan widget portal. Kalau server menolak, tandanya diganti pesan.
        patchMessage(message.key, { stars });

        try {
            await apiFetch(endpoints.rate, {
                method: 'POST',
                body: JSON.stringify({ answer_log_id: message.answer_log_id, stars }),
            });
        } catch (e) {
            patchMessage(message.key, { rateError: e.message });
        }
    }

    async function attachNote(message, { reason, comment }) {
        if (!reason && !comment) return;

        try {
            await apiFetch(endpoints.note, {
                method: 'POST',
                body: JSON.stringify({ answer_log_id: message.answer_log_id, reason, comment }),
            });
        } catch (e) {
            patchMessage(message.key, { rateError: `Catatan gagal terkirim: ${e.message}` });
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
            setError(`Draf tiket belum dapat disiapkan: ${e.message}`);
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="EVA Preview"
                subtitle={`Percakapan di sini tampil dan berperilaku sama persis dengan widget di portal. Kandidat dengan keyakinan ${thresholds.min_confidence} ke atas pasti dikutip; di bawah itu EVA hanya menjawab bila potongan yang ada berhasil dirangkum, dan selebihnya menawarkan draf tiket.`}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,320px)', gap: '16px', alignItems: 'start' }}>
                {/*
                    Kelas `eva-widget` dipakai apa adanya supaya seluruh gaya
                    gelembung portal berlaku di sini — kelasnya memang di-scope
                    ke sana. Modifier `--inline` hanya mencabut posisi
                    mengambangnya; lihat resources/css/eva.css.
                */}
                <Card style={{ height: '620px', overflow: 'hidden', padding: 0 }}>
                    <div className="eva-app eva-widget eva-widget--inline">
                        <section className="eva-w-panel" aria-label="Percobaan EVA">
                            <div className="eva-w-scroll" ref={scrollRef}>
                                {messages.length === 0 && (
                                    <div className="eva-w-empty">
                                        <p>
                                            Ajukan pertanyaan sebagaimana user menuliskannya. Pertanyaan yang
                                            kurang jelas akan dijawab EVA dengan pertanyaan balik.
                                        </p>
                                    </div>
                                )}

                                {messages.map((message) =>
                                    message.from === 'user' ? (
                                        <UserBubble key={message.key} text={message.text} />
                                    ) : (
                                        <EvaBubble
                                            key={message.key}
                                            message={message}
                                            thresholds={thresholds}
                                            onClarifyPick={(option) => ask(`${message.question} ${option}`)}
                                            onRate={(stars) => rate(message, stars)}
                                            onNote={(note) => attachNote(message, note)}
                                            onTicketDraft={() => requestTicketDraft(message)}
                                            onOpenSource={endpoints.material ? setOpenSource : null}
                                        />
                                    ),
                                )}

                                {pending && (
                                    <div className="eva-w-typing">
                                        <span className="eva-typing-dot">•</span> EVA sedang mencari…
                                    </div>
                                )}
                            </div>

                            <div className="eva-w-compose">
                                <input
                                    name="eva-preview-question"
                                    aria-label="Pertanyaan untuk EVA"
                                    value={input}
                                    placeholder="Tulis pertanyaan…"
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter') ask(); }}
                                />
                                <button
                                    type="button"
                                    className="eva-w-send"
                                    onClick={() => ask()}
                                    disabled={pending || !input.trim()}
                                    aria-label="Kirim pertanyaan"
                                >
                                    ➤
                                </button>
                            </div>
                        </section>
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
                        Pertanyaan pada halaman ini ikut tercatat pada log jawaban, termasuk yang belum
                        terjawab. Pertanyaan yang belum terjawab akan muncul pada Coverage Dashboard.
                    </p>
                </Card>
            </div>

            {openSource && (
                <EvaSourceModal
                    hit={openSource}
                    endpoint={endpoints.material}
                    onClose={() => setOpenSource(null)}
                />
            )}
        </div>
    );
}

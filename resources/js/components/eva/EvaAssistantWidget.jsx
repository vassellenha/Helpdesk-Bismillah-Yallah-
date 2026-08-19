import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '../../lib/api';
import EvaMark from './EvaMark';
import { EvaBubble, UserBubble, QUICK_QUESTIONS } from './widget/EvaChatMessages';

/*
 | Widget EVA — asisten mengambang di pojok kanan bawah portal.
 |
 | Ini permukaan EVA yang pertama kali menyentuh karyawan. Sebelumnya EVA hanya
 | bisa dicoba lewat EVA Preview, yang adalah layar ADMIN — sehingga seluruh
 | angka Analytics, Rating, dan Unanswered Questions berisi percobaan admin atas
 | dirinya sendiri, benar secara teknis tapi kosong secara bisnis.
 |
 | Tiga perilaku yang wajib dipertahankan, sama persis dengan EVA Preview:
 |   - Pertanyaan ambigu → EVA BERTANYA BALIK, tidak menebak.
 |   - Di bawah ambang keyakinan → EVA mengaku belum tahu, tidak mengarang.
 |   - Tiket berhenti di DRAF. Nomor tiket terbit di form Buat Tiket.
 |
 | Percakapan SENGAJA tidak disimpan ke localStorage. `conversation_id` menunjuk
 | baris kb_conversations milik satu penanya; menyimpannya di browser berarti
 | tab yang dibuka besok menyambung percakapan kemarin, dan Log Percakapan
 | melaporkan satu percakapan panjang yang tidak pernah terjadi.
 */

const MAX_QUESTION_LENGTH = 500;

export default function EvaAssistantWidget({ endpoints, offsetBottom = 24 }) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [conversationId, setConversationId] = useState(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    const scrollRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages, pending, open]);

    useEffect(() => {
        if (open) inputRef.current?.focus();
    }, [open]);

    // Esc menutup panel. Widget mengambang di atas seluruh halaman, jadi harus
    // ada jalan keluar yang tidak menuntut membidik tombol kecil di pojok.
    useEffect(() => {
        function onKeyDown(e) {
            if (e.key === 'Escape') setOpen(false);
        }
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

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

        // Ditandai lebih dulu supaya bintangnya terkunci seketika. Kalau server
        // menolak, tandanya diganti pesan — bukan dibiarkan terlihat bisa
        // diklik ulang untuk sesuatu yang pasti ditolak.
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
        <div className="eva-app eva-widget" style={{ '--eva-w-offset': `${offsetBottom}px` }}>
            {open && (
                <section className="eva-w-panel" role="dialog" aria-label="Asisten EVA">
                    <header className="eva-w-head">
                        <EvaMark size={32} className="eva-w-avatar" />
                        <div className="eva-w-head-text">
                            <div className="eva-w-head-title">EVA</div>
                            {/*
                             | Bukan "Asisten Layanan TI". Katalog layanan yang
                             | dilayani EVA sudah melewati batas TI — perangkat,
                             | fasilitas kantor, sampai permintaan akses — dan
                             | label yang menyebut satu domain membuat user
                             | menahan pertanyaan yang sebenarnya bisa dijawab.
                            */}
                            <div className="eva-w-head-sub">Asisten Layanan Helpdesk</div>
                        </div>
                        <button
                            type="button"
                            className="eva-w-close"
                            onClick={() => setOpen(false)}
                            aria-label="Tutup asisten EVA"
                        >
                            ✕
                        </button>
                    </header>

                    {error && (
                        <div className="eva-w-error-banner">
                            <span>{error}</span>
                            <button type="button" onClick={() => setError(null)} aria-label="Tutup pesan galat">
                                ✕
                            </button>
                        </div>
                    )}

                    <div className="eva-w-scroll" ref={scrollRef}>
                        {messages.length === 0 && (
                            <div className="eva-w-empty">
                                <p>
                                    Selamat datang. Silakan sampaikan kendala atau permintaan layanan Anda,
                                    dan saya akan mencarikan panduannya.
                                </p>
                                <div className="eva-w-quick-label">Pertanyaan yang sering diajukan</div>
                                {QUICK_QUESTIONS.map((question) => (
                                    <button
                                        key={question}
                                        type="button"
                                        className="eva-w-quick"
                                        onClick={() => ask(question)}
                                    >
                                        {question}
                                    </button>
                                ))}
                            </div>
                        )}

                        {messages.map((message) =>
                            message.from === 'user' ? (
                                <UserBubble key={message.key} text={message.text} />
                            ) : (
                                <EvaBubble
                                    key={message.key}
                                    message={message}
                                    onClarifyPick={(option) => ask(`${message.question} ${option}`)}
                                    onRate={(stars) => rate(message, stars)}
                                    onNote={(note) => attachNote(message, note)}
                                    onTicketDraft={() => requestTicketDraft(message)}
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
                            ref={inputRef}
                            name="eva-question"
                            aria-label="Pertanyaan untuk EVA"
                            className="eva-w-input"
                            value={input}
                            maxLength={MAX_QUESTION_LENGTH}
                            placeholder="Tulis pertanyaan…"
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') ask();
                            }}
                        />
                        <button
                            type="button"
                            className="eva-w-send"
                            onClick={() => ask()}
                            disabled={pending || !input.trim()}
                            aria-label="Kirim pertanyaan"
                        >
                            {/* SVG, bukan karakter "➤". Glyph dingbat dirender
                                oleh font mana pun yang kebetulan dipilih tiap
                                browser/OS — itulah sebabnya tombol yang sama
                                tampil beda besar di mesin yang berbeda. SVG
                                membawa bentuknya sendiri. */}
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M4 12h15" />
                                <path d="m13 6 6 6-6 6" />
                            </svg>
                        </button>
                    </div>
                </section>
            )}

            <button
                type="button"
                className={`eva-w-launcher${open ? '' : ' eva-w-launcher--mark'}`}
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-label={open ? 'Tutup asisten EVA' : 'Buka asisten EVA'}
                title="Tanya EVA"
            >
                {/*
                 | Saat tertutup, mark EVA menjadi WAJAH tombolnya sendiri —
                 | kelas modifier mematikan latar biru bawaan tombol. Tanpa itu,
                 | squircle mark duduk di atas lingkaran biru lain dan terlihat
                 | seperti ikon yang ditempel.
                 |
                 | Saat terbuka, tombol kembali jadi lingkaran biru berisi ✕:
                 | fungsinya sudah bukan "ini EVA" melainkan "tutup".
                */}
                {open ? '✕' : <EvaMark size={56} />}
            </button>
        </div>
    );
}

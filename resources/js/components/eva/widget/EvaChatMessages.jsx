import { useState } from 'react';

/*
 | Gelembung percakapan widget EVA.
 |
 | Dipisah dari EvaAssistantWidget supaya berkas induknya tetap mengurus SATU
 | hal: keadaan percakapan dan panggilan jaringan. Di sini murni tampilan.
 |
 | Empat bentuk balasan yang wajib dibedakan — ketiganya datang dari
 | EvaReply::TYPE_* di server, jadi jangan menebak dari isi teksnya:
 |   answer       → EVA menjawab, sumber dan keyakinannya ditampilkan
 |   clarify      → EVA BERTANYA BALIK; pilihan jadi chip yang bisa diklik
 |   no_answer    → EVA mengaku belum tahu, menawarkan draf tiket
 |   ticket_draft → hasil penyiapan draf (dibuat di klien, bukan dari server)
 */

const LOW_RATING_MAX = 3;

const FEEDBACK_REASONS = [
    'Jawaban tidak sesuai pertanyaan',
    'Langkahnya kurang lengkap',
    'Sudah tidak berlaku',
    'Sulit dipahami',
];

export function UserBubble({ text }) {
    return <div className="eva-w-bubble eva-w-bubble-user">{text}</div>;
}

export function EvaBubble({ message, thresholds, onClarifyPick, onRate, onNote, onTicketDraft }) {
    if (message.type === 'ticket_draft') {
        return <TicketDraftBubble message={message} />;
    }

    if (message.type === 'clarify') {
        return <ClarifyBubble message={message} onPick={onClarifyPick} />;
    }

    if (message.type === 'no_answer') {
        return (
            <div className="eva-w-bubble eva-w-bubble-eva eva-pop">
                <div>{message.text}</div>
                <button type="button" className="eva-w-btn" onClick={onTicketDraft}>
                    Siapkan draf tiket
                </button>
            </div>
        );
    }

    return <AnswerBubble message={message} thresholds={thresholds} onRate={onRate} onNote={onNote} />;
}

function TicketDraftBubble({ message }) {
    return (
        <div className="eva-w-bubble eva-w-bubble-eva eva-pop">
            <div>{message.draft.note}</div>
            <div className="eva-w-draft">
                <div className="eva-w-draft-title">Draf tiket</div>
                <div>{message.draft.description}</div>
                {message.draft.subject && (
                    <div className="eva-w-draft-subject">
                        {message.draft.subject.subject} · {message.draft.subject.service}
                    </div>
                )}
            </div>
            <a className="eva-w-link" href={message.submit_url}>
                Buka form Buat Tiket →
            </a>
        </div>
    );
}

function ClarifyBubble({ message, onPick }) {
    return (
        <div className="eva-w-bubble eva-w-bubble-eva eva-pop">
            <div>{message.text}</div>
            <div className="eva-w-chips">
                {message.clarify_options.map((option) => (
                    <button key={option} type="button" className="eva-w-chip" onClick={() => onPick(option)}>
                        {option}
                    </button>
                ))}
            </div>
        </div>
    );
}

function AnswerBubble({ message, thresholds, onRate, onNote }) {
    return (
        <div className="eva-w-bubble eva-w-bubble-eva eva-pop">
            {message.is_hedged && (
                <div className="eva-w-hedge">
                    Jawaban berikut kemungkinan sesuai, namun tingkat keyakinannya belum penuh.
                </div>
            )}

            <div>{message.text}</div>

            <div className="eva-w-source">
                <span className="eva-w-source-tag">{message.hit.title}</span>
                <span className="eva-w-muted">
                    keyakinan {message.hit.confidence} (ambang {thresholds.min_confidence})
                </span>
            </div>

            <RatingRow message={message} onRate={onRate} onNote={onNote} />
        </div>
    );
}

/**
 * Bintang, lalu kotak catatan bila nilainya rendah.
 *
 * Kotak catatan hanya muncul untuk nilai rendah dengan alasan yang disengaja:
 * bintang lima tidak menghasilkan pekerjaan bagi siapa pun, sedangkan bintang
 * satu tanpa keterangan menyisakan pertanyaan "apanya yang salah" yang tidak
 * bisa dijawab siapa pun. Meminta catatan pada keduanya membuat orang berhenti
 * memberi nilai sama sekali.
 *
 * Bintangnya dikirim LEBIH DULU dan langsung terkunci. Catatan menyusul sebagai
 * penyempurna baris yang sama — kalau karyawan menutup widget tanpa menulis
 * apa pun, nilainya tetap tercatat.
 */
function RatingRow({ message, onRate, onNote }) {
    const [note, setNote] = useState('');
    const [reason, setReason] = useState(null);
    const [noteSent, setNoteSent] = useState(false);

    const rated = Boolean(message.stars);
    const wantsNote = rated && message.stars <= LOW_RATING_MAX && !noteSent && !message.rateError;

    function submitNote() {
        setNoteSent(true);
        onNote({ reason, comment: note.trim() || null });
    }

    return (
        <div className="eva-w-rating">
            <div className="eva-w-stars">
                <span className="eva-w-muted">Membantu?</span>
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        className="eva-w-star"
                        data-on={message.stars >= star ? 'yes' : 'no'}
                        disabled={rated}
                        aria-label={`Beri ${star} bintang`}
                        onClick={() => onRate(star)}
                    >
                        ★
                    </button>
                ))}
            </div>

            {wantsNote && (
                <div className="eva-w-note">
                    <div className="eva-w-chips">
                        {FEEDBACK_REASONS.map((item) => (
                            <button
                                key={item}
                                type="button"
                                className="eva-w-chip"
                                data-on={reason === item ? 'yes' : 'no'}
                                onClick={() => setReason(reason === item ? null : item)}
                            >
                                {item}
                            </button>
                        ))}
                    </div>
                    <textarea
                        className="eva-w-textarea"
                        name="eva-feedback-note"
                        aria-label="Catatan untuk penilaian jawaban EVA"
                        rows={2}
                        value={note}
                        maxLength={2000}
                        placeholder="Bagian mana yang belum sesuai? (opsional)"
                        onChange={(e) => setNote(e.target.value)}
                    />
                    <div className="eva-w-note-actions">
                        <button type="button" className="eva-w-btn" onClick={submitNote} disabled={!reason && !note.trim()}>
                            Kirim catatan
                        </button>
                        <button type="button" className="eva-w-btn-ghost" onClick={() => setNoteSent(true)}>
                            Lewati
                        </button>
                    </div>
                </div>
            )}

            {noteSent && !message.rateError && (
                <div className="eva-w-muted">Terima kasih. Masukan Anda sudah kami catat.</div>
            )}

            {message.rateError && <div className="eva-w-error">{message.rateError}</div>}
        </div>
    );
}

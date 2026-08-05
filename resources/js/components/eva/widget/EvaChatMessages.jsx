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

/**
 * Pertanyaan contoh, dipakai widget portal MAUPUN EVA Preview.
 *
 * Satu daftar, bukan dua. Sebelumnya keduanya punya salinan sendiri dan sudah
 * melenceng — Preview memuat satu pertanyaan yang tidak ada di portal. Admin
 * yang menguji lewat Preview lalu menyimpulkan hal tentang perilaku EVA yang
 * tidak pernah dialami user.
 */
export const QUICK_QUESTIONS = [
    'cara reset password SAP',
    'vpn forticlient tidak bisa connect',
    'akun SAP saya terkunci',
];

const FEEDBACK_REASONS = [
    'Jawaban tidak sesuai pertanyaan',
    'Langkahnya kurang lengkap',
    'Sudah tidak berlaku',
    'Sulit dipahami',
];

export function UserBubble({ text }) {
    return <div className="eva-w-bubble eva-w-bubble-user">{text}</div>;
}

/**
 * @param thresholds  Hanya diisi EVA Preview. Kalau ada, angka keyakinan dan
 *                    ambangnya ikut ditampilkan — lihat alasannya di atas
 *                    AnswerBubble. Widget portal tidak mengirimnya, jadi
 *                    tampilannya di sana tidak berubah sedikit pun.
 */
export function EvaBubble({ message, thresholds = null, onClarifyPick, onRate, onNote, onTicketDraft }) {
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

/*
 | Angka keyakinan dan ambang SENGAJA tidak ditampilkan di sini.
 |
 | Keduanya alat kerja admin: gunanya membandingkan satu jawaban dengan
 | jawaban lain untuk memutuskan materi mana yang perlu diperbaiki. Bagi
 | karyawan yang sedang mencari cara reset password, "keyakinan 97 (ambang
 | 55)" tidak mengubah satu pun keputusannya — dan angka yang tidak bisa
 | ditindaklanjuti justru mengundang salah tafsir, seolah jawabannya baru
 | benar 97%.
 |
 | Yang TETAP ditampilkan: judul sumber, supaya karyawan tahu panduan ini
 | berasal dari materi resmi yang mana, dan catatan ragu-ragu di bawah —
 | itu peringatan yang bisa ditindaklanjuti ("periksa lagi"), bukan skor.
 |
 | Tempat melihat angkanya tetap ada: EVA Preview di konsol admin — dan itulah
 | satu-satunya guna prop `thresholds`. Ia SATU-SATUNYA perbedaan yang boleh ada
 | antara kedua permukaan; sisanya wajib identik, karena Preview tidak ada
 | gunanya kalau ia memperlihatkan sesuatu yang berbeda dari yang dialami user.
*/
function AnswerBubble({ message, thresholds, onRate, onNote }) {
    return (
        <div className="eva-w-bubble eva-w-bubble-eva eva-pop">
            {message.is_hedged && (
                <div className="eva-w-hedge">
                    Jawaban berikut kemungkinan sesuai, namun sebaiknya Anda periksa kembali.
                </div>
            )}

            <div>{message.text}</div>

            <div className="eva-w-source">
                <span className="eva-w-source-tag">{message.hit.title}</span>
                {thresholds && (
                    <span className="eva-w-muted">
                        keyakinan {message.hit.confidence} (ambang {thresholds.min_confidence})
                    </span>
                )}
            </div>

            <RatingRow message={message} onRate={onRate} onNote={onNote} />
        </div>
    );
}

/**
 * Bintang, lalu kotak ulasan.
 *
 * Kotak ulasannya SELALU tampil di bawah bintang, bukan hanya saat nilainya
 * rendah. Ulasan bintang lima juga berguna: ia menandai materi yang sudah
 * benar, dan Rating & Feedback menyaring tanggapan berdasarkan ada-tidaknya
 * kalimat, bukan berdasarkan bintangnya.
 *
 * Yang tetap bergantung pada nilai adalah CHIP ALASAN — isinya keluhan
 * ("langkahnya kurang lengkap"), jadi menyodorkannya pada bintang lima hanya
 * membingungkan.
 *
 * Bintangnya dikirim LEBIH DULU dan langsung terkunci; ulasan menyusul sebagai
 * penyempurna baris yang sama. Kalau karyawan menutup widget tanpa menulis apa
 * pun, nilainya tetap tercatat. Bintang wajib duluan karena `kb_answer_ratings.
 * stars` NOT NULL — tanpa bintang, tidak ada baris yang bisa ditempeli ulasan.
 */
function RatingRow({ message, onRate, onNote }) {
    const [note, setNote] = useState('');
    const [reason, setReason] = useState(null);
    // null | 'sent' | 'skipped' — DIBEDAKAN, bukan satu boolean. "Lewati" tidak
    // mengirim apa pun, jadi menjawabnya dengan "ulasan Anda sudah kami catat"
    // adalah kalimat yang tidak benar.
    const [noteState, setNoteState] = useState(null);

    const rated = Boolean(message.stars);
    const isLowRating = rated && message.stars <= LOW_RATING_MAX;
    const wantsNote = noteState === null && !message.rateError;
    const canSend = rated && Boolean(reason || note.trim());

    function submitNote() {
        setNoteState('sent');
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
                    {isLowRating && (
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
                    )}
                    <textarea
                        className="eva-w-textarea"
                        name="eva-feedback-note"
                        aria-label="Ulasan Anda tentang jawaban EVA"
                        rows={2}
                        value={note}
                        maxLength={2000}
                        placeholder={
                            isLowRating
                                ? 'Bagian mana yang belum sesuai?'
                                : 'Tulis ulasan Anda tentang jawaban ini…'
                        }
                        onChange={(e) => setNote(e.target.value)}
                    />
                    <div className="eva-w-note-actions">
                        <button type="button" className="eva-w-btn" onClick={submitNote} disabled={!canSend}>
                            Kirim ulasan
                        </button>
                        <button type="button" className="eva-w-btn-ghost" onClick={() => setNoteState('skipped')}>
                            Lewati
                        </button>
                    </div>
                    {!rated && (note.trim() || reason) && (
                        <div className="eva-w-muted">Beri bintang dulu, lalu ulasannya bisa dikirim.</div>
                    )}
                </div>
            )}

            {noteState === 'sent' && !message.rateError && (
                <div className="eva-w-muted">Terima kasih. Ulasan Anda sudah kami catat.</div>
            )}

            {message.rateError && <div className="eva-w-error">{message.rateError}</div>}
        </div>
    );
}

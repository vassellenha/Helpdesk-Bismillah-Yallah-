import { useEffect, useMemo, useRef, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Modal, Pagination, usePagination, inputStyle,
} from './ui';

/*
 | Log Percakapan.
 |
 | Gunanya membaca kalimat aslinya, bukan statistik — di mana EVA salah paham,
 | di mana jawabannya benar tapi tidak menolong, di mana karyawan menyerah.
 |
 | BENTUK LAYAR — kenapa bukan tabel dengan baris yang mekar.
 |
 | Versi pertama membuka isi percakapan sebagai baris tambahan DI DALAM tabel.
 | Percakapan panjang membuat baris itu setinggi ribuan piksel, mendorong sisa
 | daftar keluar layar. Akibatnya bukan cuma perlu menggulir: admin kehilangan
 | tempatnya berada, dan membandingkan dua percakapan berarti menutup yang satu
 | lalu mencari lagi yang lain dari awal.
 |
 | Isi yang panjangnya tak tentu tidak boleh menumpang di aliran yang sama
 | dengan daftarnya. Gantinya master–detail bertinggi tetap: daftar di kiri
 | tetap diam dan tetap terlihat, percakapan bergulir di panelnya sendiri.
 | Pindah ke percakapan berikutnya jadi satu klik — atau satu tekan panah.
 */

const ALL = 'Semua';

/** Tinggi tetap kedua panel. Inilah yang membuat halaman berhenti memanjang. */
const PANEL_HEIGHT = '560px';

/**
 * Percakapan per halaman.
 *
 * Panel kiri memang bergulir, tapi menggulir 90 baris untuk mencari yang di
 * bawah tetap melelahkan — dan gulir tidak memberi tahu ADA BERAPA lagi.
 */
const PER_PAGE = 12;

const OUTCOME_LABEL = {
    answered: 'Terjawab',
    ticket: 'Berakhir tiket',
    abandoned: 'Ditinggalkan',
    open: 'Berjalan',
};

const OUTCOME_TONE = {
    answered: 'green',
    ticket: 'amber',
    abandoned: 'red',
    open: 'neutral',
};

const CLAMP_2 = {
    display: '-webkit-box',
    WebkitLineClamp: 2,
    WebkitBoxOrient: 'vertical',
    overflow: 'hidden',
};

export default function EvaConversationLog({ conversations: initialConversations, stats: initialStats, showing }) {
    const [query, setQuery] = useState('');
    const [outcome, setOutcome] = useState(ALL);
    const [selectedId, setSelectedId] = useState(null);

    // State, bukan langsung membaca prop: percakapan yang dihapus harus bisa
    // lenyap dari daftar tanpa memuat ulang seluruh halaman.
    const [conversations, setConversations] = useState(initialConversations);
    const [stats, setStats] = useState(initialStats);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return conversations.filter((conversation) => {
            if (outcome !== ALL && conversation.outcome !== outcome) return false;
            if (!needle) return true;

            return `${conversation.opening_question} ${conversation.requester_name}`.toLowerCase().includes(needle);
        });
    }, [conversations, query, outcome]);

    // Dicari di SELURUH daftar, bukan di `visible`: percakapan yang sedang
    // dibaca tidak boleh lenyap dari panel kanan hanya karena saringan berubah.
    const selected = conversations.find((c) => c.id === selectedId) ?? null;
    const pager = usePagination(visible, PER_PAGE, `${query}|${outcome}`);

    useArrowNavigation({ visible, selectedId, onSelect: setSelectedId });

    /*
     | Halaman MENGIKUTI pilihan, bukan sebaliknya.
     |
     | Panah menyisir seluruh hasil saringan, bukan cuma halaman yang sedang
     | terbuka — kalau tidak, menekan panah di baris terakhir tidak melakukan
     | apa-apa dan terbaca seperti kerusakan. Begitu pilihannya menyeberang ke
     | halaman lain, halamannya ikut pindah.
     |
     | Sengaja hanya bergantung pada `selectedId`: kalau `visible` ikut jadi
     | dependensi, mengubah saringan akan melompat balik ke halaman percakapan
     | yang kebetulan masih terpilih — membatalkan kembalinya ke halaman 1.
     */
    useEffect(() => {
        if (selectedId == null) return;

        const index = visible.findIndex((c) => c.id === selectedId);

        if (index >= 0) pager.setPage(Math.floor(index / PER_PAGE) + 1);
    }, [selectedId]);

    /**
     * Menghapus TRANSKRIP percakapan, bukan riwayat pertanyaannya.
     *
     * kb_answer_logs (sumber Analytics, Unanswered Questions, deflection rate)
     * tidak disentuh — lihat ConversationController::destroy. Yang hilang dari
     * layar ini murni kalimat yang pernah diucapkan, bukan angka apa pun.
     */
    async function confirmDelete() {
        const target = deleting;

        setBusy(true);
        setError(null);
        try {
            await apiFetch(`/eva/api/conversations/${target.id}`, { method: 'DELETE' });

            setConversations((rows) => rows.filter((c) => c.id !== target.id));
            setStats((current) => ({
                ...current,
                total: current.total - 1,
                [target.outcome]: (current[target.outcome] ?? 0) - 1,
            }));

            if (selectedId === target.id) setSelectedId(null);
            setDeleting(null);
        } catch (e) {
            setError(`Gagal menghapus percakapan: ${e.message}`);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Log Percakapan"
                subtitle={`Menampilkan ${showing} percakapan terbaru dari ${stats.total} percakapan yang tercatat.`}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <StatRow>
                <StatTile label="TOTAL PERCAKAPAN" value={stats.total} />
                <StatTile label="TERJAWAB" value={stats.answered} tone="var(--green-500)" />
                <StatTile label="BERLANJUT KE TIKET" value={stats.ticket} hint="EVA tidak menemukan jawaban" tone="var(--amber-500)" />
                <StatTile label="DITINGGALKAN" value={stats.abandoned} hint="tidak dilanjutkan penanya" />
            </StatRow>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,0.9fr) minmax(0,1.1fr)', gap: '16px', alignItems: 'stretch' }}>
                <ConversationList
                    conversations={conversations}
                    visible={visible}
                    pager={pager}
                    query={query}
                    onQuery={setQuery}
                    outcome={outcome}
                    onOutcome={setOutcome}
                    selectedId={selectedId}
                    onSelect={setSelectedId}
                />
                <TranscriptPanel
                    conversation={selected}
                    onClear={() => setSelectedId(null)}
                    onDelete={() => setDeleting(selected)}
                />
            </div>

            {/*
                Dialognya menyebut jumlah giliran, bukan cuma "yakin?": itu satu
                satunya cara membedakan percakapan tiga baris dari percakapan
                lima belas giliran sebelum admin menekan Hapus.

                Kalimat kedua ada supaya kekhawatiran yang wajar — "apa angka
                Analytics ikut berubah?" — terjawab SEBELUM ditanyakan: tidak,
                karena ini menghapus transkripnya, bukan kb_answer_logs.
            */}
            {deleting && (
                <Modal title="Hapus percakapan ini?" onClose={() => (busy ? null : setDeleting(null))}>
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: 0, fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-tint)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            “{deleting.opening_question}” — {deleting.turn_count} giliran, {deleting.started_at}
                        </p>
                        <p style={{ margin: '12px 0 0', fontSize: '12.5px', lineHeight: 1.6, color: 'var(--ink-700)' }}>
                            Transkripnya akan terhapus dan tidak bisa dikembalikan. Riwayat pertanyaan
                            pada Analytics dan Unanswered Questions tidak ikut berubah.
                        </p>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', padding: '14px 20px 16px' }}>
                        <Button variant="ghost" onClick={() => setDeleting(null)} disabled={busy}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={confirmDelete} disabled={busy}>
                            {busy ? 'Menghapus…' : 'Hapus'}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
}

/**
 * Panah atas/bawah memindah percakapan.
 *
 * Layar ini dipakai untuk MENYISIR puluhan percakapan berturut-turut, dan
 * mengangkat tangan ke mouse tiap kali memutus ritme itu. Ditulis di window
 * supaya tidak menuntut panelnya difokuskan dulu — dengan penjagaan agar tidak
 * membajak panah saat admin sedang mengetik di kotak cari.
 */
function useArrowNavigation({ visible, selectedId, onSelect }) {
    useEffect(() => {
        function onKeyDown(event) {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) return;
            if (visible.length === 0) return;

            event.preventDefault();

            const current = visible.findIndex((c) => c.id === selectedId);

            if (current === -1) {
                onSelect(visible[0].id);

                return;
            }

            const next = event.key === 'ArrowDown'
                ? Math.min(current + 1, visible.length - 1)
                : Math.max(current - 1, 0);

            onSelect(visible[next].id);
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [visible, selectedId, onSelect]);
}

function ConversationList({ conversations, visible, pager, query, onQuery, outcome, onOutcome, selectedId, onSelect }) {
    const listRef = useRef(null);

    // Menggulir pilihan ke dalam pandangan — tanpa ini navigasi panah "hilang"
    // begitu pilihannya lewat dari batas panel.
    useEffect(() => {
        if (selectedId == null) return;

        listRef.current
            ?.querySelector(`[data-conversation="${selectedId}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [selectedId]);

    return (
        <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{visible.length} dari {conversations.length}</span>}>
                Percakapan
            </CardTitle>

            <div style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-soft)', display: 'flex', flexDirection: 'column', gap: '9px' }}>
                <input
                    style={{ ...inputStyle, padding: '7px 10px', fontSize: '12.5px' }}
                    placeholder="Cari pertanyaan atau nama penanya…"
                    value={query}
                    onChange={(event) => onQuery(event.target.value)}
                />
                <select
                    style={{ ...inputStyle, padding: '7px 10px', fontSize: '12.5px' }}
                    value={outcome}
                    onChange={(event) => onOutcome(event.target.value)}
                >
                    <option value={ALL}>{ALL} hasil</option>
                    {Object.entries(OUTCOME_LABEL).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
            </div>

            <div ref={listRef} style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>
                {visible.length === 0 ? (
                    <EmptyState>
                        {conversations.length === 0
                            ? 'Belum ada percakapan. Ajukan pertanyaan pada menu EVA Preview.'
                            : 'Tidak ada percakapan yang cocok dengan saringan ini.'}
                    </EmptyState>
                ) : (
                    pager.slice.map((conversation) => (
                        <ConversationRow
                            key={conversation.id}
                            conversation={conversation}
                            active={conversation.id === selectedId}
                            onClick={() => onSelect(conversation.id)}
                        />
                    ))
                )}
            </div>

            <Pagination {...pager} onPage={pager.setPage} compact />
        </Card>
    );
}

function ConversationRow({ conversation, active, onClick }) {
    return (
        <button
            type="button"
            data-conversation={conversation.id}
            onClick={onClick}
            aria-pressed={active}
            style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                cursor: 'pointer',
                font: 'inherit',
                padding: '11px 14px',
                border: 'none',
                borderBottom: '1px solid var(--border-soft)',
                borderLeft: `3px solid ${active ? 'var(--blue-500)' : 'transparent'}`,
                background: active ? 'var(--blue-050)' : 'transparent',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: '7px', marginBottom: '5px' }}>
                <Badge tone={OUTCOME_TONE[conversation.outcome] ?? 'neutral'}>
                    {OUTCOME_LABEL[conversation.outcome] ?? conversation.outcome}
                </Badge>
                <span style={{ fontSize: '11px', color: 'var(--slate-500)' }}>
                    {conversation.turn_count} giliran · {conversation.started_at}
                </span>
            </div>
            <div style={{ fontSize: '13px', fontWeight: 600, lineHeight: 1.45, color: 'var(--ink-900)', ...CLAMP_2 }}>
                {conversation.opening_question}
            </div>
            <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>
                {conversation.requester_name}
                {conversation.department && ` · ${conversation.department}`}
            </div>
        </button>
    );
}

function TranscriptPanel({ conversation, onClear, onDelete }) {
    const bodyRef = useRef(null);

    // Percakapan baru selalu dibaca dari atas. Tanpa ini, pindah percakapan
    // mendarat di posisi gulir percakapan sebelumnya — biasanya di tengah.
    useEffect(() => {
        if (bodyRef.current) bodyRef.current.scrollTop = 0;
    }, [conversation?.id]);

    if (!conversation) {
        return (
            <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
                <CardTitle>Isi percakapan</CardTitle>
                <div style={{ flex: 1, display: 'flex', alignItems: 'center' }}>
                    <EmptyState>
                        Pilih percakapan pada daftar di sebelah kiri untuk membacanya. Tombol panah
                        ↑ ↓ dapat digunakan untuk berpindah.
                    </EmptyState>
                </div>
            </Card>
        );
    }

    return (
        <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
            <CardTitle
                right={
                    <span style={{ display: 'flex', gap: '8px' }}>
                        <button type="button" onClick={onDelete} style={{ ...closeButtonStyle, color: 'var(--red-600)' }}>
                            Hapus
                        </button>
                        <button type="button" onClick={onClear} style={closeButtonStyle}>Tutup</button>
                    </span>
                }
            >
                <span style={CLAMP_2}>{conversation.opening_question}</span>
            </CardTitle>

            <div style={{ padding: '11px 18px', borderBottom: '1px solid var(--border-soft)', display: 'flex', alignItems: 'center', gap: '9px', flexWrap: 'wrap' }}>
                <Badge tone={OUTCOME_TONE[conversation.outcome] ?? 'neutral'}>
                    {OUTCOME_LABEL[conversation.outcome] ?? conversation.outcome}
                </Badge>
                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                    {conversation.requester_name}
                    {conversation.department && ` · ${conversation.department}`}
                    {' · '}{conversation.turn_count} giliran
                    {' · '}{conversation.started_at}
                    {conversation.confidence != null && ` · keyakinan tertinggi ${conversation.confidence}`}
                </span>
                {/*
                    Nomor tiket sudah lama dikirim controller tapi tidak pernah
                    ditampilkan. Padahal inilah yang menyambungkan percakapan yang
                    gagal ke tiket yang lahir darinya.
                */}
                {conversation.ticket_reference && (
                    <Badge tone="blue">Tiket {conversation.ticket_reference}</Badge>
                )}
            </div>

            <div ref={bodyRef} style={{ flex: 1, overflowY: 'auto', minHeight: 0, padding: '16px 18px', background: 'var(--canvas)' }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '9px' }}>
                    {conversation.turns.map((turn) => (
                        <Turn key={turn.id} turn={turn} />
                    ))}
                </div>
            </div>
        </Card>
    );
}

function Turn({ turn }) {
    const isUser = turn.role === 'user';

    return (
        <div
            style={{
                alignSelf: isUser ? 'flex-end' : 'flex-start',
                maxWidth: '86%',
                fontSize: '13px',
                lineHeight: 1.6,
                padding: '10px 13px',
                borderRadius: isUser ? '14px 14px 4px 14px' : '14px 14px 14px 4px',
                background: isUser ? 'var(--blue-500)' : 'var(--white)',
                color: isUser ? 'var(--on-accent)' : 'var(--ink-800)',
                border: isUser ? 'none' : '1px solid var(--border)',
            }}
        >
            {turn.message}

            {!isUser && (turn.source_label || turn.is_clarifying) && (
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center', marginTop: '8px', flexWrap: 'wrap' }}>
                    {turn.is_clarifying && <Badge tone="blue">Bertanya balik</Badge>}
                    {turn.source_label && (
                        <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                            {turn.source_label}
                            {turn.confidence != null && ` · keyakinan ${turn.confidence}`}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

const closeButtonStyle = {
    padding: '6px 11px',
    fontSize: '12px',
    fontWeight: 600,
    borderRadius: 'var(--r-md)',
    border: '1px solid var(--border)',
    background: 'var(--white)',
    color: 'var(--ink-700)',
    cursor: 'pointer',
    whiteSpace: 'nowrap',
};

import { useMemo, useState } from 'react';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge,
    EmptyState, Pagination, usePagination, inputStyle,
} from './ui';

/*
 | Rating & Feedback.
 |
 | Semua angka diagregasi dari kb_answer_ratings saat request — inilah yang
 | membuat bintang dari karyawan benar-benar sampai ke statistik. Di mockup,
 | "helpful" adalah kolom beku di artikel, sehingga penilaian pengguna tidak
 | pernah mengubah apa pun.
 |
 | BENTUK LAYAR — kenapa bukan tabel panjang seperti sebelumnya.
 |
 | Versi pertama menaruh seluruh materi sebagai satu tabel yang tumbuh tanpa
 | batas. Pada 100 artikel + FAQ, tabel itu setinggi ribuan piksel dan mendorong
 | tanggapan tertulis karyawan — satu-satunya isi yang menjelaskan KENAPA sebuah
 | materi dinilai buruk — jauh ke bawah lipatan layar. Yang paling berguna jadi
 | yang paling tidak terlihat.
 |
 | CATATAN PENTING — panel tanggapan masih kosong SELAMANYA hari ini.
 |
 | `kb_answer_ratings` punya kolom `comment` dan `reason`, dan endpoint
 | `preview/rate` sudah memvalidasi keduanya. Tapi EvaPreview hanya mengirim
 | `stars` — tidak ada satu pun kotak teks di seluruh aplikasi yang bisa mengisi
 | kolom itu. Jadi seluruh jalur tanggapan di layar ini (lencana "N tanggapan",
 | daftar di panel kanan) sudah benar tapi belum punya sumber. Yang kurang cuma
 | kotak catatan di EvaPreview; backend-nya tidak perlu diubah sama sekali.
 |
 | Gantinya master–detail dengan TINGGI TETAP: kedua panel bergulir di dalam
 | dirinya sendiri, jadi tinggi halaman sama saja untuk 5 materi maupun 500.
 | Memilih materi di kiri memunculkan tanggapan atas materi itu di kanan —
 | angka dan alasannya akhirnya terbaca berdampingan, bukan sebagai dua daftar
 | yang harus dicocokkan sendiri oleh admin.
 */

/** Rata-rata di bawah ini ditandai merah: materi yang perlu ditinjau. */
const POOR_AVERAGE = 3;

/**
 * Di bawah jumlah penilai ini, rata-rata belum berarti apa-apa.
 *
 * Karena daftar diurutkan terburuk-dulu, satu penilaian 1★ langsung melompat ke
 * puncak dan terbaca seperti krisis. Barisnya tetap ditampilkan — menyembunyikan
 * keluhan nyata lebih buruk — tapi diberi tanda supaya tidak dikira gawat.
 */
const THIN_EVIDENCE = 3;

/** Tinggi tetap kedua panel. Inilah yang membuat halaman berhenti memanjang. */
const PANEL_HEIGHT = '520px';

/** Materi per halaman di panel kiri. */
const PER_PAGE = 12;

const CLAMP_2 = {
    display: '-webkit-box',
    WebkitLineClamp: 2,
    WebkitBoxOrient: 'vertical',
    overflow: 'hidden',
};

const isPoor = (source) => source.avg < POOR_AVERAGE;

const starTone = (stars) =>
    stars >= 4 ? 'var(--green-500)' : stars === 3 ? 'var(--amber-500)' : 'var(--red-600)';

export default function EvaRatingFeedback({ summary, distribution, sources, comments, helpfulThreshold }) {
    // Kalau tidak ada satu pun materi bermasalah, membuka layar pada saringan
    // "perlu ditinjau" hanya memperlihatkan daftar kosong yang terbaca seperti
    // kerusakan. Saringannya menyesuaikan keadaan nyata.
    const [scope, setScope] = useState(() => (sources.some(isPoor) ? 'review' : 'all'));
    const [query, setQuery] = useState('');
    const [selectedKey, setSelectedKey] = useState(null);

    const visible = useMemo(() => {
        const base = scope === 'review' ? sources.filter(isPoor) : sources;
        const needle = query.trim().toLowerCase();

        return needle ? base.filter((s) => s.title.toLowerCase().includes(needle)) : base;
    }, [sources, scope, query]);

    // Dicari di SELURUH sources, bukan di `visible`: materi yang sedang dibaca
    // tidak boleh lenyap dari panel kanan hanya karena saringan kiri berubah.
    const selected = sources.find((s) => s.key === selectedKey) ?? null;
    const reviewCount = sources.filter(isPoor).length;

    return (
        <div style={PAGE}>
            <PageHeader
                title="Rating & Feedback"
                subtitle={`Penilaian user atas jawaban EVA. Dinyatakan membantu apabila memperoleh ${helpfulThreshold} bintang atau lebih.`}
            />

            <StatRow columns={3}>
                <StatTile label="TOTAL PENILAIAN" value={summary.total} hint="penilaian yang diterima" />
                <StatTile
                    label="RATA-RATA"
                    value={summary.total ? `${summary.avg} ★` : '—'}
                    tone={summary.avg && summary.avg < POOR_AVERAGE ? 'var(--red-600)' : 'var(--green-500)'}
                />
                <StatTile
                    label="PERLU DITINJAU"
                    value={reviewCount}
                    hint={`materi di bawah ${POOR_AVERAGE} ★`}
                    tone={reviewCount > 0 ? 'var(--red-600)' : 'var(--ink-900)'}
                />
            </StatRow>

            <StarDistribution distribution={distribution} total={summary.total} />

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,0.85fr) minmax(0,1.15fr)', gap: '16px', alignItems: 'stretch' }}>
                <SourceList
                    sources={sources}
                    visible={visible}
                    pageSize={PER_PAGE}
                    scope={scope}
                    onScope={setScope}
                    query={query}
                    onQuery={setQuery}
                    reviewCount={reviewCount}
                    selectedKey={selectedKey}
                    onSelect={setSelectedKey}
                />
                <DetailPanel
                    source={selected}
                    recentComments={comments}
                    onClear={() => setSelectedKey(null)}
                    helpfulThreshold={helpfulThreshold}
                />
            </div>
        </div>
    );
}

/** Sebaran bintang mendatar — lima kolom, bukan lima baris bertumpuk. */
function StarDistribution({ distribution, total }) {
    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{total} penilaian</span>}>
                Sebaran bintang
            </CardTitle>
            {total === 0 ? (
                <EmptyState>Belum ada penilaian masuk.</EmptyState>
            ) : (
                <div style={{ padding: '14px 18px', display: 'grid', gridTemplateColumns: 'repeat(5, minmax(0,1fr))', gap: '16px' }}>
                    {distribution.map((row) => (
                        <div key={row.stars}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '6px' }}>
                                <span style={{ fontSize: '12.5px', fontWeight: 700, color: 'var(--amber-500)' }}>{row.stars} ★</span>
                                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{row.count} · {row.percent}%</span>
                            </div>
                            <span style={{ display: 'block', height: '8px', borderRadius: '999px', background: 'var(--surface-tint)', overflow: 'hidden' }}>
                                <span style={{ display: 'block', width: `${row.percent}%`, height: '100%', background: starTone(row.stars) }} />
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

function SourceList({ sources, visible, pageSize, scope, onScope, query, onQuery, reviewCount, selectedKey, onSelect }) {
    const pager = usePagination(visible, pageSize, `${scope}|${query}`);

    return (
        <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{visible.length} dari {sources.length}</span>}>
                Materi yang dinilai
            </CardTitle>

            <div style={{ padding: '11px 14px', borderBottom: '1px solid var(--border-soft)', display: 'flex', flexDirection: 'column', gap: '9px' }}>
                <div style={{ display: 'flex', gap: '7px' }}>
                    <Chip active={scope === 'review'} onClick={() => onScope('review')}>
                        Perlu ditinjau {reviewCount > 0 && `(${reviewCount})`}
                    </Chip>
                    <Chip active={scope === 'all'} onClick={() => onScope('all')}>Semua</Chip>
                </div>
                <input
                    style={{ ...inputStyle, padding: '7px 10px', fontSize: '12.5px' }}
                    placeholder="Cari judul materi…"
                    value={query}
                    onChange={(event) => onQuery(event.target.value)}
                />
            </div>

            <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>
                {visible.length === 0 ? (
                    <EmptyState>{emptyReason({ sources, scope, query })}</EmptyState>
                ) : (
                    pager.slice.map((source) => (
                        <SourceRow
                            key={source.key}
                            source={source}
                            active={source.key === selectedKey}
                            onClick={() => onSelect(source.key)}
                        />
                    ))
                )}
            </div>

            <Pagination {...pager} onPage={pager.setPage} compact />
        </Card>
    );
}

/** Pesan kosong yang menyebut sebabnya — saringan sempit vs memang belum ada. */
function emptyReason({ sources, scope, query }) {
    if (sources.length === 0) {
        return 'Belum ada materi yang dinilai. Beri penilaian pada menu EVA Preview.';
    }

    if (query.trim() !== '') {
        return `Tidak ada judul yang memuat “${query.trim()}”.`;
    }

    return scope === 'review'
        ? `Tidak ada materi di bawah ${POOR_AVERAGE} ★. Pilih "Semua" untuk melihat seluruh materi.`
        : 'Belum ada materi yang dinilai.';
}

function SourceRow({ source, active, onClick }) {
    const poor = isPoor(source);
    const thin = source.rating_count < THIN_EVIDENCE;

    return (
        <button
            type="button"
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
                borderLeft: `3px solid ${poor ? 'var(--red-600)' : 'transparent'}`,
                background: active ? 'var(--blue-050)' : 'transparent',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '5px' }}>
                <Badge tone={source.type === 'Artikel' ? 'blue' : 'neutral'}>{source.type}</Badge>
                {source.comments.length > 0 && <Badge tone="amber">{source.comments.length} tanggapan</Badge>}
            </div>
            <div style={{ fontSize: '13px', fontWeight: 600, lineHeight: 1.45, color: 'var(--ink-900)', ...CLAMP_2 }}>
                {source.title}
            </div>
            <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '5px' }}>
                <strong style={{ color: poor ? 'var(--red-600)' : 'var(--ink-800)' }}>{source.avg} ★</strong>
                {' · '}{source.rating_count} penilai
                {' · '}{source.helpful_percent}% membantu
                {thin && <span style={{ fontStyle: 'italic' }}> · data tipis</span>}
            </div>
        </button>
    );
}

function DetailPanel({ source, recentComments, onClear, helpfulThreshold }) {
    if (!source) {
        return (
            <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
                <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>pilih materi di kiri</span>}>
                    Tanggapan tertulis terbaru
                </CardTitle>
                <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>
                    {recentComments.length === 0 ? (
                        <EmptyState>
                            Belum ada tanggapan tertulis dari user.
                        </EmptyState>
                    ) : (
                        recentComments.map((comment) => <CommentItem key={comment.id} comment={comment} showQuestion />)
                    )}
                </div>
            </Card>
        );
    }

    return (
        <Card style={{ display: 'flex', flexDirection: 'column', height: PANEL_HEIGHT, overflow: 'hidden' }}>
            <CardTitle
                right={
                    <div style={{ display: 'flex', gap: '7px' }}>
                        <a href={source.manage_url} style={linkButtonStyle}>
                            Buka di {source.type === 'Artikel' ? 'Article Library' : 'Manage FAQ'}
                        </a>
                        <button type="button" onClick={onClear} style={{ ...linkButtonStyle, cursor: 'pointer', font: 'inherit' }}>
                            Tutup
                        </button>
                    </div>
                }
            >
                <span style={{ ...CLAMP_2 }}>{source.title}</span>
            </CardTitle>

            <div style={{ padding: '13px 18px', borderBottom: '1px solid var(--border-soft)', display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0,1fr))', gap: '12px' }}>
                <Metric label="RATA-RATA" value={`${source.avg} ★`} tone={isPoor(source) ? 'var(--red-600)' : 'var(--ink-900)'} />
                <Metric label="PENILAI" value={source.rating_count} />
                <Metric label="MEMBANTU" value={`${source.helpful_percent}%`} hint={`${helpfulThreshold}★ atau lebih`} />
                <Metric label="DIKUTIP EVA" value={`${source.eva_uses}×`} />
            </div>

            <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>
                {source.comments.length === 0 ? (
                    <EmptyState>
                        Belum ada tanggapan tertulis untuk materi ini. Yang tersedia baru nilai bintangnya.
                    </EmptyState>
                ) : (
                    source.comments.map((comment) => <CommentItem key={comment.id} comment={comment} showQuestion />)
                )}
            </div>
        </Card>
    );
}

function Metric({ label, value, hint, tone }) {
    return (
        <div>
            <div style={{ fontSize: '10.5px', fontWeight: 600, letterSpacing: '.3px', color: 'var(--slate-500)' }}>{label}</div>
            <div style={{ fontSize: '17px', fontWeight: 700, marginTop: '2px', color: tone || 'var(--ink-900)' }}>{value}</div>
            {hint && <div style={{ fontSize: '10.5px', color: 'var(--slate-500)' }}>{hint}</div>}
        </div>
    );
}

function CommentItem({ comment, showQuestion }) {
    return (
        <div style={{ padding: '12px 18px', borderBottom: '1px solid var(--border-soft)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '9px', marginBottom: '5px', flexWrap: 'wrap' }}>
                <span style={{ color: 'var(--amber-500)', fontSize: '13px' }}>{'★'.repeat(comment.stars)}</span>
                <span style={{ fontSize: '12px', color: 'var(--slate-500)' }}>
                    {comment.rater_name} · {comment.created_at}
                </span>
                {comment.reason && <Badge>{comment.reason}</Badge>}
            </div>
            <div style={{ fontSize: '13px', lineHeight: 1.6 }}>{comment.comment}</div>
            {showQuestion && comment.question && (
                <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>
                    atas pertanyaan: “{comment.question}”
                </div>
            )}
        </div>
    );
}

function Chip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            style={{
                padding: '6px 12px',
                fontSize: '12px',
                fontWeight: 600,
                borderRadius: '999px',
                cursor: 'pointer',
                whiteSpace: 'nowrap',
                border: `1px solid ${active ? 'transparent' : 'var(--border)'}`,
                background: active ? 'var(--blue-500)' : 'var(--white)',
                color: active ? 'var(--on-accent)' : 'var(--ink-700)',
            }}
        >
            {children}
        </button>
    );
}

const linkButtonStyle = {
    padding: '6px 11px',
    fontSize: '12px',
    fontWeight: 600,
    borderRadius: 'var(--r-md)',
    border: '1px solid var(--border)',
    background: 'var(--white)',
    color: 'var(--ink-700)',
    textDecoration: 'none',
    whiteSpace: 'nowrap',
};

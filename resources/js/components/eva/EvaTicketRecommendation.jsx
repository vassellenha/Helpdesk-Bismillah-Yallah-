import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Pagination, usePagination, inputStyle, coverageTone,
} from './ui';

/**
 * Pertanyaan per halaman.
 *
 * Tiap baris di sini memuat daftar calon subject-nya sendiri, jadi satu baris
 * jauh lebih tinggi daripada baris tabel biasa — halamannya dibuat lebih pendek.
 */
const PER_PAGE = 8;

/*
 | Ticket Recommendation.
 |
 | Menjawab satu pertanyaan: kalau EVA menyerah, tiketnya akan diarahkan ke
 | mana? Sumbernya Pencarian B (SubjectMatcher) atas service_catalog_subjects —
 | BUKAN pencarian jawaban. Katalog tidak berisi solusi, hanya label masalah.
 |
 | Tidak ada tombol "kirim tiket" di sini. EVA berhenti di draf (aturan #4);
 | penomoran dan SLA milik sistem Helpdesk.
 */

export default function EvaTicketRecommendation({ rows, gaps, stats, thresholds, endpoints, links }) {
    return (
        <div style={PAGE}>
            <PageHeader
                title="Ticket Recommendation"
                subtitle="Saran subject katalog untuk pertanyaan yang belum dapat dijawab EVA."
            />

            <StatRow>
                <StatTile label="PERTANYAAN GAGAL" value={stats.questions} hint="diperiksa ulang sekarang" />
                <StatTile
                    label="SARAN KUAT"
                    value={stats.auto}
                    hint={`≥ ${thresholds.auto_fill}, cukup untuk isi otomatis`}
                    tone="var(--green-500)"
                />
                <StatTile label="SARAN LEMAH" value={stats.weak} hint="perlu dipilih manusia" />
                <StatTile
                    label="TANPA SARAN"
                    value={stats.none}
                    hint="tidak ada subject yang mendekati"
                    tone={stats.none ? 'var(--red-600)' : 'var(--green-500)'}
                />
            </StatRow>

            <TestBench endpoint={endpoints.test} />

            <MaterialGaps gaps={gaps} links={links} />

            <RoutingTable rows={rows} />
        </div>
    );
}

/** Bangku uji — mengetik pertanyaan apa pun dan melihat tujuannya seketika. */
function TestBench({ endpoint }) {
    const [question, setQuestion] = useState('');
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    async function run() {
        if (!question.trim()) return;

        setLoading(true);
        setError(null);
        try {
            setResult(await apiFetch(endpoint, {
                method: 'POST',
                body: JSON.stringify({ question: question.trim() }),
            }));
        } catch (e) {
            setError(`Gagal menguji pertanyaan: ${e.message}`);
        } finally {
            setLoading(false);
        }
    }

    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle>Uji pengarahan</CardTitle>

            <div style={{ padding: '14px 18px' }}>
                <ErrorBanner message={error} onDismiss={() => setError(null)} />

                <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                    <input
                        style={{ ...inputStyle, flex: '1 1 320px' }}
                        placeholder="Ketik pertanyaan karyawan, misalnya “mailbox saya penuh”…"
                        value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && run()}
                    />
                    <Button onClick={run} disabled={loading || !question.trim()}>
                        {loading ? 'Menguji…' : 'Uji'}
                    </Button>
                </div>

                {result && (
                    <div style={{ marginTop: '14px' }}>
                        {result.candidates.length === 0 ? (
                            <p style={{ fontSize: '12.5px', color: 'var(--slate-500)', margin: 0, lineHeight: 1.6 }}>
                                Tidak ada subject yang mendekati, sehingga karyawan harus memilih sendiri dari
                                katalog. Tambahkan sinonim kata kuncinya pada menu Search Settings.
                            </p>
                        ) : (
                            <CandidateList candidates={result.candidates} />
                        )}
                    </div>
                )}
            </div>
        </Card>
    );
}

/**
 * Subject tujuan yang belum punya materi.
 *
 * Ini bagian paling berguna di layar ini: bukan sekadar "EVA tidak tahu",
 * melainkan "EVA tidak tahu, dan inilah nama resmi masalahnya di katalog".
 * Daftar tugas menulis artikel yang paling terarah yang bisa dihasilkan.
 */
function MaterialGaps({ gaps, links }) {
    if (gaps.length === 0) return null;

    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle right={<a href={links.articles} style={{ fontSize: '11.5px', fontWeight: 600 }}>Tulis artikel →</a>}>
                Subject tujuan yang belum punya materi
            </CardTitle>

            <div style={{ padding: '14px 18px', display: 'flex', flexDirection: 'column', gap: '11px' }}>
                {gaps.map((gap) => (
                    <div
                        key={gap.subject_id}
                        style={{ border: '1px solid var(--border)', borderRadius: 'var(--r-md)', padding: '12px 14px' }}
                    >
                        <div style={{ display: 'flex', alignItems: 'center', gap: '9px', flexWrap: 'wrap' }}>
                            <span style={{ fontSize: '13px', fontWeight: 700 }}>{gap.subject}</span>
                            <Badge tone="red">{gap.total}× jadi tujuan</Badge>
                        </div>
                        <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>{gap.path}</div>
                        <ul style={{ margin: '8px 0 0', paddingLeft: '18px', fontSize: '12px', lineHeight: 1.6 }}>
                            {gap.questions.map((q, i) => <li key={i}>{q}</li>)}
                        </ul>
                    </div>
                ))}
            </div>

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '0 18px 16px', lineHeight: 1.6 }}>
                Subject di atas adalah nama resmi masalahnya pada Service Catalog. Artikel yang
                ditautkan ke subject ini sekaligus menutup celah pada Coverage Dashboard.
            </p>
        </Card>
    );
}

function RoutingTable({ rows }) {
    if (rows.length === 0) {
        return (
            <Card>
                <EmptyState>
                    Belum ada pertanyaan yang gagal dijawab. Tidak ada yang perlu diarahkan.
                </EmptyState>
            </Card>
        );
    }

    const pager = usePagination(rows, PER_PAGE);

    return (
        <Card>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{rows.length} pertanyaan</span>}>
                Pengarahan per pertanyaan
            </CardTitle>

            <div style={{ padding: '14px 18px', display: 'flex', flexDirection: 'column', gap: '13px' }}>
                {pager.slice.map((row, index) => (
                    <div
                        key={index}
                        style={{ borderBottom: index === pager.slice.length - 1 ? 'none' : '1px solid var(--border-soft)', paddingBottom: '13px' }}
                    >
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: '9px', flexWrap: 'wrap', marginBottom: '8px' }}>
                            <span style={{ fontSize: '12.5px', fontWeight: 600 }}>“{row.question}”</span>
                            {row.total > 1 && <Badge tone="neutral">{row.total}× ditanya</Badge>}
                            {row.last_asked_at && (
                                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{row.last_asked_at}</span>
                            )}
                        </div>

                        {row.candidates.length === 0 ? (
                            <div style={{ fontSize: '11.5px', color: 'var(--red-600)' }}>
                                Tidak ada subject yang mendekati. Kosakatanya belum dikenali.
                            </div>
                        ) : (
                            <CandidateList candidates={row.candidates} />
                        )}
                    </div>
                ))}
            </div>

            <Pagination {...pager} onPage={pager.setPage} unit="pertanyaan" />
        </Card>
    );
}

/**
 * Calon selalu ditampilkan sebagai DAFTAR, tidak pernah sebagai satu jawaban.
 *
 * Katalog ini penuh subject bernama mirip di layanan berbeda ("Reset Password"
 * ada di bawah SAP maupun SILO). Menampilkan satu saja menyembunyikan justru
 * bagian yang perlu diputuskan manusia.
 */
function CandidateList({ candidates }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '7px' }}>
            {candidates.map((candidate, index) => (
                <div
                    key={candidate.subject_id}
                    style={{
                        display: 'flex', alignItems: 'center', gap: '11px', flexWrap: 'wrap',
                        padding: '9px 11px', borderRadius: 'var(--r-md)',
                        background: index === 0 ? 'var(--surface-tint)' : 'transparent',
                        border: index === 0 ? '1px solid var(--border)' : '1px solid transparent',
                    }}
                >
                    <span
                        style={{
                            width: '38px', textAlign: 'right', fontWeight: 700, fontSize: '12.5px',
                            color: coverageTone(candidate.confidence), flex: 'none',
                        }}
                    >
                        {candidate.confidence}
                    </span>

                    <span style={{ flex: '1 1 200px', minWidth: 0 }}>
                        <span style={{ fontSize: '12.5px', fontWeight: 600 }}>{candidate.subject}</span>
                        <span style={{ display: 'block', fontSize: '11px', color: 'var(--slate-500)' }}>{candidate.path}</span>
                    </span>

                    {candidate.is_auto_fill
                        ? <Badge tone="green">terisi otomatis</Badge>
                        : <Badge tone="neutral">perlu dipilih manual</Badge>}

                    {!candidate.has_material && <Badge tone="red">belum ada materi</Badge>}
                    {candidate.requires_approval && <Badge tone="amber">perlu approval</Badge>}
                </div>
            ))}
        </div>
    );
}

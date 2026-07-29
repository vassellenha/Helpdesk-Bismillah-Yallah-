import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Modal, Pagination, usePagination,
    inputStyle, thStyle, tdStyle,
} from './ui';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Unanswered Questions — celah materi.
 |
 | Tidak ada tombol "tandai selesai", dan itu disengaja. Sebuah pertanyaan
 | dianggap masih jadi celah kalau ditanyakan ulang SEKARANG pun EVA tetap tidak
 | menemukan jawaban. Begitu admin menulis FAQ atau mengunggah dokumen yang
 | menutupnya, barisnya hilang sendiri.
 |
 | Daftar yang statusnya ditandai manual selalu berakhir bohong: orang menandai
 | selesai padahal materinya tidak pernah dibuat.
 */

export default function EvaUnansweredQuestions({
    gaps: initialGaps, closed, threshold, endpoints, links,
}) {
    const [query, setQuery] = useState('');
    const [gaps, setGaps] = useState(initialGaps);
    const [asking, setAsking] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return gaps;

        return gaps.filter((gap) => gap.question.toLowerCase().includes(needle));
    }, [gaps, query]);

    const pager = usePagination(visible, PER_PAGE, query);

    const totalAsks = gaps.reduce((sum, gap) => sum + gap.count, 0);

    /**
     * Barisnya langsung hilang dari layar dan tidak pindah ke daftar mana pun.
     *
     * Yang dihapus tetap HANYA barisnya dari daftar kerja, bukan datanya: log
     * jawaban tidak disentuh, jadi angka Analytics tidak berubah, dan kalau
     * pertanyaan ini ditanyakan lagi nanti barisnya muncul kembali sendiri.
     *
     * Konsekuensi yang disengaja: setelah dialog konfirmasi ini, tidak ada
     * lagi jalan membatalkan dari layar. Penarikan kembali hanya lewat
     * endpoint restore (lihat UnansweredController::restore).
     */
    async function dismiss() {
        const gap = asking;
        setBusy(true);
        setError(null);
        try {
            await apiFetch(endpoints.dismiss, {
                method: 'POST',
                body: JSON.stringify({ question: gap.question }),
            });
            setGaps((rows) => rows.filter((r) => r.question !== gap.question));
            closeDialog();
        } catch (e) {
            setError(`Gagal menghapus pertanyaan: ${e.message}`);
        } finally {
            setBusy(false);
        }
    }

    function closeDialog() {
        setAsking(null);
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Unanswered Questions"
                subtitle="Pertanyaan karyawan yang belum dapat dijawab EVA."
            />

            {error && <ErrorBanner message={error} onDismiss={() => setError(null)} />}

            <StatRow columns={3}>
                <StatTile
                    label="CELAH TERBUKA"
                    value={gaps.length}
                    hint="pertanyaan berbeda"
                    tone={gaps.length ? 'var(--red-600)' : 'var(--green-500)'}
                />
                <StatTile label="VOLUME PERTANYAAN" value={totalAsks} hint="total kali ditanyakan" />
                <StatTile
                    label="SUDAH TERTUTUP"
                    value={closed.length}
                    hint="dulu gagal, kini terjawab"
                    tone={closed.length ? 'var(--green-500)' : undefined}
                />
            </StatRow>

            <Card style={{ padding: '13px 16px', marginBottom: '14px' }}>
                <input
                    style={inputStyle}
                    placeholder="Cari pertanyaan…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </Card>

            <Card style={{ marginBottom: '16px' }}>
                <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>ambang keyakinan {threshold}</span>}>
                    Belum ada jawabannya
                </CardTitle>

                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thStyle}>PERTANYAAN</th>
                                <th style={thStyle}>DITANYAKAN</th>
                                <th style={thStyle}>TERAKHIR</th>
                                <th style={thStyle}>KANDIDAT TERDEKAT</th>
                                <th style={thStyle}>TUTUP CELAH</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pager.slice.map((gap) => (
                                <tr key={gap.question}>
                                    <td style={{ ...tdStyle, minWidth: '300px', fontWeight: 500 }}>{gap.question}</td>
                                    <td style={{ ...tdStyle, whiteSpace: 'nowrap', fontWeight: 700, color: 'var(--clay-600)' }}>
                                        {gap.count}×
                                    </td>
                                    <td style={{ ...tdStyle, whiteSpace: 'nowrap', color: 'var(--slate-500)', fontSize: '12px' }}>
                                        {gap.last_asked_at}
                                    </td>
                                    <td style={tdStyle}>
                                        {/*
                                            Menampilkan kandidat terdekat beserta skornya, bukan sekadar
                                            "tidak ditemukan". Skor 48 untuk artikel yang sebetulnya relevan
                                            berarti materinya ada tapi kata-katanya tidak cocok — itu
                                            pekerjaan yang sama sekali berbeda dari materi yang memang belum ada.
                                        */}
                                        {gap.best_match_title ? (
                                            <>
                                                <div style={{ fontSize: '12.5px' }}>{gap.best_match_title}</div>
                                                <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '2px' }}>
                                                    keyakinan {gap.best_match_confidence}, di bawah ambang
                                                </div>
                                            </>
                                        ) : (
                                            <Badge tone="red">tidak ada kandidat</Badge>
                                        )}
                                    </td>
                                    <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                        <a href={`${links.faq}?question=${encodeURIComponent(gap.question)}`}>
                                            <Button variant="ghost">Tulis FAQ</Button>
                                        </a>{' '}
                                        <a href={links.documents}><Button variant="ghost">Unggah dokumen</Button></a>{' '}
                                        <Button variant="dangerPrimary" onClick={() => setAsking(gap)}>
                                            Hapus
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {gaps.length === 0
                            ? 'Tidak ada celah materi. Seluruh pertanyaan yang pernah gagal kini sudah ada jawabannya.'
                            : 'Tidak ada pertanyaan yang cocok dengan pencarian ini.'}
                    </EmptyState>
                )}

                <Pagination {...pager} onPage={pager.setPage} unit="pertanyaan" />
            </Card>

            {asking && (
                <Modal title="Hapus pertanyaan ini dari daftar kerja?" onClose={closeDialog}>
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: 0, fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-muted, #f6f7f9)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            “{asking.question}”
                        </p>
                    </div>

                    <div style={{
                        display: 'flex', justifyContent: 'flex-end', gap: '8px',
                        padding: '14px 20px 16px', marginTop: '4px',
                    }}>
                        <Button variant="ghost" onClick={closeDialog} disabled={busy}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={dismiss} disabled={busy}>
                            {busy ? 'Menghapus…' : 'Hapus'}
                        </Button>
                    </div>
                </Modal>
            )}

            {closed.length > 0 && (
                <Card>
                    <CardTitle>Sudah tertutup</CardTitle>
                    <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0 6px' }}>
                        {closed.map((gap) => (
                            <li
                                key={gap.question}
                                style={{ display: 'flex', gap: '12px', alignItems: 'center', padding: '10px 18px', borderBottom: '1px solid var(--border-soft)' }}
                            >
                                <span style={{ flex: 1, fontSize: '12.5px', color: 'var(--ink-700)' }}>{gap.question}</span>
                                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{gap.best_match_title}</span>
                                <Badge tone="green">{gap.best_match_confidence}</Badge>
                            </li>
                        ))}
                    </ul>
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '12px 18px' }}>
                        Riwayatnya tetap tercatat di log jawaban. Yang berubah hanya kondisi saat ini.
                    </p>
                </Card>
            )}
        </div>
    );
}

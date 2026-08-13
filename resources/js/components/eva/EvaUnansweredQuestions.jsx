import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Modal, Pagination, usePagination, RetentionCountdown,
    inputStyle, thStyle, tdStyle,
} from './ui';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Urutan daftar kerja.
 |
 | "Paling sering" tetap jadi bawaan dan itu disengaja: daftar ini adalah
 | antrean menulis materi, dan pertanyaan yang diulang tiga puluh orang lebih
 | mendesak daripada yang baru ditanya sekali lima menit lalu. Dua urutan waktu
 | ada untuk pertanyaan yang berbeda — "apa yang baru muncul minggu ini" dan
 | "mana yang sudah lama menganggur dan hampir kedaluwarsa".
 */
const SORT_NEXT = { count: 'latest', latest: 'oldest', oldest: 'count' };

/** Tetap tertulis, karena panah saja tidak pernah memberi tahu urutan apa yang sedang aktif. */
const SORT_HINT = {
    count: 'Diurutkan: paling sering ditanya. Klik untuk yang terbaru dulu.',
    latest: 'Diurutkan: terbaru dulu. Klik untuk yang terlama dulu.',
    oldest: 'Diurutkan: terlama dulu. Klik untuk kembali ke paling sering.',
};

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
    gaps: initialGaps, closed, threshold, endpoints, links, retentionDays,
}) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState('count');
    const [gaps, setGaps] = useState(initialGaps);
    const [asking, setAsking] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    // Daftar "Telah terjawab" jadi state, bukan prop yang dibaca langsung:
    // barisnya harus bisa hilang tanpa memuat ulang halaman.
    const [closedRows, setClosedRows] = useState(closed);
    const [closedAsking, setClosedAsking] = useState(null);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const rows = needle
            ? gaps.filter((gap) => gap.question.toLowerCase().includes(needle))
            : gaps;

        if (sort === 'count') return rows;

        // Disalin dulu: sort() mengubah array aslinya di tempat, dan `gaps`
        // adalah state React — mengurutkannya langsung berarti mengubah state
        // tanpa setState, dan layar bisa tidak ikut menggambar ulang.
        return [...rows].sort((a, b) => {
            const selisih = new Date(b.last_asked_iso) - new Date(a.last_asked_iso);

            return sort === 'latest' ? selisih : -selisih;
        });
    }, [gaps, query, sort]);

    const pager = usePagination(visible, PER_PAGE, `${query}|${sort}`);

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

    /**
     * Hapus dari daftar "Telah terjawab" — satu baris atau seluruhnya.
     *
     * Memakai jalur yang SAMA dengan daftar kerja di atas (mencatat keputusan
     * menyingkirkan), karena daftar ini tidak punya status tersimpan: isinya
     * dihitung ulang tiap halaman dibuka. Tidak ada baris yang bisa "dihapus"
     * selain dengan menyingkirkan pertanyaannya.
     *
     * Seluruh baris dikirim dalam satu permintaan. Mengirim satu per satu
     * membuat kegagalan di tengah meninggalkan separuh terhapus, dan layar
     * tidak punya cara jujur melaporkan baris mana yang gagal.
     */
    async function dismissClosed() {
        const target = closedAsking.mode === 'all'
            ? closedRows.map((row) => row.question)
            : [closedAsking.gap.question];

        setBusy(true);
        setError(null);
        try {
            await apiFetch(endpoints.dismissMany, {
                method: 'POST',
                body: JSON.stringify({ questions: target }),
            });
            setClosedRows((rows) => rows.filter((row) => !target.includes(row.question)));
            setClosedAsking(null);
        } catch (e) {
            setError(`Gagal menghapus pertanyaan: ${e.message}`);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Unanswered Questions"
                subtitle={`Pertanyaan user yang belum dapat dijawab EVA. Tiap baris terhapus otomatis ${retentionDays} hari setelah terakhir ditanyakan.`}
            />

            {error && <ErrorBanner message={error} onDismiss={() => setError(null)} />}

            <StatRow columns={3}>
                <StatTile
                    label="BELUM TERJAWAB"
                    value={gaps.length}
                    hint="pertanyaan berbeda"
                    tone={gaps.length ? 'var(--red-600)' : 'var(--green-500)'}
                />
                <StatTile label="VOLUME PERTANYAAN" value={totalAsks} hint="total kali ditanyakan" />
                <StatTile
                    label="TELAH TERJAWAB"
                    value={closed.length}
                    hint="sebelumnya gagal, kini terjawab"
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
                    Belum ada jawaban
                </CardTitle>

                <div style={{ overflowX: 'auto' }}>
                    <div className="w-full overflow-x-auto"><table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thStyle}>PERTANYAAN</th>
                                <th style={thStyle}>JUMLAH DITANYA</th>
                                <th style={thStyle}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', whiteSpace: 'nowrap' }}>
                                        <span>TERAKHIR DITANYA</span>
                                        <SortArrows value={sort} onChange={setSort} />
                                    </div>
                                </th>
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
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: '5px' }}>
                                            <span>{gap.last_asked_at}</span>
                                            <RetentionCountdown days={gap.expires_in_days} />
                                        </div>
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
                                            <Badge tone="red">Tidak ada kandidat</Badge>
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
                    </table></div>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {gaps.length === 0
                            ? 'Tidak ada celah materi. Seluruh pertanyaan yang sebelumnya gagal kini telah memiliki jawaban.'
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
                            padding: '10px 12px', background: 'var(--surface-muted)',
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

            {closedRows.length > 0 && (
                <Card>
                    <CardTitle
                        right={
                            <button
                                type="button"
                                onClick={() => setClosedAsking({ mode: 'all' })}
                                style={{
                                    border: 'none', background: 'none', padding: 0, cursor: 'pointer',
                                    fontSize: '11.5px', fontWeight: 600, color: 'var(--red-600)',
                                }}
                            >
                                Hapus semua
                            </button>
                        }
                    >
                        Telah terjawab
                    </CardTitle>
                    <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0 6px' }}>
                        {closedRows.map((gap) => (
                            <li
                                key={gap.question}
                                style={{ display: 'flex', gap: '12px', alignItems: 'center', padding: '10px 18px', borderBottom: '1px solid var(--border-soft)' }}
                            >
                                <span style={{ flex: 1, fontSize: '12.5px', color: 'var(--ink-700)' }}>{gap.question}</span>
                                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{gap.best_match_title}</span>
                                <Badge tone="green">{gap.best_match_confidence}</Badge>
                                <button
                                    type="button"
                                    onClick={() => setClosedAsking({ mode: 'one', gap })}
                                    style={{
                                        border: 'none', background: 'none', padding: 0, cursor: 'pointer',
                                        fontSize: '11.5px', fontWeight: 600, color: 'var(--red-600)',
                                    }}
                                >
                                    Hapus
                                </button>
                            </li>
                        ))}
                    </ul>
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '12px 18px' }}>
                        Riwayatnya tetap tercatat pada log jawaban. Yang berubah hanya kondisi saat ini.
                    </p>
                </Card>
            )}

            {/*
                Dialog terpisah dari dialog daftar kerja di atas, bukan
                digabung: kalimatnya berbeda karena akibatnya berbeda. Yang di
                atas menyingkirkan satu pekerjaan, yang di sini bisa menghapus
                seluruh daftar sekaligus, dan jumlahnya wajib terbaca sebelum
                ditekan.

                Kalimat kedua menyebut kemunculan kembali karena itu memang
                yang terjadi: keputusan menyingkirkan kedaluwarsa begitu
                pertanyaannya ditanyakan lagi. Tanpa disebut di sini,
                kemunculannya nanti terbaca sebagai kerusakan.
            */}
            {closedAsking && (
                <Modal
                    title={closedAsking.mode === 'all'
                        ? 'Hapus seluruh pertanyaan yang telah terjawab?'
                        : 'Hapus pertanyaan ini dari daftar?'}
                    onClose={() => (busy ? null : setClosedAsking(null))}
                >
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: 0, fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-muted)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            {closedAsking.mode === 'all'
                                ? `${closedRows.length} pertanyaan akan dihapus dari daftar ini.`
                                : `“${closedAsking.gap.question}”`}
                        </p>
                        <p style={{ margin: '12px 0 0', fontSize: '12.5px', lineHeight: 1.6, color: 'var(--ink-700)' }}>
                            Log jawaban tidak ikut terhapus, sehingga angka pada Analytics tidak berubah.
                            Pertanyaan akan muncul kembali apabila user menanyakannya lagi.
                        </p>
                    </div>

                    <div style={{
                        display: 'flex', justifyContent: 'flex-end', gap: '8px',
                        padding: '14px 20px 16px', marginTop: '4px',
                    }}>
                        <Button variant="ghost" onClick={() => setClosedAsking(null)} disabled={busy}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={dismissClosed} disabled={busy}>
                            {busy
                                ? 'Menghapus…'
                                : closedAsking.mode === 'all' ? 'Hapus semua' : 'Hapus'}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
}

/**
 * Panah naik-turun di kepala kolom waktu.
 *
 * Tiga keadaan, bukan dua: kedua panah redup berarti daftar masih memakai
 * urutan bawaannya (paling sering ditanya), panah bawah menyala berarti
 * terbaru dulu, panah atas berarti terlama dulu. Keadaan ketiga itu yang
 * membuat urutan bawaan bisa dikembalikan — tanpa itu, sekali seseorang
 * menyentuh panahnya, antrean prioritas menulis materi hilang sampai halaman
 * dimuat ulang.
 *
 * Arahnya mengikuti kebiasaan tabel: panah atas = menaik (paling lama dulu),
 * panah bawah = menurun (paling baru dulu).
 */
function SortArrows({ value, onChange }) {
    const aktif = 'var(--clay-600)';
    const redup = 'var(--slate-400, #94a3b8)';

    return (
        <button
            type="button"
            onClick={() => onChange(SORT_NEXT[value])}
            title={SORT_HINT[value]}
            aria-label={SORT_HINT[value]}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                border: 'none',
                background: 'transparent',
                cursor: 'pointer',
                padding: '2px',
                lineHeight: 0,
            }}
        >
            <svg width="9" height="13" viewBox="0 0 10 14" aria-hidden="true" focusable="false">
                <path d="M5 0.5 L9.2 5.2 L0.8 5.2 Z" fill={value === 'oldest' ? aktif : redup} />
                <path d="M5 13.5 L9.2 8.8 L0.8 8.8 Z" fill={value === 'latest' ? aktif : redup} />
            </svg>
        </button>
    );
}

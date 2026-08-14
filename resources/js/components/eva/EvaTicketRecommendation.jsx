import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Pagination, usePagination, inputStyle,
} from './ui';

/*
 | Ticket Recommendation — daftar kerja menulis, disusun PER SUBJECT.
 |
 | Bentuk lamanya satu baris per pertanyaan. Pada 40 pertanyaan layar itu jadi
 | tumpukan yang tidak menghasilkan keputusan: admin harus menyimpulkan sendiri
 | bahwa tujuh pertanyaan berbeda sebetulnya menuju satu artikel yang sama, dan
 | kesimpulan itu tidak pernah benar-benar diambil.
 |
 | Sekarang SUBJECT yang jadi baris. Pertanyaannya tetap ada, tapi tertutup di
 | balik baris subjectnya dan hanya terbuka kalau diminta — itu satu-satunya
 | cara membuat 40 pertanyaan tetap terbaca dalam satu layar.
 |
 | Dua daftar dipisah tegas karena pekerjaannya berbeda:
 |   SUBJECT TUJUAN  → tulis artikel.
 |   TANPA SARAN     → kosakatanya belum dikenali, perbaiki di Search Settings.
 */

const PER_PAGE = 8;

export default function EvaTicketRecommendation({ targets, unrouted, stats, thresholds, endpoints, links }) {
    return (
        <div style={PAGE}>
            <PageHeader
                title="Ticket Recommendation"
                subtitle="Subject katalog yang akan menjadi tujuan tiket, diurutkan dari yang paling mendesak untuk ditulisi artikel."
            />

            <StatRow columns={4}>
                <StatTile label="SUBJECT TUJUAN" value={stats.targets} hint="menampung pertanyaan tak terjawab" />
                <StatTile
                    label="BELUM BERMATERI"
                    value={stats.without_material}
                    hint="perlu artikel baru"
                    tone={stats.without_material ? 'var(--red-600)' : undefined}
                />
                <StatTile label="PERTANYAAN" value={stats.questions} hint="masih tidak terjawab saat ini" />
                <StatTile label="TANPA SARAN" value={stats.unrouted} hint="tidak ada subject yang mendekati" />
            </StatRow>

            <SubjectTargets targets={targets} thresholds={thresholds} links={links} />

            <UnroutedQuestions rows={unrouted} links={links} />

            <Bench endpoint={endpoints.test} thresholds={thresholds} />

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '14px 2px 0', lineHeight: 1.6 }}>
                Data pada halaman ini bersumber dari <a href={links.unanswered}>Unanswered Questions</a>.
                Pertanyaan yang telah terjawab atau telah dihapus dari daftar kerja pada halaman tersebut
                tidak lagi ditampilkan di sini.
            </p>
        </div>
    );
}

/**
 * Daftar utama. Satu baris = satu subject, dan pertanyaannya tertutup.
 *
 * Saringan bekerja pada nama subject MAUPUN teks pertanyaan di dalamnya: admin
 * yang mengingat keluhan karyawan belum tentu tahu nama resmi subjectnya, dan
 * saringan yang hanya mencari nama subject akan menjawab "tidak ada" untuk
 * pertanyaan yang jelas-jelas ada di halaman itu.
 */
function SubjectTargets({ targets, thresholds, links }) {
    const [query, setQuery] = useState('');
    const [opened, setOpened] = useState(null);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return targets;

        return targets.filter((target) =>
            target.subject.toLowerCase().includes(needle)
            || target.path.toLowerCase().includes(needle)
            || target.questions.some((q) => q.question.toLowerCase().includes(needle)));
    }, [targets, query]);

    const pager = usePagination(visible, PER_PAGE, query);

    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>Belum bermateri lebih dulu</span>}>
                Subject tujuan
            </CardTitle>

            <div style={{ padding: '13px 18px 0' }}>
                <input
                    style={inputStyle}
                    placeholder="Cari subject atau pertanyaan…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </div>

            {visible.length === 0 ? (
                <EmptyState>
                    {targets.length === 0
                        ? 'Tidak ada pertanyaan tak terjawab yang mengarah ke subject katalog.'
                        : 'Tidak ada subject yang cocok dengan pencarian ini.'}
                </EmptyState>
            ) : (
                <div style={{ padding: '14px 18px 4px', display: 'flex', flexDirection: 'column', gap: '9px' }}>
                    {pager.slice.map((target) => (
                        <TargetRow
                            key={target.subject_id}
                            target={target}
                            thresholds={thresholds}
                            links={links}
                            isOpen={opened === target.subject_id}
                            onToggle={() => setOpened(opened === target.subject_id ? null : target.subject_id)}
                        />
                    ))}
                </div>
            )}

            <Pagination {...pager} onPage={pager.setPage} unit="subject" />
        </Card>
    );
}

function TargetRow({ target, thresholds, links, isOpen, onToggle }) {
    return (
        <div style={{ border: '1px solid var(--border)', borderRadius: 'var(--r-md)', overflow: 'hidden' }}>
            <div
                onClick={onToggle}
                style={{
                    display: 'flex', alignItems: 'center', gap: '11px', flexWrap: 'wrap',
                    padding: '12px 14px', cursor: 'pointer',
                    background: target.has_material ? 'var(--white)' : 'var(--surface-tint)',
                }}
            >
                <span style={{ fontSize: '10px', color: 'var(--slate-500)', width: '10px', flex: 'none' }}>
                    {isOpen ? '▼' : '▶'}
                </span>

                <span style={{ flex: '1 1 240px', minWidth: 0 }}>
                    <span style={{ display: 'block', fontSize: '13px', fontWeight: 700 }}>{target.subject}</span>
                    <span style={{ display: 'block', fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '2px' }}>
                        {target.path}
                    </span>
                </span>

                {/*
                    Dua angka yang berbeda dan keduanya perlu: VOLUME adalah
                    berapa kali user bertanya (seberapa mendesak), TOTAL
                    adalah berapa pertanyaan berbeda (seberapa lebar). Satu
                    artikel menutup keduanya sekaligus, dan tanpa dipisah "3×"
                    selalu terbaca sebagai yang satunya.
                */}
                <span style={{ fontSize: '11.5px', color: 'var(--slate-500)', whiteSpace: 'nowrap' }}>
                    {target.volume}× ditanyakan · {target.total} pertanyaan
                </span>

                <Badge tone={target.has_material ? 'neutral' : 'red'}>
                    {target.has_material ? 'Sudah ada materi' : 'Belum ada materi'}
                </Badge>

                {target.has_material && (
                    <a
                        href={links.faq}
                        onClick={(e) => e.stopPropagation()}
                        style={{ fontSize: '11.5px', fontWeight: 600, whiteSpace: 'nowrap' }}
                    >
                        Perbaiki materi →
                    </a>
                )}
            </div>

            {isOpen && (
                <div style={{ borderTop: '1px solid var(--border-soft)', padding: '4px 0' }}>
                    {target.questions.map((q) => (
                        <div
                            key={q.question}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '11px',
                                padding: '9px 14px 9px 35px', fontSize: '12.5px',
                            }}
                        >
                            <span style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--clay-600)', flex: 'none', width: '30px' }}>
                                {q.count}×
                            </span>
                            <span style={{ flex: 1, lineHeight: 1.5 }}>{q.question}</span>
                            <Badge tone={q.is_auto_fill ? 'green' : 'amber'}>{q.confidence}</Badge>
                        </div>
                    ))}

                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '6px 14px 10px 35px', lineHeight: 1.6 }}>
                        Angka di kanan adalah keyakinan pengarahan. Mulai {thresholds.auto_fill}, subject
                        terisi otomatis pada draf tiket; di bawahnya user masih diminta memastikan.
                    </p>
                </div>
            )}
        </div>
    );
}

/**
 * Pertanyaan yang tak punya calon subject sama sekali.
 *
 * Kartu sendiri, bukan baris "—" di daftar atas: pekerjaannya bukan menulis
 * artikel melainkan mengajari kosakatanya, dan mencampur dua jenis pekerjaan
 * dalam satu daftar membuat keduanya sama-sama tertunda.
 */
function UnroutedQuestions({ rows, links }) {
    const pager = usePagination(rows, PER_PAGE);

    if (rows.length === 0) return null;

    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle right={<a href={links.searchSettings} style={{ fontSize: '11.5px', fontWeight: 600 }}>Search Settings →</a>}>
                Pertanyaan tanpa saran
            </CardTitle>

            <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0' }}>
                {pager.slice.map((row) => (
                    <li
                        key={row.question}
                        style={{
                            display: 'flex', gap: '11px', alignItems: 'center',
                            padding: '10px 18px', borderBottom: '1px solid var(--border-soft)',
                        }}
                    >
                        <span style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--clay-600)', flex: 'none', width: '30px' }}>
                            {row.count}×
                        </span>
                        <span style={{ flex: 1, fontSize: '12.5px', lineHeight: 1.5 }}>{row.question}</span>
                    </li>
                ))}
            </ul>

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '12px 18px', lineHeight: 1.6 }}>
                Tidak ada subject katalog yang mendekati pertanyaan ini, sehingga user harus memilih
                sendiri dari katalog. Tambahkan sinonim kata kuncinya pada Search Settings.
            </p>

            <Pagination {...pager} onPage={pager.setPage} unit="pertanyaan" />
        </Card>
    );
}

/**
 * Bangku uji. Tetap ada karena inilah satu-satunya tempat calon ALTERNATIF
 * berguna: membandingkan calon kedua dan ketiga hanya bermakna untuk satu
 * pertanyaan, bukan untuk satu daftar.
 */
function Bench({ endpoint, thresholds }) {
    const [question, setQuestion] = useState('');
    const [result, setResult] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    async function run() {
        if (!question.trim()) return;

        setBusy(true);
        setError(null);
        try {
            setResult(await apiFetch(endpoint, {
                method: 'POST',
                body: JSON.stringify({ question }),
            }));
        } catch (e) {
            setError(`Pengujian gagal: ${e.message}`);
        } finally {
            setBusy(false);
        }
    }

    function reset() {
        setQuestion('');
        setResult(null);
        setError(null);
    }

    return (
        <Card>
            <CardTitle>Uji pengarahan</CardTitle>

            <div style={{ padding: '13px 18px 0' }}>
                <ErrorBanner message={error} onDismiss={() => setError(null)} />
            </div>

            <div style={{ padding: '0 18px 13px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                <input
                    style={{ ...inputStyle, flex: '1 1 280px' }}
                    placeholder="Ketik pertanyaan user, misalnya “mailbox saya penuh”…"
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && run()}
                />
                <Button onClick={run} disabled={busy || !question.trim()}>
                    {busy ? 'Memeriksa…' : 'Periksa'}
                </Button>
                <Button variant="ghost" onClick={reset} disabled={busy || (!question && !result)}>
                    Reset
                </Button>
            </div>

            {result && (
                result.candidates.length === 0 ? (
                    <EmptyState>Tidak ada subject yang mendekati. Kosakatanya belum dikenali.</EmptyState>
                ) : (
                    <div style={{ padding: '0 18px 16px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        {result.candidates.map((candidate, index) => (
                            <div
                                key={candidate.subject_id}
                                style={{
                                    display: 'flex', alignItems: 'center', gap: '11px', flexWrap: 'wrap',
                                    padding: '11px 13px', borderRadius: 'var(--r-md)',
                                    border: '1px solid var(--border)',
                                    background: index === 0 ? 'var(--surface-tint)' : 'var(--white)',
                                }}
                            >
                                <span style={{ flex: '1 1 220px', minWidth: 0 }}>
                                    <span style={{ display: 'block', fontSize: '12.5px', fontWeight: 700 }}>
                                        {candidate.subject}
                                    </span>
                                    <span style={{ display: 'block', fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '2px' }}>
                                        {candidate.path}
                                    </span>
                                </span>

                                <Badge tone={candidate.is_auto_fill ? 'green' : 'amber'}>{candidate.confidence}</Badge>

                                {candidate.is_tied && <Badge tone="amber">Seri — perlu dipilih</Badge>}

                                {!candidate.has_material && <Badge tone="red">Belum ada materi</Badge>}
                            </div>
                        ))}

                        {result.candidates.some((c) => c.is_tied) ? (
                            /*
                             | Penjelasan kenapa tidak ada yang terisi otomatis.
                             |
                             | Tanpa ini, admin melihat dua angka besar yang nyaris sama lalu
                             | mendapati kolom subject tetap kosong — dan menyimpulkan
                             | pencocokannya rusak, padahal ia justru menahan diri dengan
                             | benar. Menebak satu di antara dua yang seri berarti tiket
                             | mendarat di tim yang salah tanpa ada yang memeriksa.
                            */
                            <p style={{ fontSize: '11.5px', color: 'var(--amber-700, #b45309)', margin: '2px 0 0', lineHeight: 1.6 }}>
                                Dua calon teratas berselisih ≤{thresholds.tie_margin} poin, jadi <strong>tidak ada yang
                                terisi otomatis</strong> — pengguna memilih sendiri. Untuk membuat salah satunya
                                menang, bedakan namanya di Category &amp; Taxonomy, atau tambahkan sinonim
                                yang hanya dimiliki salah satu.
                            </p>
                        ) : (
                            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '2px 0 0', lineHeight: 1.6 }}>
                                Calon teratas yang mencapai {thresholds.auto_fill} akan terisi otomatis pada draf
                                tiket. Pengujian pada kartu ini tidak dicatat pada log jawaban.
                            </p>
                        )}
                    </div>
                )
            )}
        </Card>
    );
}

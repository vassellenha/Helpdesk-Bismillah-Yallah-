import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge,
    EmptyState, thStyle, tdStyle,
} from './ui';

/*
 | Analytics — laporan pemakaian EVA.
 |
 | Semua angka berasal dari kb_answer_logs: apa yang benar-benar ditanyakan
 | karyawan. Tidak ada satu pun daftar contoh di layar ini.
 */

const CHART_HEIGHT = 230;

/** Pertanyaan yang terjawab di bawah porsi ini dianggap rapuh, bukan aman. */
const FRAGILE_THRESHOLD = 70;

export default function EvaAnalytics({ summary, trend, topQuestions, topMaterials, links }) {
    return (
        <div style={PAGE}>
            <PageHeader
                title="Analytics"
                subtitle="Ringkasan seluruh pertanyaan yang diterima EVA."
            />

            <StatRow>
                <StatTile label="TOTAL PERTANYAAN" value={summary.total} />
                <StatTile
                    label="TERSELESAIKAN EVA"
                    value={`${summary.deflection_percent}%`}
                    hint={`${summary.answered} dari ${summary.total}`}
                    tone={summary.deflection_percent >= 50 ? 'var(--green-500)' : 'var(--amber-500)'}
                />
                <StatTile
                    label="TIDAK TERJAWAB"
                    value={summary.unanswered}
                    hint="berlanjut menjadi draf tiket"
                    tone={summary.unanswered ? 'var(--red-600)' : undefined}
                />
                <StatTile
                    label="RATA-RATA KEYAKINAN"
                    value={summary.avg_confidence || '—'}
                    hint="pada jawaban yang diberikan"
                />
            </StatRow>

            {/*
                Penyebut "selesai di EVA" adalah SELURUH pertanyaan, termasuk yang
                dijawab dengan bertanya balik lalu ditinggalkan. Angka deflection
                paling mudah dibuat bagus dengan diam-diam mempersempit penyebutnya.
            */}
            <Card style={{ marginBottom: '16px' }}>
                <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>EVA bertanya balik {summary.clarify} kali</span>}>
                    Pertanyaan masuk dan terjawab per minggu
                </CardTitle>
                <div style={{ padding: '14px 12px 8px', height: CHART_HEIGHT }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={trend} margin={{ top: 6, right: 12, left: -20, bottom: 0 }}>
                            <CartesianGrid stroke="var(--border-soft)" vertical={false} />
                            <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--slate-500)' }} axisLine={false} tickLine={false} />
                            <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: 'var(--slate-500)' }} axisLine={false} tickLine={false} />
                            <Tooltip />
                            <Legend wrapperStyle={{ fontSize: '12px' }} />
                            <Bar dataKey="total" name="Masuk" fill="var(--border)" radius={[4, 4, 0, 0]} isAnimationActive={false} />
                            <Bar dataKey="answered" name="Terjawab" fill="var(--blue-500)" radius={[4, 4, 0, 0]} isAnimationActive={false} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </Card>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)', gap: '16px', alignItems: 'start' }}>
                <Card>
                    <CardTitle right={<a href={links.unanswered} style={{ fontSize: '11.5px', fontWeight: 600 }}>Celah materi →</a>}>
                        Pertanyaan terbanyak
                    </CardTitle>
                    {topQuestions.length === 0 ? (
                        <EmptyState>Belum ada pertanyaan yang tercatat.</EmptyState>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                <thead>
                                    <tr>
                                        <th style={thStyle}>PERTANYAAN</th>
                                        <th style={thStyle}>MASUK</th>
                                        <th style={thStyle}>TERJAWAB</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {topQuestions.map((row) => (
                                        <tr key={row.question}>
                                            <td style={{ ...tdStyle, fontSize: '12.5px' }}>{row.question}</td>
                                            <td style={{ ...tdStyle, whiteSpace: 'nowrap', fontWeight: 600 }}>{row.count}×</td>
                                            <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                                {/*
                                                    Yang paling menarik bukan 0% atau 100%, melainkan
                                                    yang di antaranya: materinya ada tapi rapuh —
                                                    kadang ketemu, kadang tidak.
                                                */}
                                                {row.answered_percent === 0 ? (
                                                    <Badge tone="red">Tidak pernah</Badge>
                                                ) : row.answered_percent < FRAGILE_THRESHOLD ? (
                                                    <Badge tone="amber">{row.answered_percent}% terjawab</Badge>
                                                ) : (
                                                    <Badge tone="green">{row.answered_percent}%</Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardTitle right={<a href={links.ratings} style={{ fontSize: '11.5px', fontWeight: 600 }}>Penilaian →</a>}>
                        Materi paling sering dikutip
                    </CardTitle>
                    {topMaterials.length === 0 ? (
                        <EmptyState>Belum ada materi yang digunakan EVA.</EmptyState>
                    ) : (
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                <thead>
                                    <tr>
                                        <th style={thStyle}>MATERI</th>
                                        <th style={thStyle}>DIKUTIP</th>
                                        <th style={thStyle}>RATING</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {topMaterials.map((row) => (
                                        <tr key={`${row.type}-${row.id}`}>
                                            <td style={tdStyle}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                    <Badge tone={row.type === 'Artikel' ? 'blue' : 'neutral'}>{row.type}</Badge>
                                                    <span style={{ fontSize: '12.5px' }}>{row.title}</span>
                                                </div>
                                            </td>
                                            <td style={{ ...tdStyle, whiteSpace: 'nowrap', fontWeight: 600 }}>{row.eva_uses}×</td>
                                            <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                                {row.rating_count > 0
                                                    ? `${row.rating_avg} ★ (${row.rating_count})`
                                                    : <span style={{ color: 'var(--slate-500)' }}>Belum dinilai</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}

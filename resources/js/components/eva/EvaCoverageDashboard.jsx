import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, EmptyState,
    Pagination, usePagination, coverageTone,
} from './ui';

/*
 | Coverage Dashboard.
 |
 | Seluruh angka datang jadi dari server (CoverageCalculator) — komponen ini
 | tidak menghitung ulang apa pun. Menghitung coverage di dua tempat adalah
 | cara paling cepat membuat dua layar menampilkan angka berbeda.
 */

const CHART_HEIGHT = 190;

/**
 * Sub category per halaman.
 *
 * Server dulu memotong daftar ini di 10 teratas dan sisanya tidak terjangkau
 * dari mana pun. Karena urutannya menurun, yang lenyap justru sub category
 * dengan kesiapan TERBURUK — persis yang paling perlu dilihat.
 */
const SUBCATEGORY_PER_PAGE = 12;

export default function EvaCoverageDashboard({ summary, bySubcategory, trend, todo, todoVolume, blockers, links }) {
    // Riwayat tren hanya berisi titik yang pernah direkam eva:snapshot-coverage.
    // Selama belum ada satu pun, yang punya makna cuma angka hari ini — dan
    // satu titik bukan tren. Menggambarnya seolah-olah garis, lengkap dengan
    // "+0 poin", adalah cara termudah membuat orang menyimpulkan sesuatu dari
    // data yang belum ada.
    const hasHistory = trend.length > 1;
    const delta = hasHistory ? trend[trend.length - 1].percent - trend[0].percent : 0;

    const subcategoryPager = usePagination(bySubcategory.rows, SUBCATEGORY_PER_PAGE);

    return (
        <div style={PAGE}>
            <PageHeader
                title="Coverage Dashboard"
                subtitle={`Kesiapan EVA menjawab ${summary.total_subjects} subject di Service Catalog.`}
            />

            {/*
                Dua kartu, bukan empat.

                Rincian "ditutup artikel" dan "hanya dari FAQ" pernah ada di sini
                lalu dibuang: keduanya benar, tapi tidak mengubah keputusan siapa
                pun — admin tidak melakukan hal berbeda karena tahu sebuah subject
                ditutup FAQ dan bukan artikel. Pengganti berbasis tiket sempat
                dicoba dan ikut dibuang atas keputusan pemilik.

                Yang tersisa adalah dua angka yang benar-benar dibaca: seberapa
                siap EVA sekarang, dan berapa banyak yang belum tersentuh.
            */}
            <StatRow columns={2}>
                <StatTile
                    label="KESIAPAN EVA"
                    value={`${summary.percent}%`}
                    hint={`${summary.covered_subjects} dari ${summary.total_subjects} subject`}
                    tone={coverageTone(summary.percent)}
                />
                <StatTile label="BELUM TERTUTUP" value={summary.uncovered_subjects} hint="subject tanpa artikel & FAQ" />
            </StatRow>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.35fr) minmax(0,1fr)', gap: '16px', marginBottom: '16px' }}>
                <Card>
                    <CardTitle right={hasHistory
                        ? <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{delta >= 0 ? '+' : ''}{delta} poin sejak {trend[0]?.label}</span>
                        : null}
                    >
                        Tren kesiapan
                    </CardTitle>
                    {!hasHistory ? (
                        <div style={{ height: CHART_HEIGHT, display: 'flex', alignItems: 'center' }}>
                            <EmptyState>
                                Riwayat kesiapan belum tersedia. Kesiapan hari ini <strong>{summary.percent}%</strong>.
                                Grafik mulai terbentuk setelah data harian terkumpul.
                            </EmptyState>
                        </div>
                    ) : (
                    <div style={{ padding: '14px 12px 6px', height: CHART_HEIGHT }}>
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={trend} margin={{ top: 6, right: 12, left: -18, bottom: 0 }}>
                                <CartesianGrid stroke="var(--border-soft)" vertical={false} />
                                <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--slate-500)' }} axisLine={false} tickLine={false} />
                                <YAxis unit="%" tick={{ fontSize: 11, fill: 'var(--slate-500)' }} axisLine={false} tickLine={false} />
                                <Tooltip formatter={(value) => [`${value}%`, 'Kesiapan']} />
                                {/* Tanpa animasi: grafik yang menggambar dirinya tiap muat
                                    membuat garis terlihat putus selama sepersekian detik
                                    pertama — di dasbor itu terbaca seperti data yang bolong. */}
                                <Line
                                    type="monotone"
                                    dataKey="percent"
                                    stroke="var(--blue-500)"
                                    strokeWidth={2.4}
                                    dot={{ r: 3.5 }}
                                    activeDot={{ r: 5 }}
                                    isAnimationActive={false}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                    )}
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '0 18px 14px' }}>
                        Angka hari ini dihitung ulang setiap halaman dibuka.
                    </p>
                </Card>

                <Card>
                    <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{todoVolume}× ditanyakan</span>}>
                        Sering ditanya, belum terjawab
                    </CardTitle>
                    {todo.length === 0 ? (
                        <EmptyState>
                            Belum ada pertanyaan yang gagal dijawab.
                        </EmptyState>
                    ) : (
                        <ul style={{ listStyle: 'none', margin: 0, padding: '6px 0' }}>
                            {todo.map((item) => (
                                <li
                                    key={item.question}
                                    style={{ display: 'flex', gap: '11px', alignItems: 'flex-start', padding: '10px 18px', borderBottom: '1px solid var(--border-soft)' }}
                                >
                                    <span style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--clay-600)', flex: 'none', paddingTop: '1px' }}>
                                        {item.count}×
                                    </span>
                                    <span style={{ flex: 1, fontSize: '12.5px', lineHeight: 1.5 }}>{item.question}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div style={{ padding: '12px 18px', fontSize: '11.5px', color: 'var(--slate-500)' }}>
                        Tutup celah ini melalui <a href={links.documents}>Documents</a> atau{' '}
                        <a href={links.faq}>Manage FAQ</a>.
                    </div>
                </Card>
            </div>

            <Card style={{ marginBottom: '16px' }}>
                <CardTitle
                    right={
                        <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                            kesiapan tertinggi lebih dulu
                        </span>
                    }
                >
                    Kesiapan per sub category
                </CardTitle>
                {bySubcategory.rows.length === 0 ? (
                    <EmptyState>Service Catalog belum berisi data.</EmptyState>
                ) : (
                    <div style={{ padding: '14px 18px 18px', display: 'flex', flexDirection: 'column', gap: '13px' }}>
                        {subcategoryPager.slice.map((row) => (
                            <div key={row.label}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '5px' }}>
                                    <span style={{ flex: 1, minWidth: 0, fontSize: '12.5px', fontWeight: 500 }}>{row.label}</span>
                                    <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{row.covered} / {row.total}</span>
                                    <span style={{ fontSize: '12px', fontWeight: 700, color: coverageTone(row.percent), width: '38px', textAlign: 'right' }}>
                                        {row.percent}%
                                    </span>
                                </div>
                                {/* Bar bertumpuk: biru = ditutup artikel, hijau = tambahan dari FAQ saja. */}
                                <div style={{ display: 'flex', height: '8px', borderRadius: '999px', overflow: 'hidden', background: 'var(--surface-tint)' }}>
                                    <span style={{ width: `${row.article_percent}%`, background: 'var(--blue-500)' }} />
                                    <span style={{ width: `${row.faq_percent}%`, background: 'var(--green-solid)' }} />
                                </div>
                            </div>
                        ))}
                        <div style={{ display: 'flex', gap: '16px', fontSize: '11.5px', color: 'var(--slate-500)', paddingTop: '4px' }}>
                            <LegendDot color="var(--blue-500)" label="ditutup artikel" />
                            <LegendDot color="var(--green-500)" label="tambahan dari FAQ" />
                        </div>
                    </div>
                )}

                <Pagination {...subcategoryPager} onPage={subcategoryPager.setPage} unit="sub category" />
            </Card>

            <Card>
                <CardTitle>Yang menghambat</CardTitle>
                {blockers.length === 0 ? (
                    <EmptyState>Tidak ada hambatan. Seluruh dokumen sudah terindeks dan materi sudah tertaut ke subject.</EmptyState>
                ) : (
                    <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0 6px' }}>
                        {blockers.map((blocker) => (
                            <li
                                key={blocker.text}
                                style={{ display: 'flex', gap: '12px', alignItems: 'center', padding: '11px 18px', borderBottom: '1px solid var(--border-soft)' }}
                            >
                                <span style={{ flex: 1, fontSize: '12.5px' }}>{blocker.text}</span>
                                <a href={blocker.url} style={{ fontSize: '12px', fontWeight: 600, whiteSpace: 'nowrap' }}>
                                    {blocker.action} →
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}

function LegendDot({ color, label }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '9px', height: '9px', borderRadius: '999px', background: color }} />
            {label}
        </span>
    );
}

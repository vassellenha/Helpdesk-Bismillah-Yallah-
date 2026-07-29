import { useMemo, useState } from 'react';
import {
    PAGE, PageHeader, Card, StatTile, StatRow, Badge,
    EmptyState, Pagination, usePagination,
    inputStyle, thStyle, tdStyle, coverageTone,
} from './ui';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Apps & Systems — BACA SAJA.
 |
 | Layanan dan sub category adalah isi Service Catalog milik role Admin
 | (aturan #5). Mockup menyediakan form tambah/sunting layanan di layar ini;
 | itu sengaja tidak ditiru. Layanan yang bisa dibuat dari dua tempat adalah
 | persis pola "satu konsep, dua sumber data" yang membuat katalog dan
 | Knowledge Base perlahan berbeda isi tanpa ada yang menyadari.
 |
 | Yang layar ini tambahkan di atas katalog: seberapa siap EVA menjawab tiap
 | layanan. Itu memang milik EVA.
 */

export default function EvaAppsSystems({ services, stats, catalogUrl }) {
    const [query, setQuery] = useState('');

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return services;

        return services.filter((service) => service.service.toLowerCase().includes(needle));
    }, [services, query]);

    const pager = usePagination(visible, PER_PAGE, query);

    return (
        <div style={PAGE}>
            <PageHeader
                title="Apps & Systems"
                subtitle="Kesiapan EVA untuk setiap layanan di Service Catalog."
            />

            <StatRow>
                <StatTile label="LAYANAN" value={stats.services} hint="punya subject aktif" />
                <StatTile label="TOTAL SUBJECT" value={stats.subjects} />
                <StatTile label="SUBJECT TERTUTUP" value={stats.covered} hint="punya artikel atau FAQ" tone="var(--green-500)" />
                <StatTile
                    label="LAYANAN KOSONG"
                    value={stats.untouched}
                    hint="belum punya materi sama sekali"
                    tone={stats.untouched ? 'var(--red-600)' : 'var(--green-500)'}
                />
            </StatRow>

            <Card style={{ padding: '13px 16px', marginBottom: '14px' }}>
                <input
                    style={inputStyle}
                    placeholder="Cari layanan…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </Card>

            <Card>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thStyle}>LAYANAN</th>
                                <th style={thStyle}>SUB CATEGORY</th>
                                <th style={thStyle}>SUBJECT</th>
                                <th style={thStyle}>TERTUTUP</th>
                                <th style={thStyle}>KESIAPAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pager.slice.map((service) => (
                                <tr key={service.service}>
                                    <td style={{ ...tdStyle, fontWeight: 600, minWidth: '200px' }}>{service.service}</td>
                                    <td style={tdStyle}>{service.subcategories}</td>
                                    <td style={tdStyle}>{service.total}</td>
                                    <td style={tdStyle}>{service.covered}</td>
                                    <td style={{ ...tdStyle, minWidth: '190px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                            <span style={{ flex: 1, height: '8px', borderRadius: '999px', background: 'var(--surface-tint)', overflow: 'hidden' }}>
                                                <span style={{ display: 'block', width: `${service.percent}%`, height: '100%', background: coverageTone(service.percent) }} />
                                            </span>
                                            <span style={{ width: '38px', textAlign: 'right', fontWeight: 700, fontSize: '12px', color: coverageTone(service.percent) }}>
                                                {service.percent}%
                                            </span>
                                        </div>
                                        {service.covered === 0 && (
                                            <div style={{ marginTop: '5px' }}>
                                                <Badge tone="red">belum tersentuh</Badge>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {services.length === 0
                            ? 'Service Catalog belum berisi data.'
                            : 'Tidak ada layanan yang cocok dengan pencarian ini.'}
                    </EmptyState>
                )}

                <Pagination {...pager} onPage={pager.setPage} unit="layanan" />
            </Card>

            <p style={{ fontSize: '12px', color: 'var(--slate-500)', margin: '14px 2px 0', lineHeight: 1.6 }}>
                Penambahan dan perubahan layanan dilakukan pada{' '}
                <a href={catalogUrl}>Service Catalog</a> milik Admin. EVA hanya membacanya.
            </p>
        </div>
    );
}

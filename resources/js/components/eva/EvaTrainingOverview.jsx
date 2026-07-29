import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    ErrorBanner, coverageTone,
} from './ui';

/*
 | Training Overview — apa yang EVA pelajari, dan sumber mana yang ia pakai.
 |
 | Sakelar di sini NYATA. Mematikan sebuah sumber benar-benar mengeluarkannya
 | dari jawaban EVA (FulltextKnowledgeSearch membacanya tiap pencarian), bukan
 | sekadar mengubah tampilan. Karena itu ada peringatan tegas saat mematikan,
 | dan tautan ke EVA Preview supaya efeknya bisa langsung dibuktikan.
 */

const SOURCE_LABELS = {
    articles: { title: 'Artikel', desc: 'Panduan lengkap yang lahir dari dokumen.' },
    faqs: { title: 'FAQ', desc: 'Tanya-jawab singkat, langsung terbit tanpa review.' },
};

export default function EvaTrainingOverview({ sources: initialSources, readiness, endpoints, links }) {
    const [sources, setSources] = useState(initialSources);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(null);

    async function toggle(source, enabled) {
        setError(null);
        setBusy(source);
        try {
            const result = await apiFetch(endpoints.toggle, {
                method: 'POST',
                body: JSON.stringify({ source, enabled }),
            });

            // Server adalah sumber kebenaran: pakai peta yang dikembalikannya,
            // jangan menebak state baru di klien.
            setSources((current) => ({
                articles: { ...current.articles, enabled: result.sources.articles },
                faqs: { ...current.faqs, enabled: result.sources.faqs },
            }));
        } catch (e) {
            setError(`Gagal mengubah sumber "${source}": ${e.message}`);
        } finally {
            setBusy(null);
        }
    }

    const bothOff = !sources.articles.enabled && !sources.faqs.enabled;

    return (
        <div style={PAGE}>
            <PageHeader
                title="Training Overview"
                subtitle="Ringkasan kesiapan EVA dan pengaturan sumber jawabannya."
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <StatRow>
                <StatTile
                    label="CAKUPAN KATALOG"
                    value={`${readiness.coverage_percent}%`}
                    hint={`${readiness.covered_subjects}/${readiness.total_subjects} subject`}
                    tone={coverageTone(readiness.coverage_percent)}
                />
                <StatTile label="SUBJECT BELUM TERTUTUP" value={readiness.uncovered_subjects} hint="belum ada materi" />
                <StatTile label="DOKUMEN TERINDEKS" value={readiness.documents_indexed} hint="sudah jadi artikel" />
                <StatTile
                    label="PERTANYAAN GAGAL"
                    value={readiness.open_gaps}
                    hint="tercatat di log"
                    tone={readiness.open_gaps ? 'var(--amber-600)' : 'var(--green-500)'}
                />
            </StatRow>

            {bothOff && (
                <div style={{ marginBottom: '16px', padding: '12px 15px', borderRadius: 'var(--r-md)', background: 'var(--red-soft-weak)', border: '1px solid var(--red-border)', fontSize: '12.5px', color: 'var(--red-600)', lineHeight: 1.6 }}>
                    <strong>Kedua sumber mati.</strong> EVA tidak akan bisa menjawab pertanyaan apa pun —
                    setiap pertanyaan berakhir sebagai tawaran draf tiket. Nyalakan minimal satu sumber.
                </div>
            )}

            <Card style={{ marginBottom: '16px' }}>
                <CardTitle right={<a href={links.coverage} style={{ fontSize: '11.5px', fontWeight: 600 }}>Lihat Coverage →</a>}>
                    Sumber jawaban EVA
                </CardTitle>

                <div style={{ padding: '8px 18px 16px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    <SourceRow
                        source="articles"
                        state={sources.articles}
                        busy={busy === 'articles'}
                        onToggle={toggle}
                        manageHref={links.articles}
                    />
                    <SourceRow
                        source="faqs"
                        state={sources.faqs}
                        busy={busy === 'faqs'}
                        onToggle={toggle}
                        manageHref={links.faq}
                    />
                </div>

                <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '0 18px 16px', lineHeight: 1.6 }}>
                    EVA menjawab dari artikel dan FAQ saja. Dokumen tidak menjadi sumber langsung
                    karena isinya sudah menjadi artikel. Perubahan dapat diuji pada <a href="/eva/preview">EVA Preview</a>.
                </p>
            </Card>
        </div>
    );
}

/**
 * Satu baris sumber. Peringatan hanya muncul saat AKAN mematikan — konfirmasi
 * yang selalu tampil cepat diabaikan; yang muncul tepat di momen berisiko tidak.
 */
function SourceRow({ source, state, busy, onToggle, manageHref }) {
    const label = SOURCE_LABELS[source];
    const next = !state.enabled;

    return (
        <div
            style={{
                display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap',
                padding: '13px 15px', borderRadius: 'var(--r-md)',
                border: '1px solid var(--border)',
                background: state.enabled ? 'var(--white)' : 'var(--surface-tint)',
            }}
        >
            <div style={{ flex: '1 1 240px', minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '9px' }}>
                    <span style={{ fontSize: '13.5px', fontWeight: 700 }}>{label.title}</span>
                    <Badge tone={state.enabled ? 'green' : 'neutral'}>{state.enabled ? 'dipakai' : 'dimatikan'}</Badge>
                    <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{state.count} aktif</span>
                </div>
                <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '3px' }}>
                    {label.desc} · <a href={manageHref}>kelola →</a>
                </div>
            </div>

            <Button
                variant={state.enabled ? 'ghost' : 'primary'}
                disabled={busy}
                onClick={() => onToggle(source, next)}
            >
                {busy ? '…' : state.enabled ? 'Matikan sebagai sumber' : 'Nyalakan'}
            </Button>
        </div>
    );
}

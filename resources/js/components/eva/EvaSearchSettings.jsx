import { useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, Badge, Toggle, Button,
    EmptyState, ErrorBanner, inputStyle, labelStyle, thStyle, tdStyle,
} from './ui';

/*
 | Search Settings.
 |
 | Daftar sinonim di sini benar-benar dipakai pencarian EVA — saat menyaring
 | kandidat maupun saat memberi skor. Pengujian langsung disediakan di layar yang
 | sama supaya efeknya bisa DILIHAT detik itu juga, bukan dipercaya begitu saja.
 */

const emptyDraft = { terms: '', note: '', is_active: true };

export default function EvaSearchSettings({ synonyms: initial, threshold, endpoints }) {
    const [synonyms, setSynonyms] = useState(initial);
    const [draft, setDraft] = useState(null);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);

    async function save() {
        setError(null);
        setSaving(true);

        const payload = { terms: draft.terms, note: draft.note, is_active: draft.is_active };

        try {
            if (draft.id) {
                const result = await apiFetch(`/eva/api/synonyms/${draft.id}`, { method: 'PUT', body: JSON.stringify(payload) });
                setSynonyms((current) => current.map((s) => (s.id === result.id ? result : s)));
            } else {
                const result = await apiFetch(endpoints.store, { method: 'POST', body: JSON.stringify(payload) });
                setSynonyms((current) => [result, ...current]);
            }
            setDraft(null);
        } catch (e) {
            setError(`Gagal menyimpan kelompok sinonim: ${e.message}`);
        } finally {
            setSaving(false);
        }
    }

    async function remove(synonym) {
        setError(null);
        try {
            await apiFetch(`/eva/api/synonyms/${synonym.id}`, { method: 'DELETE' });
            setSynonyms((current) => current.filter((s) => s.id !== synonym.id));
        } catch (e) {
            setError(`Gagal menghapus: ${e.message}`);
        }
    }

    async function toggle(synonym) {
        setError(null);
        try {
            const result = await apiFetch(`/eva/api/synonyms/${synonym.id}`, {
                method: 'PUT',
                body: JSON.stringify({ terms: synonym.terms, note: synonym.note, is_active: !synonym.is_active }),
            });
            setSynonyms((current) => current.map((s) => (s.id === result.id ? result : s)));
        } catch (e) {
            setError(`Gagal mengubah status: ${e.message}`);
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Search Settings"
                subtitle="Daftar kata yang dianggap sama saat EVA mencari jawaban."
                right={<Button onClick={() => setDraft({ ...emptyDraft })}>Kelompok Baru</Button>}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.3fr) minmax(0,1fr)', gap: '16px', alignItems: 'start' }}>
                <div>
                    {draft && (
                        <Card style={{ marginBottom: '14px' }}>
                            <CardTitle>{draft.id ? 'Edit kelompok' : 'Kelompok sinonim baru'}</CardTitle>
                            <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                <div>
                                    <label style={labelStyle}>Kata yang setara</label>
                                    <input
                                        style={inputStyle}
                                        value={draft.terms}
                                        onChange={(e) => setDraft({ ...draft, terms: e.target.value })}
                                        placeholder="password, sandi, kata sandi"
                                    />
                                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0', lineHeight: 1.55 }}>
                                        Pisahkan dengan koma, minimal dua kata. Seluruh kata dianggap setara.
                                        Imbuhan tidak perlu ditulis, misalnya “sandinya” telah tercakup
                                        oleh “sandi”.
                                    </p>
                                </div>
                                <div>
                                    <label style={labelStyle}>Catatan (opsional)</label>
                                    <input
                                        style={inputStyle}
                                        value={draft.note ?? ''}
                                        onChange={(e) => setDraft({ ...draft, note: e.target.value })}
                                        placeholder="Karyawan menulis 'sandi', dokumen menulis 'password'"
                                    />
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '11px' }}>
                                    <Toggle
                                        on={draft.is_active}
                                        onChange={() => setDraft({ ...draft, is_active: !draft.is_active })}
                                        label="Aktifkan kelompok"
                                    />
                                    <span style={{ fontSize: '12.5px' }}>Aktif</span>
                                </div>
                                <div style={{ display: 'flex', gap: '10px' }}>
                                    <Button onClick={save} disabled={saving || !draft.terms.trim()}>
                                        {saving ? 'Menyimpan…' : 'Simpan'}
                                    </Button>
                                    <Button variant="ghost" onClick={() => setDraft(null)}>Batal</Button>
                                </div>
                            </div>
                        </Card>
                    )}

                    <Card>
                        <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{synonyms.length} kelompok</span>}>
                            Kelompok sinonim
                        </CardTitle>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                <thead>
                                    <tr>
                                        <th style={thStyle}>KATA SETARA</th>
                                        <th style={thStyle}>AKTIF</th>
                                        <th style={thStyle} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {synonyms.map((synonym) => (
                                        <tr key={synonym.id}>
                                            <td style={{ ...tdStyle, minWidth: '240px' }}>
                                                <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                                                    {synonym.term_list.map((term) => (
                                                        <Badge key={term} tone="blue">{term}</Badge>
                                                    ))}
                                                </div>
                                                {synonym.note && (
                                                    <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '6px' }}>
                                                        {synonym.note}
                                                    </div>
                                                )}
                                            </td>
                                            <td style={tdStyle}>
                                                <Toggle on={synonym.is_active} onChange={() => toggle(synonym)} label="Aktifkan kelompok" />
                                            </td>
                                            <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                                <Button variant="ghost" onClick={() => setDraft({ ...synonym })}>Edit</Button>{' '}
                                                <Button variant="danger" onClick={() => remove(synonym)}>Hapus</Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {synonyms.length === 0 && (
                            <EmptyState>
                                Belum ada kelompok sinonim. Contoh penggunaan: “password, sandi”,
                                untuk kondisi karyawan menulis “sandi” sedangkan dokumen menulis “password”.
                            </EmptyState>
                        )}
                    </Card>
                </div>

                <LiveTest endpoint={endpoints.test} threshold={threshold} />
            </div>
        </div>
    );
}

/**
 * Uji langsung — memakai pencarian yang sama dengan EVA, tapi TIDAK dicatat ke
 * log jawaban. Percobaan admin tidak boleh mengotori daftar celah materi.
 */
function LiveTest({ endpoint, threshold }) {
    const [question, setQuestion] = useState('saya lupa sandi SAP bagaimana');
    const [result, setResult] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    async function run() {
        if (!question.trim()) return;

        setBusy(true);
        setError(null);
        try {
            setResult(await apiFetch(endpoint, { method: 'POST', body: JSON.stringify({ question }) }));
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>ambang {threshold}</span>}>
                Uji langsung
            </CardTitle>
            <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: '11px' }}>
                <input
                    style={inputStyle}
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') run(); }}
                    placeholder="Tulis pertanyaan sebagaimana karyawan menuliskannya…"
                />
                <Button onClick={run} disabled={busy || !question.trim()}>
                    {busy ? 'Mencari…' : 'Uji'}
                </Button>

                <ErrorBanner message={error} onDismiss={() => setError(null)} />

                {result && (
                    <>
                        <div
                            style={{
                                fontSize: '12.5px',
                                fontWeight: 600,
                                padding: '9px 12px',
                                borderRadius: 'var(--r-md)',
                                color: result.would_answer ? 'var(--green-500)' : 'var(--red-600)',
                                background: result.would_answer ? 'var(--green-soft)' : 'var(--red-soft-weak)',
                            }}
                        >
                            {result.would_answer ? 'EVA akan menjawab pertanyaan ini' : 'EVA belum akan menjawab. Seluruh kandidat berada di bawah ambang.'}
                        </div>

                        {result.hits.length === 0 ? (
                            <div style={{ fontSize: '12.5px', color: 'var(--slate-500)' }}>Tidak ada kandidat yang ditemukan.</div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                {result.hits.map((hit) => (
                                    <div
                                        key={`${hit.source_type}-${hit.source_id}`}
                                        style={{
                                            display: 'flex', alignItems: 'center', gap: '9px',
                                            padding: '9px 11px', borderRadius: 'var(--r-md)',
                                            border: '1px solid var(--border)',
                                        }}
                                    >
                                        <Badge tone={hit.type === 'Article' ? 'blue' : 'neutral'}>
                                            {hit.type === 'Article' ? 'Artikel' : 'FAQ'}
                                        </Badge>
                                        <span style={{ flex: 1, fontSize: '12.5px', minWidth: 0 }}>{hit.title}</span>
                                        <span style={{ fontSize: '12px', fontWeight: 700, color: hit.passes_threshold ? 'var(--green-500)' : 'var(--slate-500)' }}>
                                            {hit.confidence}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}

                <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', lineHeight: 1.6, margin: 0 }}>
                    Pengujian pada halaman ini tidak dicatat pada log jawaban.
                </p>
            </div>
        </Card>
    );
}

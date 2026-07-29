import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Toggle, Button,
    EmptyState, ErrorBanner, Pagination, usePagination,
    inputStyle, labelStyle, thStyle, tdStyle,
} from './ui';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Manage FAQ.
 |
 | FAQ ditulis admin dan LANGSUNG tayang — tidak ada status draf atau alur
 | review (aturan #2). Satu-satunya gerbang adalah "Show in EVA".
 */

const ALL = 'Semua';

/** Pemecah tag yang sama dengan TagRegistry::split() di sisi server. */
const splitTags = (tags) => (tags ?? '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);

const emptyDraft = { question: '', answer: '', catalog_subject_id: '', tags: '', is_eva_visible: true };

export default function EvaFaqManager({
    faqs: initial, subjects, services, stats, tags = [], activeTag = null, prefillQuestion = null,
}) {
    const [faqs, setFaqs] = useState(initial);
    const [query, setQuery] = useState('');
    const [service, setService] = useState(ALL);
    const [tag, setTag] = useState(activeTag ?? ALL);

    // Datang dari tombol "Tulis FAQ" di Unanswered Questions: form langsung
    // terbuka dengan pertanyaan karyawan sudah tersalin apa adanya. Mengetik
    // ulang kalimat yang sudah ada di layar sebelah adalah cara paling mudah
    // membuat FAQ meleset dari pertanyaan yang sebenarnya diajukan.
    const [draft, setDraft] = useState(
        prefillQuestion ? { ...emptyDraft, question: prefillQuestion } : null,
    );
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);

    const subjectService = useMemo(
        () => Object.fromEntries(subjects.map((s) => [s.id, s.service])),
        [subjects],
    );

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return faqs.filter((faq) => {
            if (needle && !`${faq.question} ${faq.answer} ${faq.tags ?? ''}`.toLowerCase().includes(needle)) return false;
            if (service !== ALL && subjectService[faq.catalog_subject_id] !== service) return false;
            if (tag !== ALL && !splitTags(faq.tags).includes(tag)) return false;

            return true;
        });
    }, [faqs, query, service, tag, subjectService]);

    const pager = usePagination(visible, PER_PAGE, `${query}|${service}|${tag}`);

    async function toggle(faq) {
        setError(null);
        try {
            const result = await apiFetch(`/eva/api/faqs/${faq.id}/toggle`, { method: 'POST' });
            setFaqs((current) => current.map((f) => (f.id === result.id ? { ...f, ...result } : f)));
        } catch (e) {
            setError(`Gagal mengubah status EVA: ${e.message}`);
        }
    }

    async function save() {
        setError(null);
        setSaving(true);

        const payload = {
            question: draft.question,
            answer: draft.answer,
            catalog_subject_id: draft.catalog_subject_id || null,
            is_eva_visible: draft.is_eva_visible,
            tags: draft.tags,
        };

        try {
            if (draft.id) {
                const result = await apiFetch(`/eva/api/faqs/${draft.id}`, { method: 'PUT', body: JSON.stringify(payload) });
                setFaqs((current) => current.map((f) => (f.id === result.id ? result : f)));
            } else {
                const result = await apiFetch('/eva/api/faqs', { method: 'POST', body: JSON.stringify(payload) });
                setFaqs((current) => [result, ...current]);
            }
            setDraft(null);
        } catch (e) {
            setError(`Gagal menyimpan FAQ: ${e.message}`);
        } finally {
            setSaving(false);
        }
    }

    async function remove(faq) {
        setError(null);
        try {
            await apiFetch(`/eva/api/faqs/${faq.id}`, { method: 'DELETE' });
            setFaqs((current) => current.filter((f) => f.id !== faq.id));
        } catch (e) {
            setError(`Gagal menghapus FAQ: ${e.message}`);
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Manage FAQ"
                subtitle="FAQ yang disimpan langsung dapat digunakan EVA untuk menjawab."
                right={<Button onClick={() => setDraft({ ...emptyDraft })}>FAQ Baru</Button>}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <ActiveTagChip tag={tag !== ALL ? tag : null} onClear={() => setTag(ALL)} shown={visible.length} total={faqs.length} />

            <StatRow columns={3}>
                <StatTile label="TOTAL FAQ" value={stats.total} />
                <StatTile label="AKTIF DI EVA" value={stats.eva_visible} hint="ikut dijadikan jawaban" />
                <StatTile label="BELUM TERTAUT" value={stats.unlinked} hint="tanpa subject katalog" tone={stats.unlinked ? 'var(--red-600)' : undefined} />
            </StatRow>

            {draft && (
                <Card style={{ marginBottom: '14px' }}>
                    <CardTitle>{draft.id ? 'Edit FAQ' : 'FAQ baru'}</CardTitle>
                    <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        <div>
                            <label style={labelStyle}>Pertanyaan</label>
                            <input
                                style={inputStyle}
                                value={draft.question}
                                onChange={(e) => setDraft({ ...draft, question: e.target.value })}
                                placeholder="Berapa lama proses unlock akun SAP?"
                            />
                        </div>
                        <div>
                            <label style={labelStyle}>Jawaban</label>
                            <textarea
                                style={{ ...inputStyle, minHeight: '110px', resize: 'vertical' }}
                                value={draft.answer}
                                onChange={(e) => setDraft({ ...draft, answer: e.target.value })}
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                            <div style={{ flex: '2 1 320px' }}>
                                <label style={labelStyle}>Subject katalog</label>
                                <select
                                    style={inputStyle}
                                    value={draft.catalog_subject_id ?? ''}
                                    onChange={(e) => setDraft({ ...draft, catalog_subject_id: e.target.value })}
                                >
                                    <option value="">— belum tertaut —</option>
                                    {subjects.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                                </select>
                            </div>
                            <div style={{ flex: '1 1 180px' }}>
                                <label style={labelStyle}>Tag</label>
                                <input style={inputStyle} value={draft.tags ?? ''} onChange={(e) => setDraft({ ...draft, tags: e.target.value })} />
                            </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '11px' }}>
                            <Toggle
                                on={draft.is_eva_visible}
                                onChange={() => setDraft({ ...draft, is_eva_visible: !draft.is_eva_visible })}
                                label="Tampilkan di EVA"
                            />
                            <span style={{ fontSize: '12.5px' }}>Show in EVA</span>
                        </div>
                        <div style={{ display: 'flex', gap: '10px' }}>
                            <Button onClick={save} disabled={saving || !draft.question.trim() || !draft.answer.trim()}>
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                            <Button variant="ghost" onClick={() => setDraft(null)}>Batal</Button>
                        </div>
                    </div>
                </Card>
            )}

            <Card style={{ padding: '13px 16px', marginBottom: '14px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                <input
                    style={{ ...inputStyle, flex: '1 1 260px' }}
                    placeholder="Cari pertanyaan, jawaban, atau tag…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                {/*
                    `value={ALL}` WAJIB ditulis — tanpa itu nilainya jatuh ke
                    teks "Semua layanan"/"Semua tag" dan saringan mencari nama
                    yang persis itu, sehingga daftar kosong justru saat saringan
                    dilepas. Lihat catatan panjang di EvaArticleLibrary.
                */}
                <select style={{ ...inputStyle, width: 'auto' }} value={service} onChange={(e) => setService(e.target.value)}>
                    <option value={ALL}>{ALL} layanan</option>
                    {services.map((name) => <option key={name} value={name}>{name}</option>)}
                </select>
                <select style={{ ...inputStyle, width: 'auto' }} value={tag} onChange={(e) => setTag(e.target.value)}>
                    <option value={ALL}>{ALL} tag</option>
                    {tags.map((name) => <option key={name} value={name}>{name}</option>)}
                </select>
            </Card>

            <Card>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thStyle}>PERTANYAAN & JAWABAN</th>
                                <th style={thStyle}>SUBJECT KATALOG</th>
                                <th style={thStyle}>DIPAKAI EVA</th>
                                <th style={thStyle}>RATING</th>
                                <th style={thStyle}>SHOW IN EVA</th>
                                <th style={thStyle} />
                            </tr>
                        </thead>
                        <tbody>
                            {pager.slice.map((faq) => (
                                <tr key={faq.id}>
                                    <td style={{ ...tdStyle, minWidth: '300px' }}>
                                        <div style={{ fontWeight: 600 }}>{faq.question}</div>
                                        <div style={{ fontSize: '12px', color: 'var(--slate-500)', marginTop: '4px', lineHeight: 1.55 }}>
                                            {faq.answer}
                                        </div>
                                        <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '5px' }}>
                                            {faq.author_name} · {faq.updated_at}
                                        </div>
                                    </td>
                                    <td style={tdStyle}>{faq.subject_name ?? <Badge tone="red">belum tertaut</Badge>}</td>
                                    <td style={tdStyle}>{faq.eva_uses}×</td>
                                    <td style={tdStyle}>
                                        {faq.rating_count > 0
                                            ? `${faq.rating_avg} ★ (${faq.rating_count})`
                                            : <span style={{ color: 'var(--slate-500)' }}>belum dinilai</span>}
                                    </td>
                                    <td style={tdStyle}>
                                        <Toggle on={faq.is_eva_visible} onChange={() => toggle(faq)} label={`Tampilkan FAQ di EVA`} />
                                    </td>
                                    <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                        <Button variant="ghost" onClick={() => setDraft({ ...faq })}>Edit</Button>{' '}
                                        <Button variant="danger" onClick={() => remove(faq)}>Hapus</Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {faqs.length === 0
                            ? 'Belum ada FAQ. Gunakan tombol FAQ Baru untuk menambahkannya.'
                            : 'Tidak ada FAQ yang cocok dengan filter ini.'}
                    </EmptyState>
                )}

                <Pagination {...pager} onPage={pager.setPage} unit="FAQ" />
            </Card>
        </div>
    );
}

/**
 * Chip tag aktif. Ditampilkan mencolok karena filter yang datang dari tautan
 * layar lain mudah tidak disadari — pengguna melihat daftar pendek dan mengira
 * datanya memang cuma segitu.
 */
function ActiveTagChip({ tag, onClear, shown, total }) {
    if (!tag) return null;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '9px', marginBottom: '14px', fontSize: '12.5px', flexWrap: 'wrap' }}>
            <span style={{ color: 'var(--slate-500)' }}>Disaring berdasarkan tag</span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '5px 12px', borderRadius: '999px', background: 'var(--blue-050)', color: 'var(--blue-ink)', fontWeight: 700 }}>
                {tag}
                <button type="button" onClick={onClear} style={{ border: 'none', background: 'none', cursor: 'pointer', color: 'inherit', fontWeight: 700, fontSize: '14px', lineHeight: 1 }}>×</button>
            </span>
            {/*
                Jumlah hasil ditulis di sini karena kartu statistik di bawahnya
                selalu merangkum SELURUH pustaka, bukan hasil yang tersaring.
                Tanpa angka ini, daftar yang tiba-tiba pendek terlihat seperti
                data yang hilang.
            */}
            <span style={{ color: 'var(--slate-500)' }}>
                menampilkan {shown} dari {total}
            </span>
        </div>
    );
}

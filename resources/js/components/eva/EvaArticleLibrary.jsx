import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, StatTile, StatRow, Badge, Toggle, Button,
    EmptyState, ErrorBanner, Modal, Pagination, usePagination,
    inputStyle, labelStyle, thStyle, tdStyle,
} from './ui';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Article Library.
 |
 | Tidak ada tombol "Artikel Baru" — artikel lahir dari dokumen (aturan #1).
 | Kalau butuh artikel baru, unggah dokumennya di layar Documents.
 */

const ALL = 'Semua';

/** Pemecah tag yang sama dengan TagRegistry::split() di sisi server. */
const splitTags = (tags) => (tags ?? '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);

/**
 * Cerminan Article::allSubjectIds() di sisi server: subject utama ∪ tautan
 * tambahan. Semua yang bertanya "artikel ini melayani apa saja" lewat sini.
 */
const allSubjectIds = (article) =>
    [article.catalog_subject_id, ...(article.subject_ids ?? [])].filter(Boolean);

export default function EvaArticleLibrary({ articles: initial, subjects, services, stats, tags = [], activeTag = null }) {
    const [articles, setArticles] = useState(initial);
    const [query, setQuery] = useState('');
    const [service, setService] = useState(ALL);
    const [visibility, setVisibility] = useState(ALL);
    const [tag, setTag] = useState(activeTag ?? ALL);
    const [editing, setEditing] = useState(null);
    const [error, setError] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);

    const subjectService = useMemo(
        () => Object.fromEntries(subjects.map((s) => [s.id, s.service])),
        [subjects],
    );

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return articles.filter((article) => {
            if (needle && !`${article.title} ${article.summary ?? ''} ${article.tags ?? ''}`.toLowerCase().includes(needle)) {
                return false;
            }
            // Satu artikel bisa melayani beberapa subject, jadi filter layanan
            // memeriksa SEMUA subject-nya. Kalau hanya subject utama yang
            // diperiksa, artikel akan hilang dari daftar layanan yang sebenarnya
            // ia layani — dan tidak ada petunjuk apa pun kenapa.
            if (service !== ALL && !allSubjectIds(article).some((id) => subjectService[id] === service)) return false;
            if (tag !== ALL && !splitTags(article.tags).includes(tag)) return false;
            if (visibility === 'Aktif di EVA' && !article.is_eva_visible) return false;
            if (visibility === 'Nonaktif' && article.is_eva_visible) return false;

            return true;
        });
    }, [articles, query, service, visibility, tag, subjectService]);

    const pager = usePagination(visible, PER_PAGE, `${query}|${service}|${visibility}|${tag}`);

    const replace = (updated) =>
        setArticles((current) => current.map((a) => (a.id === updated.id ? { ...a, ...updated } : a)));

    /**
     * Menghapus artikel — TIDAK menyentuh dokumen sumbernya, dan tidak menghapus
     * riwayat jawaban. Artikel yang lahir dari dokumen akan muncul lagi kalau
     * dokumennya diindeks ulang; itu disebut di dialognya supaya kemunculannya
     * kembali tidak terbaca sebagai bug.
     */
    async function remove() {
        setBusy(true);
        setError(null);
        try {
            await apiFetch(`/eva/api/articles/${deleting.id}`, { method: 'DELETE' });
            setArticles((rows) => rows.filter((a) => a.id !== deleting.id));
            setDeleting(null);
        } catch (e) {
            setError(`Gagal menghapus artikel: ${e.message}`);
        } finally {
            setBusy(false);
        }
    }

    async function toggle(article) {
        setError(null);
        try {
            const result = await apiFetch(`/eva/api/articles/${article.id}/toggle`, { method: 'POST' });
            replace(result);
        } catch (e) {
            setError(`Gagal mengubah status EVA untuk "${article.title}": ${e.message}`);
        }
    }

    async function save(draft) {
        setError(null);
        try {
            const result = await apiFetch(`/eva/api/articles/${draft.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    title: draft.title,
                    summary: draft.summary,
                    body: draft.body,
                    status: draft.status,
                    is_eva_visible: draft.is_eva_visible,
                    catalog_subject_id: draft.catalog_subject_id || null,
                    subject_ids: draft.subject_ids ?? [],
                    tags: draft.tags,
                }),
            });
            replace(result);
            setEditing(null);
        } catch (e) {
            setError(`Gagal menyimpan "${draft.title}": ${e.message}`);
        }
    }

    // Tabel ini punya delapan kolom; dengan padding bawaan, tombol paling kanan
    // baru terlihat setelah digeser. Yang dipangkas hanya jarak dan panjang
    // ringkasan — tidak ada kolom yang dihilangkan, karena semuanya dipakai
    // untuk memutuskan artikel mana yang perlu disentuh.
    const head = { ...thStyle, padding: '9px 10px' };
    const cell = { ...tdStyle, padding: '11px 10px' };

    return (
        <div style={PAGE}>
            <PageHeader
                title="Article Library"
                subtitle="Artikel dibuat dari dokumen yang diunggah pada menu Documents."
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <ActiveTagChip tag={tag !== ALL ? tag : null} onClear={() => setTag(ALL)} shown={visible.length} total={articles.length} />

            <StatRow>
                <StatTile label="TOTAL ARTIKEL" value={stats.total} />
                <StatTile label="TERBIT" value={stats.published} hint="berstatus published" />
                <StatTile label="AKTIF DI EVA" value={stats.eva_visible} hint="digunakan sebagai jawaban" />
                <StatTile label="BELUM TERTAUT" value={stats.unlinked} hint="tanpa subject katalog" tone={stats.unlinked ? 'var(--red-600)' : undefined} />
            </StatRow>

            <Card style={{ padding: '13px 16px', marginBottom: '14px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                <input
                    style={{ ...inputStyle, flex: '1 1 260px' }}
                    placeholder="Cari judul, ringkasan, atau tag…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                {/*
                    `value={ALL}` WAJIB ditulis. Tanpa atribut value, nilai sebuah
                    <option> jatuh ke teksnya — jadi "Semua layanan", bukan
                    "Semua". Saringan lalu mencari layanan yang benar-benar
                    bernama "Semua layanan", tidak menemukan apa pun, dan tabel
                    jadi kosong tepat saat admin mengira sedang menghapus
                    saringan. Bacaan awal halaman tetap benar karena state-nya
                    dimulai dari ALL, jadi cacatnya hanya muncul setelah admin
                    memilih satu nilai lalu kembali ke "Semua".
                */}
                <select style={{ ...inputStyle, width: 'auto' }} value={service} onChange={(e) => setService(e.target.value)}>
                    <option value={ALL}>{ALL} layanan</option>
                    {services.map((name) => <option key={name} value={name}>{name}</option>)}
                </select>
                <select style={{ ...inputStyle, width: 'auto' }} value={tag} onChange={(e) => setTag(e.target.value)}>
                    <option value={ALL}>{ALL} tag</option>
                    {tags.map((name) => <option key={name} value={name}>{name}</option>)}
                </select>
                <select style={{ ...inputStyle, width: 'auto' }} value={visibility} onChange={(e) => setVisibility(e.target.value)}>
                    <option value={ALL}>{ALL}</option>
                    <option value="Aktif di EVA">Aktif di EVA</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </Card>

            <Card>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={head}>ARTIKEL</th>
                                <th style={head}>SUBJECT KATALOG</th>
                                <th style={head}>SUMBER</th>
                                <th style={head}>DIPAKAI EVA</th>
                                <th style={head}>RATING</th>
                                <th style={head}>STATUS</th>
                                <th style={head}>SHOW IN EVA</th>
                                <th style={head} />
                            </tr>
                        </thead>
                        <tbody>
                            {pager.slice.map((article) => (
                                <tr key={article.id}>
                                    <td style={{ ...cell, minWidth: '230px', maxWidth: '340px' }}>
                                        <div style={{ fontWeight: 600 }}>{article.title}</div>
                                        <div style={{
                                            fontSize: '12px', color: 'var(--slate-500)', marginTop: '3px',
                                            display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
                                            overflow: 'hidden',
                                        }}>
                                            {article.summary}
                                        </div>
                                        <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>
                                            {article.author_name} · {article.updated_at}
                                            {/* Muncul hanya setelah isinya pernah ditimpa tangan manusia —
                                                jejak yang membedakan "lahir dari dokumen" dari "sudah disunting". */}
                                            {article.updated_by_name && ` · disunting ${article.updated_by_name}`}
                                        </div>
                                    </td>
                                    <td style={{ ...cell, minWidth: '150px' }}>
                                        <SubjectCell article={article} /></td>
                                    <td style={{
                                        ...cell, fontSize: '12px', color: 'var(--slate-500)',
                                        maxWidth: '150px', overflow: 'hidden', textOverflow: 'ellipsis',
                                    }}>
                                        {article.source_document_name ?? '—'}
                                    </td>
                                    <td style={{ ...cell, whiteSpace: 'nowrap' }}>{article.eva_uses}×</td>
                                    <td style={{ ...cell, whiteSpace: 'nowrap' }}>
                                        {article.rating_count > 0
                                            ? `${article.rating_avg} ★ (${article.rating_count})`
                                            : <span style={{ color: 'var(--slate-500)' }}>belum dinilai</span>}
                                    </td>
                                    <td style={cell}>
                                        <Badge tone={article.status === 'published' ? 'green' : 'amber'}>
                                            {article.status === 'published' ? 'Published' : 'Draft'}
                                        </Badge>
                                    </td>
                                    <td style={cell}>
                                        <Toggle
                                            on={article.is_eva_visible}
                                            onChange={() => toggle(article)}
                                            label={`Tampilkan "${article.title}" di EVA`}
                                        />
                                    </td>
                                    <td style={{ ...cell, whiteSpace: 'nowrap' }}>
                                        {/*
                                            Terbitkan / jadikan draf TIDAK ada di sini: mengubah
                                            status adalah keputusan yang diambil setelah membaca
                                            isinya, dan isinya cuma terbaca utuh di drawer Edit.
                                            Lencana STATUS di kiri tetap memberi tahu keadaannya
                                            tanpa perlu tombol yang mengubahnya sambil lalu.
                                        */}
                                        <div style={{ display: 'flex', gap: '6px' }}>
                                            <Button variant="ghost" onClick={() => setEditing({ ...article })}>Edit</Button>
                                            <Button variant="dangerPrimary" onClick={() => setDeleting(article)}>Hapus</Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {articles.length === 0
                            ? 'Belum ada artikel. Unggah dokumen pada menu Documents untuk membuatnya.'
                            : 'Tidak ada artikel yang cocok dengan filter ini.'}
                    </EmptyState>
                )}

                <Pagination {...pager} onPage={pager.setPage} unit="artikel" />
            </Card>

            {deleting && (
                <Modal title="Hapus artikel ini?" onClose={() => setDeleting(null)}>
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: 0, fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-tint)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            “{deleting.title}”
                        </p>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', padding: '14px 20px 16px' }}>
                        <Button variant="ghost" onClick={() => setDeleting(null)} disabled={busy}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={remove} disabled={busy}>
                            {busy ? 'Menghapus…' : 'Hapus'}
                        </Button>
                    </div>
                </Modal>
            )}

            {editing && (
                <EditorDrawer
                    draft={editing}
                    subjects={subjects}
                    onChange={setEditing}
                    onClose={() => setEditing(null)}
                    onSave={() => save(editing)}
                />
            )}
        </div>
    );
}

/**
 * Subject yang dilayani sebuah artikel: yang utama tampil sebagai teks, tautan
 * tambahan sebagai daftar kecil di bawahnya.
 *
 * Tautan tambahan sengaja ditulis lengkap, bukan disingkat "+2 lagi". Kolom ini
 * satu-satunya tempat seseorang bisa tahu sebuah SOP sudah menutup subject lain
 * — kalau disembunyikan, artikel kedua akan ditulis untuk subject yang
 * sebenarnya sudah tertutup.
 */
function SubjectCell({ article }) {
    const extras = article.subject_names ?? [];

    if (!article.subject_name && extras.length === 0) {
        return <Badge tone="red">belum tertaut</Badge>;
    }

    return (
        <div>
            {article.subject_name
                ? <div>{article.subject_name}</div>
                : <div style={{ color: 'var(--slate-500)', fontStyle: 'italic' }}>tanpa subject utama</div>}
            {extras.length > 0 && (
                <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>
                    + {extras.join(' · ')}
                </div>
            )}
        </div>
    );
}

function EditorDrawer({ draft, subjects, onChange, onClose, onSave }) {
    const set = (field) => (e) => onChange({ ...draft, [field]: e.target.value });

    return (
        <div
            style={{
                position: 'fixed', inset: 0, background: 'var(--overlay)',
                display: 'flex', justifyContent: 'flex-end', zIndex: 50,
            }}
            onClick={onClose}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                style={{
                    width: 'min(560px, 100%)', background: 'var(--white)', height: '100%',
                    overflowY: 'auto', padding: '22px 24px', display: 'flex', flexDirection: 'column', gap: '14px',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <h2 style={{ flex: 1, fontSize: '16px', fontWeight: 700, margin: 0 }}>Edit artikel</h2>
                    <Button variant="ghost" onClick={onClose}>Tutup</Button>
                </div>

                <div>
                    <label style={labelStyle}>Judul</label>
                    <input style={inputStyle} value={draft.title} onChange={set('title')} />
                </div>

                <div>
                    <label style={labelStyle}>Ringkasan</label>
                    <textarea style={{ ...inputStyle, minHeight: '80px', resize: 'vertical' }} value={draft.summary ?? ''} onChange={set('summary')} />
                </div>

                <div>
                    <label style={labelStyle}>Isi</label>
                    <textarea style={{ ...inputStyle, minHeight: '220px', resize: 'vertical', fontFamily: 'ui-monospace, monospace', fontSize: '12px' }} value={draft.body ?? ''} onChange={set('body')} />
                </div>

                <div>
                    <label style={labelStyle}>Subject katalog</label>
                    <select style={inputStyle} value={draft.catalog_subject_id ?? ''} onChange={set('catalog_subject_id')}>
                        <option value="">— belum tertaut —</option>
                        {subjects.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                    </select>
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0' }}>
                        Subject utama yang dicatat ketika artikel ini menjawab pertanyaan. Artikel tanpa
                        subject tidak dihitung dalam angka kesiapan EVA.
                    </p>
                </div>

                <ExtraSubjectPicker
                    subjects={subjects}
                    primaryId={Number(draft.catalog_subject_id) || null}
                    selectedIds={draft.subject_ids ?? []}
                    onChange={(subject_ids) => onChange({ ...draft, subject_ids })}
                />

                <div style={{ display: 'flex', gap: '12px' }}>
                    <div style={{ flex: 1 }}>
                        <label style={labelStyle}>Status</label>
                        <select style={inputStyle} value={draft.status} onChange={set('status')}>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                    <div style={{ flex: 1 }}>
                        <label style={labelStyle}>Tag</label>
                        <input style={inputStyle} value={draft.tags ?? ''} onChange={set('tags')} />
                    </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '11px' }}>
                    <Toggle
                        on={draft.is_eva_visible}
                        onChange={() => onChange({ ...draft, is_eva_visible: !draft.is_eva_visible })}
                        label="Tampilkan di EVA"
                    />
                    <span style={{ fontSize: '12.5px' }}>Show in EVA</span>
                </div>

                <div style={{ display: 'flex', gap: '10px', marginTop: 'auto', paddingTop: '10px' }}>
                    <Button onClick={onSave}>Simpan</Button>
                    <Button variant="ghost" onClick={onClose}>Batal</Button>
                </div>
            </div>
        </div>
    );
}

/**
 * Pemilih subject TAMBAHAN.
 *
 * Satu SOP kerap menjawab beberapa subject sekaligus — "SOP Unlock Akun SAP"
 * melayani "Aktivasi/Unlock Akun" di Access Request maupun "User Locked" di
 * Incident. Sebelum ada pemilih ini, jalan satu-satunya adalah menulis artikel
 * kedua dengan isi yang sama; begitu SOP-nya berubah, satu salinan pasti
 * tertinggal.
 *
 * Subject utama sengaja dikeluarkan dari daftar pilihan: menautkannya dua kali
 * tidak menambah apa pun, dan server memang akan membuangnya.
 */
function ExtraSubjectPicker({ subjects, primaryId, selectedIds, onChange }) {
    const selected = selectedIds.filter((id) => id !== primaryId);
    const labelOf = (id) => subjects.find((s) => s.id === id)?.label ?? `Subject #${id}`;
    const available = subjects.filter((s) => s.id !== primaryId && !selected.includes(s.id));

    const add = (e) => {
        const id = Number(e.target.value);
        if (id) onChange([...selected, id]);
        e.target.value = '';
    };

    return (
        <div>
            <label style={labelStyle}>Subject tambahan</label>

            {selected.length > 0 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '8px' }}>
                    {selected.map((id) => (
                        <div
                            key={id}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12.5px',
                                background: 'var(--blue-050)', color: 'var(--blue-ink)',
                                borderRadius: '8px', padding: '7px 10px',
                            }}
                        >
                            <span style={{ flex: 1 }}>{labelOf(id)}</span>
                            <button
                                type="button"
                                aria-label={`Lepas tautan ${labelOf(id)}`}
                                onClick={() => onChange(selected.filter((x) => x !== id))}
                                style={{ border: 'none', background: 'none', cursor: 'pointer', color: 'inherit', fontWeight: 700, fontSize: '15px', lineHeight: 1 }}
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <select style={inputStyle} value="" onChange={add} disabled={available.length === 0}>
                <option value="">
                    {available.length === 0 ? '— semua subject sudah tertaut —' : '+ tautkan subject lain…'}
                </option>
                {available.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
            </select>

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0' }}>
                Subject lain yang juga dijawab artikel ini. Seluruhnya ikut dihitung sebagai
                subject yang telah tercakup.
            </p>
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

import { useMemo, useState } from 'react';
import { apiFetch } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Modal, inputStyle, coverageTone,
} from './ui';

/*
 | Category & Taxonomy.
 |
 | Dua bagian yang sengaja dipisahkan tegas:
 |
 |   STRUKTUR (Issue Category → Layanan → Sub Category) milik Service Catalog,
 |   di sini hanya dibaca. Tidak ada tombol tambah/ubah/hapus — menambah
 |   kategori dari dua tempat adalah cara tercepat membuat katalog dan
 |   Knowledge Base diam-diam berbeda isi.
 |
 |   TAG milik EVA, dan di sinilah pengelolaan sesungguhnya terjadi.
 */

export default function EvaTaxonomy({ tree, tags: initialTags, duplicates: initialDuplicates, stats, catalogUrl }) {
    const [tags, setTags] = useState(initialTags);
    const [duplicates, setDuplicates] = useState(initialDuplicates);
    const [error, setError] = useState(null);

    const applyResult = (result) => {
        setTags(result.tags);
        setDuplicates(result.duplicates);
    };

    async function rename(from, to) {
        setError(null);
        try {
            applyResult(await apiFetch('/eva/api/tags/rename', {
                method: 'POST',
                body: JSON.stringify({ from, to }),
            }));
        } catch (e) {
            setError(`Gagal mengganti nama tag "${from}": ${e.message}`);
        }
    }

    /**
     * Mengembalikan berhasil/tidak, bukan void: dialog konfirmasinya hanya
     * boleh tertutup kalau tagnya benar-benar terhapus. Tertutup saat gagal
     * membuat layar terlihat seperti sudah selesai padahal tag masih ada.
     */
    async function remove(tag) {
        setError(null);
        try {
            applyResult(await apiFetch('/eva/api/tags/delete', {
                method: 'POST',
                body: JSON.stringify({ tag }),
            }));

            return true;
        } catch (e) {
            setError(`Gagal menghapus tag "${tag}": ${e.message}`);

            return false;
        }
    }

    return (
        <div style={PAGE}>
            <PageHeader
                title="Category & Taxonomy"
                subtitle="Kategori mengikuti Service Catalog. Tag dikelola pada halaman ini."
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <StatRow columns={5}>
                <StatTile label="ISSUE CATEGORY" value={stats.issue_categories} />
                <StatTile label="LAYANAN" value={stats.services} />
                <StatTile label="SUB CATEGORY" value={stats.subcategories} />
                <StatTile label="SUBJECT" value={stats.subjects} />
                <StatTile label="TAG DIGUNAKAN" value={stats.tags} />
            </StatRow>

            <TaxonomyTree tree={tree} catalogUrl={catalogUrl} />

            <TagManager tags={tags} duplicates={duplicates} onRename={rename} onRemove={remove} />
        </div>
    );
}

function TaxonomyTree({ tree, catalogUrl }) {
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState({});

    const needle = query.trim().toLowerCase();

    // Saat mencari, semua simpul dibuka otomatis — pencarian yang menemukan
    // hasil lalu menyembunyikannya di balik simpul tertutup terasa rusak.
    const isOpen = (key) => (needle ? true : open[key] ?? false);
    const toggle = (key) => setOpen((current) => ({ ...current, [key]: ! (current[key] ?? false) }));

    // Pencarian dicek pada NAMA SUBJECT juga, bukan cuma tiga level di atasnya.
    // Sebuah sub category tetap dianggap cocok kalau salah satu subject di
    // dalamnya cocok — tanpa itu, mencari "reset password" tidak akan
    // menemukan apa pun kalau sub category-nya bernama "AKUN APLIKASI".
    const filtered = useMemo(() => {
        if (! needle) return tree;

        const matches = (name) => name.toLowerCase().includes(needle);

        return tree
            .map((issue) => ({
                ...issue,
                services: issue.services
                    .map((service) => ({
                        ...service,
                        subcategories: service.subcategories.filter(
                            (sub) =>
                                matches(sub.name) || matches(service.name) || matches(issue.name)
                                || sub.subjectList.some((subject) => matches(subject.name)),
                        ),
                    }))
                    .filter((service) => service.subcategories.length > 0),
            }))
            .filter((issue) => issue.services.length > 0);
    }, [tree, needle]);

    return (
        <Card style={{ marginBottom: '16px' }}>
            <CardTitle right={<a href={catalogUrl} style={{ fontSize: '11.5px', fontWeight: 600 }}>Kelola di Service Catalog →</a>}>
                Struktur kategori
            </CardTitle>

            <div style={{ padding: '13px 18px 0' }}>
                <input
                    style={inputStyle}
                    placeholder="Cari layanan atau sub category…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </div>

            {filtered.length === 0 ? (
                <EmptyState>Tidak ada kategori yang cocok dengan pencarian ini.</EmptyState>
            ) : (
                <div style={{ padding: '14px 18px 18px', display: 'flex', flexDirection: 'column', gap: '6px' }}>
                    {filtered.map((issue) => (
                        <div key={issue.name}>
                            <Node
                                level={0}
                                name={issue.name}
                                node={issue}
                                isOpen={isOpen(issue.name)}
                                onToggle={() => toggle(issue.name)}
                            />

                            {isOpen(issue.name) && issue.services.map((service) => {
                                const key = `${issue.name}/${service.name}`;

                                return (
                                    <div key={key}>
                                        <Node
                                            level={1}
                                            name={service.name}
                                            node={service}
                                            isOpen={isOpen(key)}
                                            onToggle={() => toggle(key)}
                                        />

                                        {isOpen(key) && service.subcategories.map((sub) => {
                                            const subKey = `${key}/${sub.name}`;

                                            return (
                                                <div key={subKey}>
                                                    <Node
                                                        level={2}
                                                        name={sub.name}
                                                        node={sub}
                                                        isOpen={isOpen(subKey)}
                                                        onToggle={() => toggle(subKey)}
                                                    />

                                                    {isOpen(subKey) && sub.subjectList.map((subject) => (
                                                        <Node
                                                            key={`${subKey}/${subject.name}`}
                                                            level={3}
                                                            name={subject.name}
                                                            node={subject}
                                                        />
                                                    ))}
                                                </div>
                                            );
                                        })}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>
            )}

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: 0, padding: '0 18px 16px', lineHeight: 1.6 }}>
                Struktur kategori mengikuti Service Catalog. Perubahannya dilakukan pada menu Admin.
            </p>
        </Card>
    );
}

function Node({ level, name, node, isOpen, onToggle }) {
    const clickable = Boolean(onToggle);
    // Subject adalah SATU baris, bukan kelompok — "1/1" dan bar 100%/0% cuma
    // menyatakan ulang satu bit informasi lewat tiga tampilan berbeda. Level 0-2
    // tetap memakai pecahan+bar+persen karena masing-masing menaungi beberapa
    // subject dan ketiganya menjawab pertanyaan berbeda (berapa banyak, seberapa
    // penuh, seberapa dekat ke 100%).
    const isLeafSubject = level === 3;

    return (
        <div
            onClick={onToggle}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '9px 11px',
                marginLeft: `${level * 22}px`,
                borderRadius: 'var(--r-md)',
                cursor: clickable ? 'pointer' : 'default',
                background: level === 0 ? 'var(--blue-050)' : level === 1 ? 'var(--surface-tint)' : 'transparent',
                border: level >= 2 ? '1px solid var(--border-soft)' : 'none',
            }}
        >
            {clickable ? (
                <span style={{ fontSize: '10px', color: 'var(--slate-500)', width: '10px' }}>{isOpen ? '▼' : '▶'}</span>
            ) : (
                <span style={{ width: '10px' }} />
            )}

            <span style={{ flex: 1, minWidth: 0, fontSize: level === 0 ? '13px' : '12.5px', fontWeight: level === 0 ? 700 : 500 }}>
                {name}
            </span>

            <span style={{ fontSize: '11.5px', color: 'var(--slate-500)', whiteSpace: 'nowrap' }}>
                {node.articles} artikel · {node.faqs} FAQ
            </span>

            {isLeafSubject ? (
                <Badge tone={node.covered ? 'green' : 'red'}>{node.covered ? 'Ada materi' : 'Belum ada materi'}</Badge>
            ) : (
                <>
                    <span style={{ fontSize: '11.5px', color: 'var(--slate-500)', whiteSpace: 'nowrap', width: '64px', textAlign: 'right' }}>
                        {node.covered}/{node.subjects}
                    </span>

                    <span style={{ width: '70px', height: '7px', borderRadius: '999px', background: 'var(--border)', overflow: 'hidden', flex: 'none' }}>
                        <span style={{ display: 'block', width: `${node.percent}%`, height: '100%', background: coverageTone(node.percent) }} />
                    </span>

                    <span style={{ width: '38px', textAlign: 'right', fontSize: '12px', fontWeight: 700, color: coverageTone(node.percent) }}>
                        {node.percent}%
                    </span>
                </>
            )}
        </div>
    );
}

function TagManager({ tags, duplicates, onRename, onRemove }) {
    const [editing, setEditing] = useState(null);
    const [opened, setOpened] = useState(null);
    const [loading, setLoading] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);

    async function confirmRemove() {
        setBusy(true);
        try {
            if (await onRemove(deleting.tag)) {
                setDeleting(null);
            }
        } finally {
            setBusy(false);
        }
    }

    async function openTag(tag) {
        if (opened?.tag === tag) {
            setOpened(null);

            return;
        }

        setLoading(true);
        try {
            setOpened(await apiFetch('/eva/api/tags/materials', {
                method: 'POST',
                body: JSON.stringify({ tag }),
            }));
        } finally {
            setLoading(false);
        }
    }

    return (
        <Card>
            <CardTitle right={<span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>{tags.length} tag digunakan</span>}>
                Tag
            </CardTitle>

            {duplicates.length > 0 && (
                <div style={{ padding: '13px 18px', borderBottom: '1px solid var(--border-soft)', background: 'var(--amber-soft-weak)' }}>
                    <div style={{ fontSize: '12.5px', fontWeight: 700, color: 'var(--amber-ink)', marginBottom: '7px' }}>
                        {duplicates.length} kelompok tag yang nyaris serupa
                    </div>
                    {/*
                        Deteksinya konservatif — hanya beda spasi, tanda hubung, atau
                        huruf besar. Menyarankan penggabungan yang salah lebih merugikan
                        daripada tidak menyarankan apa pun, karena admin cenderung
                        menuruti saran tanpa memeriksa.
                    */}
                    {duplicates.map((group) => (
                        <div key={group.key} style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap', marginTop: '6px' }}>
                            {group.tags.map((t) => (
                                <Badge key={t.tag} tone="amber">{t.tag} ({t.total})</Badge>
                            ))}
                            <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                                Gabungkan melalui “Ganti nama” ke salah satu bentuk.
                            </span>
                        </div>
                    ))}
                </div>
            )}

            {tags.length === 0 ? (
                <EmptyState>
                    Belum ada tag yang digunakan. Tag diisi saat menyunting artikel, FAQ, atau dokumen,
                    untuk hal yang tidak memiliki tempat pada katalog, misalnya “forticlient” atau “windows 11”.
                </EmptyState>
            ) : (
                <div style={{ padding: '15px 18px', display: 'flex', flexWrap: 'wrap', gap: '9px' }}>
                    {tags.map((tag) => (
                        <div
                            key={tag.tag}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px',
                                padding: '7px 11px', borderRadius: '999px',
                                border: '1px solid var(--border)', background: 'var(--white)',
                            }}
                        >
                            <button
                                type="button"
                                onClick={() => openTag(tag.tag)}
                                style={{
                                    border: 'none', background: 'none', padding: 0, cursor: 'pointer',
                                    fontSize: '12.5px', fontWeight: 700,
                                    color: opened?.tag === tag.tag ? 'var(--blue-ink)' : 'var(--ink-900)',
                                    textDecoration: 'underline', textUnderlineOffset: '3px',
                                }}
                            >
                                {tag.tag}
                            </button>
                            <span style={{ fontSize: '11px', color: 'var(--slate-500)' }}>
                                {tag.total}× · {Object.entries(tag.by_type).map(([k, v]) => `${v} ${k}`).join(', ')}
                            </span>
                            <button
                                type="button"
                                onClick={() => setEditing({ from: tag.tag, to: tag.tag })}
                                style={{ border: 'none', background: 'none', cursor: 'pointer', fontSize: '11.5px', color: 'var(--blue-500)', fontWeight: 600 }}
                            >
                                Ganti nama
                            </button>
                            <button
                                type="button"
                                onClick={() => setDeleting(tag)}
                                style={{ border: 'none', background: 'none', cursor: 'pointer', fontSize: '11.5px', color: 'var(--red-600)', fontWeight: 600 }}
                            >
                                hapus
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {loading && (
                <div style={{ padding: '0 18px 16px', fontSize: '12.5px', color: 'var(--slate-500)' }}>Memuat materi…</div>
            )}

            {opened && !loading && (
                <div style={{ padding: '0 18px 18px' }}>
                    <div style={{ border: '1px solid var(--border)', borderRadius: 'var(--r-md)', padding: '14px 16px' }}>
                        <div style={{ fontSize: '12.5px', fontWeight: 700, marginBottom: '10px' }}>
                            Materi ber-tag “{opened.tag}”
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0,1fr))', gap: '14px' }}>
                            <MaterialColumn label="Artikel" items={opened.materials.articles} href={opened.links.articles} />
                            <MaterialColumn label="FAQ" items={opened.materials.faqs} href={opened.links.faqs} />
                            <MaterialColumn label="Dokumen" items={opened.materials.documents} href={opened.links.documents} />
                        </div>
                    </div>
                </div>
            )}

            {editing && (
                <div style={{ padding: '0 18px 18px', display: 'flex', gap: '10px', alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <div style={{ flex: '1 1 240px' }}>
                        <label style={{ display: 'block', fontSize: '11.5px', fontWeight: 600, color: 'var(--ink-700)', marginBottom: '5px' }}>
                            Ganti “{editing.from}” menjadi
                        </label>
                        <input
                            style={inputStyle}
                            value={editing.to}
                            autoFocus
                            onChange={(e) => setEditing({ ...editing, to: e.target.value })}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    onRename(editing.from, editing.to);
                                    setEditing(null);
                                }
                            }}
                        />
                        <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0' }}>
                            Ketik nama tag yang telah ada untuk menggabungkan keduanya.
                        </p>
                    </div>
                    <Button
                        onClick={() => { onRename(editing.from, editing.to); setEditing(null); }}
                        disabled={! editing.to.trim() || editing.to.trim() === editing.from}
                    >
                        Simpan
                    </Button>
                    <Button variant="ghost" onClick={() => setEditing(null)}>Batal</Button>
                </div>
            )}

            {/*
                Menghapus tag adalah satu-satunya tindakan di layar ini yang
                tidak bisa dibatalkan: setelah tag dilepas dari 40 materi, tidak
                ada catatan materi mana saja yang dulu memakainya, jadi
                memasangnya kembali berarti menelusuri satu per satu.

                Karena itu dialognya menyebut angkanya, bukan cuma bertanya
                "yakin?" — jumlah materi yang terpengaruh adalah satu-satunya
                hal yang membedakan salah klik yang sepele dari yang mahal.
            */}
            {deleting && (
                <Modal title="Hapus tag ini?" onClose={() => (busy ? null : setDeleting(null))}>
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: 0, fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-tint)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            “{deleting.tag}”
                        </p>
                        <p style={{ margin: '12px 0 0', fontSize: '12.5px', lineHeight: 1.6, color: 'var(--ink-700)' }}>
                            Tag ini akan dilepas dari <strong>{deleting.total} materi</strong>
                            {Object.keys(deleting.by_type).length > 0 && (
                                <> ({Object.entries(deleting.by_type).map(([jenis, jumlah]) => `${jumlah} ${jenis}`).join(', ')})</>
                            )}
                            {' '}dan tidak bisa dikembalikan.
                        </p>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', padding: '14px 20px 16px' }}>
                        <Button variant="ghost" onClick={() => setDeleting(null)} disabled={busy}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={confirmRemove} disabled={busy}>
                            {busy ? 'Menghapus…' : 'Hapus tag'}
                        </Button>
                    </div>
                </Modal>
            )}
        </Card>
    );
}

/**
 * Satu kolom materi per jenis, dengan tautan ke layarnya yang sudah tersaring.
 *
 * Tautan itu yang menjawab pertanyaan "lalu apa" — melihat daftar nama tanpa
 * bisa membukanya sama tidak bergunanya dengan tidak melihat daftar sama sekali.
 */
function MaterialColumn({ label, items, href }) {
    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '7px' }}>
                <Badge tone={items.length ? 'blue' : 'neutral'}>{label} {items.length}</Badge>
                {items.length > 0 && (
                    <a href={href} style={{ fontSize: '11.5px', fontWeight: 600 }}>buka →</a>
                )}
            </div>

            {items.length === 0 ? (
                <div style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>—</div>
            ) : (
                <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    {items.map((item) => (
                        <li key={item.id} style={{ fontSize: '12px', lineHeight: 1.5 }}>{item.title}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

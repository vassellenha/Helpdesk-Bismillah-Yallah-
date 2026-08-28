import { useEffect, useMemo, useState } from 'react';
import { apiFetch, uploadFile } from '../../lib/api';
import {
    PAGE, PageHeader, Card, CardTitle, StatTile, StatRow, Badge, Button,
    EmptyState, ErrorBanner, Modal, Pagination, Toggle, usePagination,
    inputStyle, labelStyle, thStyle, tdStyle,
} from './ui';
import SearchableSelect from './SearchableSelect';
import { DocumentBody, describeDocument, fileMeta } from './documentView';

/** Baris per halaman. Cukup panjang untuk dipindai, cukup pendek untuk dimuat. */
const PER_PAGE = 15;

/*
 | Documents — hulu Knowledge Base.
 |
 | Mengunggah dokumen di sini melahirkan SATU artikel draf. Indeks ulang
 | memperbarui artikel yang sama, tidak menggandakannya.
 */

const ALL = 'Semua';

/** Pemecah tag yang sama dengan TagRegistry::split() di sisi server. */
const splitTags = (tags) => (tags ?? '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);

const STATUS_TONE = { indexed: 'green', processing: 'amber', failed: 'red' };

const STATUS_PROCESSING = 'processing';

/**
 * Jeda antar-tanya status. Indexing berjalan di antrean, jadi hasilnya tidak
 * ikut di balasan unggah — layar ini yang menanyakannya sampai selesai. Tiga
 * detik: cukup rapat supaya TXT/DOCX terasa seketika, cukup jarang supaya OCR
 * yang berjalan menit-menitan tidak menghujani server.
 */
const POLL_INTERVAL_MS = 3000;

const emptyDraft = { name: '', extension: 'PDF', extracted_text: '', catalog_subject_id: '', tags: '', file: null };

/** Nama dokumen diusulkan dari nama berkas, tanpa ekstensinya. */
const nameFromFile = (filename) => filename.replace(/\.[^.]+$/, '');

const extensionOf = (filename) => (filename.split('.').pop() ?? '').toUpperCase();

/**
 * Berkas sudah dipilih tapi formatnya belum bisa dibaca aplikasi (PDF/XLSX) —
 * teksnya wajib ditempel. Dipisah jadi fungsi karena dipakai di tiga tempat;
 * kalau syaratnya ditulis ulang, cepat atau lambat tombol Simpan dan pesan di
 * layar akan berbeda pendapat.
 */
const needsPastedText = (draft, readable) =>
    draft.file !== null && !readable.includes(draft.extension);

/**
 * Berkas yang dipilih berupa FOTO.
 *
 * Dipisah dari needsPastedText karena keadaannya berbeda: teksnya tidak wajib
 * diketik — Tesseract akan mencobanya — tapi hasilnya bergantung ketajaman
 * gambar, dan admin perlu tahu ia boleh mengetiknya sendiri sejak awal. Foto
 * kertas miring atau kurang cahaya menghasilkan artikel penuh salah ketik, dan
 * artikel itulah yang nanti dikutip EVA sebagai jawaban.
 */
const isImageDraft = (draft, images) =>
    draft.file !== null && images.includes(draft.extension);

/**
 * Kalimat di dalam kotak isi dokumen.
 *
 * Tiga keadaan, tiga kalimat — dan yang membedakannya bukan gaya bahasa
 * melainkan apa yang harus dikerjakan admin: WAJIB mengetik (format tak
 * terbaca), BOLEH mengetik (foto), atau tidak perlu menyentuhnya sama sekali.
 */
function placeholderFor(draft, readable, images) {
    if (needsPastedText(draft, readable)) {
        return `Isi berkas ${draft.extension} belum dapat dibaca otomatis. Silakan salin teksnya ke sini.`;
    }

    if (isImageDraft(draft, images)) {
        return 'Boleh dikosongkan — tulisan di gambar akan dibaca otomatis. Ketik sendiri isinya bila hasil bacaannya meleset.';
    }

    return 'Tempel isi dokumen di sini, atau biarkan kosong bila berkas di atas sudah terbaca sendiri.';
}

function hintFor(draft, readable, images) {
    if (needsPastedText(draft, readable)) {
        return `Berkas ${draft.extension} tetap tersimpan, jadi bisa dibaca ulang bila ekstraksi otomatisnya diaktifkan nanti.`;
    }

    if (isImageDraft(draft, images)) {
        // Kalimat terakhir bukan basa-basi: IndexDocument memang mendahulukan
        // teks yang diketik dan tidak menjalankan OCR sama sekali bila kolom
        // ini terisi. Admin berhak tahu bahwa ketikannya tidak akan ditimpa.
        return 'Ketajaman foto menentukan hasil bacaannya — kertas miring atau kurang cahaya menghasilkan '
            + 'artikel penuh salah ketik. Teks yang Anda ketik di sini dipakai apa adanya dan tidak ditimpa hasil OCR.';
    }

    return `Isi berkas ${readable.join('/')} dibaca otomatis. Pemotongan mengikuti batas paragraf, jadi pisahkan bagian dengan baris kosong.`;
}

const canSubmit = (draft, readable) => {
    if (draft.name.trim() === '') return false;
    if (draft.file === null) return draft.extracted_text.trim() !== '';

    return !needsPastedText(draft, readable) || draft.extracted_text.trim() !== '';
};

export default function EvaDocuments({ documents: initial, subjects, extensions, readableExtensions = [], imageExtensions = [], tags = [], activeTag = null }) {
    const [documents, setDocuments] = useState(initial);
    const [draft, setDraft] = useState(null);
    const [query, setQuery] = useState('');
    const [tag, setTag] = useState(activeTag ?? ALL);
    const [busyId, setBusyId] = useState(null);
    const [deleting, setDeleting] = useState(null);
    // { mode: 'preview' | 'edit', document, content } — `content` null selagi
    // isinya masih diambil, supaya modalnya bisa terbuka seketika.
    const [detail, setDetail] = useState(null);
    const [busyDelete, setBusyDelete] = useState(false);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);

    /*
     | Kunci ini, bukan `documents`, yang jadi dependensi polling di bawah.
     | Kalau seluruh daftar dipakai, setiap jawaban polling mengubah state,
     | mengubah dependensi, lalu MEMULAI ULANG penghitung waktunya — dan
     | dokumen yang statusnya tak kunjung berubah tak pernah ditanya lagi.
     */
    const pendingKey = documents.filter((d) => d.status === STATUS_PROCESSING).map((d) => d.id).join(',');

    useEffect(() => {
        if (pendingKey === '') return undefined;

        const ids = pendingKey.split(',');

        const timer = setInterval(async () => {
            // Kegagalan satu permintaan sengaja diabaikan diam-diam: ini
            // pemeriksaan latar, dan memunculkan spanduk merah setiap kali
            // jaringan tersendat akan menutupi kesalahan yang sungguhan.
            const results = await Promise.all(
                ids.map((id) => apiFetch(`/eva/api/documents/${id}`).catch(() => null)),
            );

            const fresh = results.filter(Boolean);
            if (fresh.length === 0) return;

            setDocuments((current) => current.map((d) => fresh.find((f) => f.id === d.id) ?? d));
        }, POLL_INTERVAL_MS);

        return () => clearInterval(timer);
    }, [pendingKey]);

    /*
     | Dihitung dari daftar yang sedang tampil, BUKAN dikirim server sekali di
     | awal. Status berubah sendiri selagi halaman terbuka; angka yang dibekukan
     | saat halaman dimuat akan berdebat dengan tabel di bawahnya.
     */
    const stats = useMemo(() => ({
        total: documents.length,
        indexed: documents.filter((d) => d.status === 'indexed').length,
        failed: documents.filter((d) => d.status === 'failed').length,
        processing: documents.filter((d) => d.status === STATUS_PROCESSING).length,
        chunks: documents.reduce((sum, d) => sum + (d.chunk_count ?? 0), 0),
    }), [documents]);

    async function upload() {
        setError(null);
        setSaving(true);
        try {
            const fields = {
                name: draft.name,
                extension: draft.extension,
                extracted_text: draft.extracted_text,
                catalog_subject_id: draft.catalog_subject_id || null,
                tags: draft.tags,
            };

            const result = draft.file
                ? await uploadFile('/eva/api/documents', draft.file, fields)
                : await apiFetch('/eva/api/documents', {
                    method: 'POST',
                    body: JSON.stringify(fields),
                });
            setDocuments((current) => [result, ...current]);
            setDraft(null);
        } catch (e) {
            setError(`Gagal menambahkan dokumen: ${e.message}`);
        } finally {
            setSaving(false);
        }
    }

    /**
     * Menghapus dokumen berikut berkas aslinya. Artikel turunannya TIDAK ikut
     * terhapus — isinya sudah jadi milik admin begitu disunting. Itu disebut di
     * dialognya, karena kebalikannyalah yang biasanya diduga orang.
     */
    async function remove() {
        setBusyDelete(true);
        setError(null);
        try {
            await apiFetch(`/eva/api/documents/${deleting.id}`, { method: 'DELETE' });
            setDocuments((rows) => rows.filter((d) => d.id !== deleting.id));
            setDeleting(null);
        } catch (e) {
            setError(`Gagal menghapus dokumen: ${e.message}`);
        } finally {
            setBusyDelete(false);
        }
    }

    /**
     * Membuka Pratinjau atau Sunting.
     *
     * Isi dokumen SENGAJA diambil di sini, bukan dititipkan di daftar: satu
     * halaman memuat lima belas baris, dan menyeret seluruh teks SOP tiap baris
     * berarti membayar ongkos sesuatu yang dibuka satu per satu.
     */
    async function openDetail(document, mode) {
        setError(null);
        setDetail({ mode, document, content: null });
        try {
            const { document: content } = await apiFetch(`/eva/api/documents/${document.id}/content`);
            setDetail({ mode, document, content });
        } catch (e) {
            setDetail(null);
            setError(`Gagal membuka "${document.name}": ${e.message}`);
        }
    }

    /**
     * Menyimpan suntingan. Isi dokumen ikut terkirim, dan bila ia berubah server
     * mengindeks ulang — potongan pencarian dan artikel turunannya lahir dari
     * teks itu, jadi membiarkannya tertinggal berarti EVA menjawab memakai
     * kalimat yang sudah tidak ada di dokumennya.
     */
    async function saveEdit(fields) {
        setSaving(true);
        setError(null);
        try {
            const result = await apiFetch(`/eva/api/documents/${detail.document.id}`, {
                method: 'PUT',
                body: JSON.stringify(fields),
            });
            setDocuments((rows) => rows.map((d) => (d.id === result.id ? result : d)));
            setDetail(null);
        } catch (e) {
            setError(`Gagal menyimpan "${detail.document.name}": ${e.message}`);
        } finally {
            setSaving(false);
        }
    }

    async function reindex(document) {
        setError(null);
        setBusyId(document.id);
        try {
            const result = await apiFetch(`/eva/api/documents/${document.id}/reindex`, { method: 'POST' });
            setDocuments((current) => current.map((d) => (d.id === result.id ? result : d)));
        } catch (e) {
            setError(`Gagal mengindeks ulang "${document.name}": ${e.message}`);
        } finally {
            setBusyId(null);
        }
    }

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return documents.filter((document) => {
            if (needle && !`${document.name} ${document.tags ?? ''}`.toLowerCase().includes(needle)) return false;
            if (tag !== ALL && !splitTags(document.tags).includes(tag)) return false;

            return true;
        });
    }, [documents, query, tag]);

    const pager = usePagination(visible, PER_PAGE, `${query}|${tag}`);

    return (
        <div style={PAGE}>
            <PageHeader
                title="Documents"
                subtitle="Dokumen yang selesai diindeks akan menghasilkan satu artikel draf."
                right={<Button onClick={() => setDraft({ ...emptyDraft })}>Tambah Dokumen</Button>}
            />

            <ErrorBanner message={error} onDismiss={() => setError(null)} />

            <ActiveTagChip tag={tag !== ALL ? tag : null} onClear={() => setTag(ALL)} shown={visible.length} total={documents.length} />

            <StatRow>
                <StatTile label="TOTAL DOKUMEN" value={stats.total} />
                <StatTile label="TERINDEKS" value={stats.indexed} hint="isinya dapat digunakan EVA" />
                <StatTile label="DIPROSES" value={stats.processing} hint="sedang dibaca pada antrean" />
                <StatTile label="GAGAL" value={stats.failed} hint="perlu diindeks ulang" tone={stats.failed ? 'var(--red-600)' : undefined} />
                <StatTile label="POTONGAN TEKS" value={stats.chunks} hint="potongan siap dicari" />
            </StatRow>

            {draft && (
                <Card style={{ marginBottom: '14px' }}>
                    <CardTitle>Dokumen baru</CardTitle>
                    <div style={{ padding: '15px 18px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                            <div style={{ flex: '2 1 300px' }}>
                                <label style={labelStyle}>Nama dokumen</label>
                                <input
                                    style={inputStyle}
                                    value={draft.name}
                                    onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                                    placeholder="SOP Reset Password SAP"
                                />
                            </div>
                            <div style={{ flex: '0 1 130px' }}>
                                <label style={labelStyle}>Tipe</label>
                                <select style={inputStyle} value={draft.extension} onChange={(e) => setDraft({ ...draft, extension: e.target.value })}>
                                    {extensions.map((ext) => <option key={ext}>{ext}</option>)}
                                </select>
                            </div>
                        </div>

                        <FilePicker
                            file={draft.file}
                            extensions={extensions}
                            readableExtensions={readableExtensions}
                            onPick={(file) => setDraft({
                                ...draft,
                                file,
                                // Nama & tipe diisikan dari berkas, tapi tetap
                                // bisa disunting — nama berkas sering berupa
                                // "SOP-final-REV3(1).docx".
                                name: draft.name.trim() === '' ? nameFromFile(file.name) : draft.name,
                                extension: extensionOf(file.name),
                            })}
                            onClear={() => setDraft({ ...draft, file: null })}
                        />

                        <div>
                            <label style={labelStyle}>Isi dokumen</label>
                            <textarea
                                style={{ ...inputStyle, minHeight: '200px', resize: 'vertical' }}
                                value={draft.extracted_text}
                                onChange={(e) => setDraft({ ...draft, extracted_text: e.target.value })}
                                placeholder={placeholderFor(draft, readableExtensions, imageExtensions)}
                            />
                            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0' }}>
                                {hintFor(draft, readableExtensions, imageExtensions)}
                            </p>
                        </div>

                        <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                            <div style={{ flex: '2 1 320px' }}>
                                <label style={labelStyle}>Subject katalog</label>
                                <SearchableSelect
                                    value={draft.catalog_subject_id}
                                    onChange={(v) => setDraft({ ...draft, catalog_subject_id: v })}
                                    options={subjects.map((s) => ({ value: s.id, label: s.label }))}
                                    searchPlaceholder="Cari subject…"
                                />
                            </div>
                            <div style={{ flex: '1 1 180px' }}>
                                <label style={labelStyle}>Tag</label>
                                <input style={inputStyle} value={draft.tags} onChange={(e) => setDraft({ ...draft, tags: e.target.value })} />
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: '10px' }}>
                            <Button onClick={upload} disabled={saving || !canSubmit(draft, readableExtensions)}>
                                {saving ? 'Mengunggah…' : 'Simpan & indeks'}
                            </Button>
                            <Button variant="ghost" onClick={() => setDraft(null)}>Batal</Button>
                        </div>
                    </div>
                </Card>
            )}

            <Card style={{ padding: '13px 16px', marginBottom: '14px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                <input
                    style={{ ...inputStyle, flex: '1 1 260px' }}
                    placeholder="Cari nama dokumen atau tag…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                />
                {/*
                    `value={ALL}` WAJIB ditulis — tanpa itu nilainya jatuh ke
                    teks "Semua tag" dan saringan mencari tag yang bernama
                    persis itu, sehingga tabel kosong justru saat saringan
                    dilepas. Lihat catatan panjang di EvaArticleLibrary.
                */}
                <select style={{ ...inputStyle, width: 'auto' }} value={tag} onChange={(e) => setTag(e.target.value)}>
                    <option value={ALL}>{ALL} tag</option>
                    {tags.map((name) => <option key={name} value={name}>{name}</option>)}
                </select>
            </Card>

            <Card>
                <div style={{ overflowX: 'auto' }}>
                    <div className="w-full overflow-x-auto"><table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                <th style={thStyle}>DOKUMEN</th>
                                <th style={thStyle}>SUBJECT KATALOG</th>
                                <th style={thStyle}>STATUS</th>
                                <th style={thStyle}>POTONGAN</th>
                                <th style={thStyle}>ARTIKEL</th>
                                <th style={thStyle} />
                            </tr>
                        </thead>
                        <tbody>
                            {pager.slice.map((document) => (
                                <tr key={document.id}>
                                    <td style={{ ...tdStyle, minWidth: '260px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <Badge tone="blue">{document.extension}</Badge>
                                            <span style={{ fontWeight: 600 }}>{document.name}</span>
                                        </div>
                                        <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '4px' }}>
                                            {document.size_kb} KB · {document.page_count} hal · {document.uploaded_by_name} · {document.updated_at}
                                        </div>
                                    </td>
                                    <td style={tdStyle}>{document.subject_name ?? <Badge tone="red">Belum tertaut</Badge>}</td>
                                    <td style={{ ...tdStyle, maxWidth: '320px' }}>
                                        <Badge tone={STATUS_TONE[document.status] ?? 'neutral'}>{document.status}</Badge>
                                        {/*
                                            Alasan gagal ditulis di sebelah lencananya, bukan
                                            disembunyikan di balik klik. Selagi indexing masih
                                            sinkron, kegagalan baca dijawab langsung saat unggah
                                            berikut langkah berikutnya; setelah pindah ke antrean,
                                            kalimat itu tak punya tempat lain untuk muncul.
                                        */}
                                        {document.failure_reason && (
                                            <div style={{ fontSize: '11.5px', color: 'var(--red-600)', marginTop: '5px' }}>
                                                {document.failure_reason}
                                            </div>
                                        )}
                                    </td>
                                    <td style={tdStyle}>{document.chunk_count}</td>
                                    <td style={tdStyle}>
                                        {document.article_title
                                            ? <a href="/eva/articles">{document.article_title}</a>
                                            : <span style={{ color: 'var(--slate-500)' }}>belum ada</span>}
                                    </td>
                                    <td style={{ ...tdStyle, whiteSpace: 'nowrap' }}>
                                        <Button variant="ghost" onClick={() => openDetail(document, 'preview')}>
                                            Pratinjau
                                        </Button>{' '}
                                        <Button variant="ghost" onClick={() => openDetail(document, 'edit')}>
                                            Sunting
                                        </Button>{' '}
                                        <Button
                                            variant="ghost"
                                            onClick={() => reindex(document)}
                                            disabled={busyId === document.id || document.status === STATUS_PROCESSING}
                                        >
                                            {document.status === STATUS_PROCESSING ? 'Mengindeks…' : 'Indeks ulang'}
                                        </Button>{' '}
                                        <Button variant="dangerPrimary" onClick={() => setDeleting(document)}>Hapus</Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table></div>
                </div>

                {visible.length === 0 && (
                    <EmptyState>
                        {documents.length === 0
                            ? 'Belum ada dokumen. Unggah dokumen pertama untuk memulai.'
                            : 'Tidak ada dokumen yang cocok dengan filter ini.'}
                    </EmptyState>
                )}

                <Pagination {...pager} onPage={pager.setPage} unit="dokumen" />
            </Card>

            {detail?.mode === 'preview' && (
                <PreviewModal detail={detail} onClose={() => setDetail(null)} />
            )}

            {detail?.mode === 'edit' && (
                <EditModal
                    detail={detail}
                    subjects={subjects}
                    saving={saving}
                    onSave={saveEdit}
                    onClose={() => setDetail(null)}
                />
            )}

            {deleting && (
                <Modal title="Hapus dokumen ini?" onClose={() => setDeleting(null)}>
                    <div style={{ padding: '12px 20px 4px' }}>
                        <p style={{
                            margin: '0 0 12px', fontSize: '13px', lineHeight: 1.6, color: 'var(--ink-900)',
                            padding: '10px 12px', background: 'var(--surface-tint)',
                            borderRadius: '6px', borderLeft: '3px solid var(--border-soft)',
                        }}>
                            “{deleting.name}”
                        </p>
                        {deleting.article_title && (
                            <p style={{ margin: 0, fontSize: '11.5px', lineHeight: 1.6, color: 'var(--slate-500)' }}>
                                Artikel “{deleting.article_title}” <strong>tidak ikut terhapus</strong> dan tetap
                                digunakan EVA untuk menjawab.
                            </p>
                        )}
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', padding: '14px 20px 16px' }}>
                        <Button variant="ghost" onClick={() => setDeleting(null)} disabled={busyDelete}>Batal</Button>
                        <Button variant="dangerPrimary" onClick={remove} disabled={busyDelete}>
                            {busyDelete ? 'Menghapus…' : 'Hapus'}
                        </Button>
                    </div>
                </Modal>
            )}
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

/**
 * Pemilih berkas.
 *
 * Sebelum ini layar Documents hanya punya dropdown tipe — sebuah LABEL, bukan
 * berkas. Dokumen bertanda "PDF" tak pernah benar-benar berupa PDF, dan tidak
 * ada apa pun di layar yang mengungkap itu.
 */
/**
 * Pratinjau berkas asli di konsol.
 *
 * Memakai DocumentBody — penampil yang SAMA dengan popup rujukan yang dibuka
 * karyawan. Itu bukan sekadar hemat kode: admin membuka layar ini untuk
 * memastikan apa yang akan dilihat karyawan, dan pratinjau yang dirender dengan
 * aturannya sendiri menjawab pertanyaan yang berbeda dari yang ditanyakan.
 */
function PreviewModal({ detail, onClose }) {
    const { document, content } = detail;
    const source = content ? describeDocument(content) : null;
    const meta = content ? fileMeta(content, source.mode === 'preview') : null;

    return (
        <Modal
            title={document.name}
            onClose={onClose}
            width={source?.mode === 'preview' ? '900px' : '620px'}
        >
            <div className="eva-src-body" style={{ maxHeight: '72vh' }}>
                {!content && <div className="eva-src-muted">Memuat dokumen…</div>}
                {meta && <div className="eva-src-meta" style={{ marginBottom: '12px' }}>{meta}</div>}
                {source && <DocumentBody source={source} />}
            </div>
        </Modal>
    );
}

/**
 * Menyunting dokumen — keterangan DAN isinya.
 *
 * Kolom isi itulah yang selama ini hilang. Saat OCR gagal membaca sebuah
 * berkas, dokumennya ditandai `failed` dengan kalimat "salin teksnya ke kolom
 * isi, lalu indeks ulang" — kalimat yang menunjuk ke kolom yang tidak pernah
 * ada, sehingga satu-satunya jalan keluar adalah menghapus dokumennya dan
 * mengunggah ulang, membuang berkas aslinya sekalian.
 */
function EditModal({ detail, subjects, saving, onSave, onClose }) {
    const { document, content } = detail;
    const [form, setForm] = useState({
        name: document.name,
        catalog_subject_id: document.catalog_subject_id ?? '',
        tags: document.tags ?? '',
        is_eva_visible: document.is_eva_visible,
    });
    // Dipisah dari `form` karena nasibnya berbeda: hanya dikirim bila isinya
    // sudah termuat, supaya menekan Simpan sebelum itu tidak mengosongkan isi
    // dokumen — yang di server dibaca sebagai perintah membaca ulang berkasnya.
    const [text, setText] = useState(null);

    useEffect(() => {
        if (content && text === null) setText(content.text ?? '');
    }, [content, text]);

    const set = (patch) => setForm({ ...form, ...patch });

    return (
        <Modal title={`Sunting "${document.name}"`} onClose={onClose} width="680px">
            <div style={{ display: 'grid', gap: '14px', padding: '14px 20px 4px', maxHeight: '68vh', overflowY: 'auto' }}>
                <div>
                    <label style={labelStyle}>Nama dokumen</label>
                    <input style={inputStyle} value={form.name} onChange={(e) => set({ name: e.target.value })} />
                </div>

                <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <div style={{ flex: '2 1 300px' }}>
                        <label style={labelStyle}>Subject katalog</label>
                        <SearchableSelect
                            value={form.catalog_subject_id}
                            onChange={(v) => set({ catalog_subject_id: v })}
                            options={subjects.map((s) => ({ value: s.id, label: s.label }))}
                            searchPlaceholder="Cari subject…"
                        />
                    </div>
                    <div style={{ flex: '1 1 180px' }}>
                        <label style={labelStyle}>Tag</label>
                        <input style={inputStyle} value={form.tags} onChange={(e) => set({ tags: e.target.value })} />
                    </div>
                </div>

                {/*
                  | Toggle memanggil onChange TANPA argumen — nilainya dibalik di
                  | sini, sama seperti Manage FAQ. Menerima argumen di posisi itu
                  | berarti menyimpan objek event sebagai nilai boolean.
                  |
                  | Labelnya ditulis dua kali dengan sengaja: `label` hanya jadi
                  | aria-label untuk pembaca layar, jadi tanpa teks di sebelahnya
                  | saklar ini tak bertuliskan apa pun di layar.
                */}
                <div style={{ display: 'flex', alignItems: 'center', gap: '11px' }}>
                    <Toggle
                        on={form.is_eva_visible}
                        onChange={() => set({ is_eva_visible: !form.is_eva_visible })}
                        label="Boleh dipakai EVA"
                    />
                    <span style={{ fontSize: '12.5px' }}>Boleh dipakai EVA</span>
                </div>

                <div>
                    <label style={labelStyle}>Isi dokumen</label>
                    <textarea
                        style={{ ...inputStyle, minHeight: '220px', resize: 'vertical' }}
                        value={text ?? ''}
                        disabled={text === null}
                        onChange={(e) => setText(e.target.value)}
                        placeholder={text === null ? 'Memuat isi dokumen…' : 'Ketik isi dokumen di sini.'}
                    />
                    <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0', lineHeight: 1.6 }}>
                        Mengubah isi akan <strong>mengindeks ulang</strong> dokumen ini, dan potongan pencarian
                        ikut diperbarui. Artikel yang sudah ada <strong>tidak ditimpa</strong> — isinya milik Anda
                        sejak disunting di Article Library; dokumen yang belum punya artikel akan melahirkannya
                        sekarang.
                        {content?.has_file && ' Kosongkan kolom ini untuk membaca ulang berkas aslinya.'}
                    </p>
                </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', padding: '14px 20px 16px' }}>
                <Button variant="ghost" onClick={onClose} disabled={saving}>Batal</Button>
                <Button
                    onClick={() => onSave(text === null ? form : { ...form, extracted_text: text })}
                    disabled={saving || form.name.trim() === ''}
                >
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            </div>
        </Modal>
    );
}

function FilePicker({ file, extensions, readableExtensions, onPick, onClear }) {
    const accept = extensions.map((ext) => `.${ext.toLowerCase()}`).join(',');
    const unreadable = extensions.filter((ext) => !readableExtensions.includes(ext));

    return (
        <div>
            <label style={labelStyle}>Berkas dokumen</label>

            {file ? (
                <div style={{
                    display: 'flex', alignItems: 'center', gap: '10px', fontSize: '12.5px',
                    background: 'var(--blue-050)', color: 'var(--blue-ink)',
                    borderRadius: '8px', padding: '9px 12px',
                }}>
                    <span style={{ flex: 1, wordBreak: 'break-all' }}>
                        {file.name} · {Math.max(1, Math.round(file.size / 1024))} KB
                    </span>
                    <button
                        type="button"
                        aria-label={`Lepas berkas ${file.name}`}
                        onClick={onClear}
                        style={{ border: 'none', background: 'none', cursor: 'pointer', color: 'inherit', fontWeight: 700, fontSize: '15px', lineHeight: 1 }}
                    >
                        &times;
                    </button>
                </div>
            ) : (
                <input
                    type="file"
                    accept={accept}
                    style={{ ...inputStyle, padding: '9px 10px' }}
                    onChange={(e) => {
                        const picked = e.target.files?.[0];
                        if (picked) onPick(picked);
                    }}
                />
            )}

            <p style={{ fontSize: '11.5px', color: 'var(--slate-500)', margin: '5px 0 0' }}>
                Isi {readableExtensions.join('/')} dibaca otomatis.
                {unreadable.length > 0 && ` Untuk ${unreadable.join('/')}, teksnya masih perlu disalin secara manual. Berkasnya tetap disimpan.`}
                {' '}Berkas disimpan di penyimpanan privat, tidak terjangkau dari web.
            </p>
        </div>
    );
}

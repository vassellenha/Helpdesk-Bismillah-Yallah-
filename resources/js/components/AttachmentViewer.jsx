import { useEffect, useState } from 'react';
import useLockBodyScroll from '../lib/useLockBodyScroll';

// Exported so CommentComposer can decide the same way for a not-yet-uploaded
// File before it has a server URL — one file-type rule, not two copies.
export function isImage(name = '', url = '') {
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name || url);
}

export function isVideo(name = '', url = '') {
    return /\.(mp4|mov|webm)$/i.test(name || url);
}

/*
 | Tipe berkas ditentukan dari NAMANYA, bukan dari header balasan server.
 |
 | Saat helpdesk dibuka lewat portal SINTA, proxy portal mengganti Content-Type
 | sebagian lampiran menjadi `text/html` dan membuang Content-Disposition —
 | terbukti pada tiket yang sama: PDF lolos dengan `application/pdf`, sedangkan
 | JPEG dikirim sebagai `text/html` walau isinya utuh (byte pembukanya tetap
 | ff d8 ff). Browser lalu menampilkan berkas gambar sebagai teks, dan yang
 | terlihat pengguna adalah "simbol-simbol aneh".
 |
 | Karena bytenya tidak pernah rusak, jalan keluarnya bukan menuntut perbaikan
 | proxy: berkas diambil sebagai data mentah, dibungkus ulang dengan tipe yang
 | kita tentukan sendiri dari ekstensinya, lalu ditampilkan dari alamat blob.
*/
const TIPE_BERKAS = {
    png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif',
    webp: 'image/webp', bmp: 'image/bmp',
    mp4: 'video/mp4', mov: 'video/quicktime', webm: 'video/webm',
    pdf: 'application/pdf',
};

function ekstensi(nama = '') {
    return (nama.split('.').pop() ?? '').toLowerCase();
}

/**
 * Bentuk tampilan: 'image' | 'video' | 'pdf' | 'file'.
 *
 * SVG SENGAJA tidak ikut dipratinjau meski ia gambar: ia markup yang bisa
 * memuat skrip, dan menampilkannya dari alamat blob milik halaman ini sama
 * saja menjalankan berkas kiriman orang di dalam sesi yang sedang login.
 * Ia jatuh ke 'file' — tetap bisa diunduh, tidak dirender.
 */
function bentuk(nama = '') {
    const ext = ekstensi(nama);

    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'].includes(ext)) return 'image';
    if (['mp4', 'mov', 'webm'].includes(ext)) return 'video';
    if (ext === 'pdf') return 'pdf';

    return 'file';
}

/**
 * Menampilkan lampiran tiket sebagai chip yang bisa ditekan. Semua jenis berkas
 * dibuka di pratinjau dalam aplikasi: gambar, video, dan PDF dirender; jenis
 * lain menawarkan unduhan. Tidak ada lagi yang dibuka sebagai tab baru berisi
 * byte mentah.
 */
export default function AttachmentViewer({ attachments = [], className = '' }) {
    // { name, bentuk, url } — `url` alamat blob, bukan alamat server.
    const [preview, setPreview] = useState(null);
    const [memuat, setMemuat] = useState(false);
    // Diisi saat berkasnya gagal diambil walau server mengira ia ada — menutup
    // celah antara pemeriksaan keberadaan dan pengambilan sesungguhnya.
    const [loadFailed, setLoadFailed] = useState(false);
    useLockBodyScroll(!!preview || memuat);

    /*
     | Alamat blob memesan memori di browser sampai dilepas sendiri. Tanpa
     | pelepasan ini, membuka dua puluh lampiran dalam satu sesi menahan dua
     | puluh berkas di memori — dan untuk video 30 MB itu terasa.
    */
    useEffect(() => () => {
        if (preview?.url) URL.revokeObjectURL(preview.url);
    }, [preview]);

    if (!attachments.length) return null;

    async function open(a) {
        setLoadFailed(false);
        setMemuat(true);

        try {
            const res = await fetch(a.url, { headers: { Accept: '*/*' } });

            if (!res.ok) throw new Error(String(res.status));

            /*
             | Dibungkus ULANG dengan tipe pilihan kita sendiri.
             |
             | Tipe dari server sengaja diabaikan: lewat proxy portal ia kerap
             | datang sebagai `text/html` walau isinya gambar, dan itu yang
             | membuat berkas tampil sebagai deretan simbol. Nama berkas jauh
             | lebih bisa dipercaya di sini.
            */
            const mentah = await res.blob();
            const tipe = TIPE_BERKAS[ekstensi(a.name)] ?? 'application/octet-stream';

            setPreview({
                name: a.name,
                bentuk: bentuk(a.name),
                url: URL.createObjectURL(new Blob([mentah], { type: tipe })),
            });
        } catch {
            setPreview({ name: a.name, bentuk: 'file', url: null });
            setLoadFailed(true);
        } finally {
            setMemuat(false);
        }
    }

    function tutup() {
        if (preview?.url) URL.revokeObjectURL(preview.url);
        setPreview(null);
        setLoadFailed(false);
    }

    return (
        <>
            <div className={`flex flex-col gap-1.5 ${className}`}>
                {attachments.map((a) => (a.missing || !a.url ? (
                    // The row outlived its file — say so instead of offering a
                    // link that opens a broken image.
                    <div
                        key={a.id}
                        title="Berkas tidak ditemukan di penyimpanan server"
                        className="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-left text-[13px] font-medium text-gray-400 dark:text-ink-3"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                        <span className="truncate line-through">{a.name}</span>
                        <span className="shrink-0 text-[11px] not-italic">berkas hilang</span>
                    </div>
                ) : (
                    <button
                        key={a.id}
                        type="button"
                        onClick={() => open(a)}
                        className="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-left text-[13px] font-medium text-blue-600 dark:text-accent-text hover:bg-gray-100 dark:hover:bg-panel-hover hover:underline"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9" /></svg>
                        <span className="truncate">{a.name}</span>
                    </button>
                )))}
            </div>

            {memuat && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <div className="rounded-xl bg-white/90 dark:bg-panel-2 px-5 py-4 text-[13px] font-semibold text-gray-700 dark:text-ink-1">
                        Membuka lampiran…
                    </div>
                </div>
            )}

            {preview && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onClick={tutup}>
                    <div className="liquid-glass-dense flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-edge px-4 py-3">
                            <button onClick={tutup} className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-900 dark:hover:text-ink-1">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                                Kembali
                            </button>
                            <p className="min-w-0 flex-1 truncate text-center text-[13px] font-semibold text-gray-800 dark:text-ink-1">{preview.name}</p>
                            {/*
                              | Mengunduh dari alamat blob, bukan dari alamat server: berkasnya
                              | sudah ada di browser, dan `download` menjamin namanya utuh walau
                              | proxy portal membuang Content-Disposition.
                            */}
                            {preview.url ? (
                                <a href={preview.url} download={preview.name} className="shrink-0 text-[12px] font-semibold text-blue-600 dark:text-accent-text hover:underline">Unduh</a>
                            ) : <span className="shrink-0" />}
                        </div>
                        <div className="flex flex-1 items-center justify-center overflow-auto bg-gray-50 dark:bg-panel-3 p-4">
                            {loadFailed || !preview.url ? (
                                <div className="flex flex-col items-center gap-2 px-6 py-10 text-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                                    <p className="text-[13px] font-semibold text-gray-700 dark:text-ink-1">Berkas tidak bisa dibuka</p>
                                    <p className="max-w-xs text-[12px] leading-relaxed text-gray-500 dark:text-ink-2">
                                        File “{preview.name}” tidak bisa diambil dari server. Datanya masih tercatat, tapi berkas fisiknya hilang — minta pengunggahnya mengirim ulang.
                                    </p>
                                </div>
                            ) : preview.bentuk === 'video' ? (
                                <video src={preview.url} controls onError={() => setLoadFailed(true)} className="max-h-[70vh] w-full rounded-lg" />
                            ) : preview.bentuk === 'image' ? (
                                <img
                                    src={preview.url}
                                    alt={preview.name}
                                    onError={() => setLoadFailed(true)}
                                    className="max-h-[70vh] max-w-full rounded-lg object-contain"
                                />
                            ) : preview.bentuk === 'pdf' ? (
                                <iframe src={preview.url} title={preview.name} className="h-[70vh] w-full rounded-lg border-0 bg-white" />
                            ) : (
                                /*
                                 | Jenis yang tidak aman atau tidak mungkin dirender browser —
                                 | arsip, dokumen Office, SVG. Ditawarkan diunduh, bukan
                                 | dipaksa tampil: memasang berkas kiriman orang di dalam
                                 | halaman yang sedang login adalah cara termudah menjalankan
                                 | skrip milik orang lain di sesi Anda.
                                */
                                <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><path d="M14 2v6h6" /></svg>
                                    <p className="text-[13px] font-semibold text-gray-700 dark:text-ink-1">Berkas ini tidak bisa ditampilkan di browser</p>
                                    <a href={preview.url} download={preview.name} className="rounded-lg bg-blue-600 px-4 py-2 text-[12.5px] font-semibold text-white hover:bg-blue-700">
                                        Unduh {preview.name}
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

        </>
    );
}

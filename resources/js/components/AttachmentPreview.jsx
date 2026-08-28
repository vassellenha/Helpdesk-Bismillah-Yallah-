import { useEffect, useState } from 'react';
import useLockBodyScroll from '../lib/useLockBodyScroll';

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
 * Mengembalikan byte nol di kepala berkas video yang dibuang proxy portal.
 *
 * Proxy SINTA memangkas byte bernilai nol di AWAL balasan. Gambar dan PDF lolos
 * karena byte pertamanya bukan nol (PNG 89 50, JPEG ff d8, PDF 25 50) —
 * sedangkan MP4 dan MOV selalu diawali empat byte UKURAN yang untuk kotak
 * pembuka nyaris selalu bernilai kecil, jadi tiga byte pertamanya nol dan ikut
 * terbuang. Berkasnya sampai utuh selain itu, tapi browser menolaknya:
 * "DEMUXER_ERROR_COULD_NOT_OPEN".
 *
 * Pemulihannya PASTI, bukan tebakan: format ISO-BMFF menetapkan penanda `ftyp`
 * berada tepat setelah empat byte ukuran, jadi menemukannya di posisi ke-n
 * berarti persis (4 - n) byte nol yang hilang. Diuji pada berkas sungguhan:
 * tanpa ini videonya gagal dibuka, dengan ini durasi dan resolusinya terbaca.
 *
 * Berkas yang sudah benar dibiarkan apa adanya — `ftyp` di posisi 4 tidak
 * memenuhi syarat di bawah, jadi akses langsung (tanpa portal) tidak tersentuh.
 */
function pulihkanVideo(data) {
    const kepala = String.fromCharCode(...data.slice(0, 8));
    const posisi = kepala.indexOf('ftyp');

    if (posisi <= 0 || posisi >= 4) return data;

    const pulih = new Uint8Array(data.length + (4 - posisi));
    pulih.set(data, 4 - posisi);

    return pulih;
}

/**
 * Membuka lampiran di pratinjau dalam aplikasi — dipakai bersama oleh daftar
 * lampiran tiket dan gelembung forum diskusi.
 *
 * Dipisah setelah keduanya sempat berbeda perilaku: lampiran tiket sudah dibuka
 * di dalam aplikasi, sementara forum diskusi masih melempar alamat mentah ke
 * tab baru — dan di sanalah gambar dan video muncul sebagai deretan simbol.
 * Satu jalur, satu perilaku.
 *
 * @return {{ buka: (lampiran: {name: string, url: string}) => void, overlay: JSX.Element }}
 */
export function useAttachmentPreview() {
    // { name, bentuk, url } — `url` alamat blob, bukan alamat server.
    const [preview, setPreview] = useState(null);
    const [memuat, setMemuat] = useState(false);
    // Diisi saat berkasnya gagal diambil walau server mengira ia ada — menutup
    // celah antara pemeriksaan keberadaan dan pengambilan sesungguhnya.
    const [loadFailed, setLoadFailed] = useState(false);
    /*
     | Dipisah dari loadFailed: "tidak bisa DIAMBIL" dan "tidak bisa
     | DITAMPILKAN" adalah dua keadaan berbeda dengan langkah berikutnya yang
     | berbeda pula. Menyamakannya membuat berkas yang sebenarnya utuh dituduh
     | hilang dari server — dan orang lalu meminta pengunggahnya mengirim ulang
     | sesuatu yang tidak pernah rusak.
    */
    const [gagalTampil, setGagalTampil] = useState(false);
    useLockBodyScroll(!!preview || memuat);

    /*
     | Alamat blob memesan memori di browser sampai dilepas sendiri. Tanpa
     | pelepasan ini, membuka dua puluh lampiran dalam satu sesi menahan dua
     | puluh berkas di memori — dan untuk video 30 MB itu terasa.
    */
    useEffect(() => () => {
        if (preview?.url) URL.revokeObjectURL(preview.url);
    }, [preview]);

    async function buka(a) {
        setLoadFailed(false);
        setGagalTampil(false);
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
            const bentukBerkas = bentuk(a.name);
            const tipe = TIPE_BERKAS[ekstensi(a.name)] ?? 'application/octet-stream';

            let isi = new Uint8Array(await res.arrayBuffer());

            if (bentukBerkas === 'video') {
                isi = pulihkanVideo(isi);
            }

            setPreview({
                name: a.name,
                bentuk: bentukBerkas,
                url: URL.createObjectURL(new Blob([isi], { type: tipe })),
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
        setGagalTampil(false);
    }

    const overlay = (
        <>
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
                            ) : gagalTampil ? (
                                <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                                    <p className="text-[13px] font-semibold text-gray-700 dark:text-ink-1">Browser tidak bisa menampilkan berkas ini</p>
                                    <p className="max-w-xs text-[12px] leading-relaxed text-gray-500 dark:text-ink-2">
                                        Berkasnya berhasil diambil dari server — hanya tidak bisa dibuka di sini. Unduh lalu buka dengan aplikasi di komputer Anda.
                                    </p>
                                    <a href={preview.url} download={preview.name} className="rounded-lg bg-blue-600 px-4 py-2 text-[12.5px] font-semibold text-white hover:bg-blue-700">
                                        Unduh {preview.name}
                                    </a>
                                </div>
                            ) : preview.bentuk === 'video' ? (
                                <video src={preview.url} controls onError={() => setGagalTampil(true)} className="max-h-[70vh] w-full rounded-lg" />
                            ) : preview.bentuk === 'image' ? (
                                <img
                                    src={preview.url}
                                    alt={preview.name}
                                    onError={() => setGagalTampil(true)}
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

    return { buka, overlay };
}

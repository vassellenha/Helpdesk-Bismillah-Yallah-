import { useAttachmentPreview } from './AttachmentPreview';

const PAPERCLIP_PATH = 'M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9';

/**
 * Lampiran sebuah balasan diskusi, tampil sebagai chip kecil di dalam gelembung
 * percakapan. `dark` menukar paletnya untuk gelembung biru pesan sendiri, yang
 * warna bawaannya tidak terbaca di sana.
 *
 * TOMBOL, bukan tautan — dan bedanya bukan kosmetik. Sebagai tautan
 * `target="_blank"`, ia melempar alamat mentah lampiran ke tab baru, dan
 * browser menampilkan gambar maupun video sebagai deretan simbol: lewat proxy
 * portal SINTA, tipe kontennya datang sebagai `text/html` walau isinya utuh.
 * Sekarang berkasnya diambil lalu ditampilkan lewat pratinjau yang sama dengan
 * lampiran tiket.
 */
export default function CommentAttachmentChip({ attachment, dark = false }) {
    const { buka, overlay } = useAttachmentPreview();

    if (!attachment) return null;

    return (
        <>
            <button
                type="button"
                onClick={() => buka(attachment)}
                className={`mt-2 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[12px] font-semibold ${dark ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-white dark:bg-panel-2 text-blue-600 dark:text-accent-text hover:underline'}`}
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={PAPERCLIP_PATH} /></svg>
                <span className="truncate">{attachment.name}</span>
            </button>
            {overlay}
        </>
    );
}

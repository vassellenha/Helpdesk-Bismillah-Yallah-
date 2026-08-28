import { useAttachmentPreview } from './AttachmentPreview';

// Exported so CommentComposer can decide the same way for a not-yet-uploaded
// File before it has a server URL — one file-type rule, not two copies.
export function isImage(name = '', url = '') {
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name || url);
}

export function isVideo(name = '', url = '') {
    return /\.(mp4|mov|webm)$/i.test(name || url);
}

/**
 * Menampilkan lampiran tiket sebagai chip yang bisa ditekan. Semua jenis berkas
 * dibuka di pratinjau dalam aplikasi: gambar, video, dan PDF dirender; jenis
 * lain menawarkan unduhan. Tidak ada lagi yang dibuka sebagai tab baru berisi
 * byte mentah.
 */
export default function AttachmentViewer({ attachments = [], className = '' }) {
    const { buka, overlay } = useAttachmentPreview();

    if (!attachments.length) return null;

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
                        onClick={() => buka(a)}
                        className="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-left text-[13px] font-medium text-blue-600 dark:text-accent-text hover:bg-gray-100 dark:hover:bg-panel-hover hover:underline"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9" /></svg>
                        <span className="truncate">{a.name}</span>
                    </button>
                )))}
            </div>

            {overlay}
        </>
    );
}

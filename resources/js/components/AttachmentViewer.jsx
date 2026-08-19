import { useState } from 'react';
import useLockBodyScroll from '../lib/useLockBodyScroll';

// Exported so CommentComposer can decide the same way for a not-yet-uploaded
// File before it has a server URL — one file-type rule, not two copies.
export function isImage(name = '', url = '') {
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name || url);
}

export function isVideo(name = '', url = '') {
    return /\.(mp4|mov|webm)$/i.test(name || url);
}

/**
 * Renders a ticket's attachments as clickable chips. Images open in an in-app
 * preview modal (with a Back button, sized down instead of raw full-screen);
 * non-images (PDF, etc.) open in a new tab. "Buka penuh" is always available
 * for the raw file.
 */
export default function AttachmentViewer({ attachments = [], className = '' }) {
    const [preview, setPreview] = useState(null); // { name, url }
    // Set when the file 404s despite the server believing it exists — covers the
    // gap between the existence check and the browser actually fetching it.
    const [loadFailed, setLoadFailed] = useState(false);
    useLockBodyScroll(!!preview);

    if (!attachments.length) return null;

    function open(a) {
        setLoadFailed(false);
        if (isImage(a.name, a.url) || isVideo(a.name, a.url)) {
            setPreview(a);
        } else {
            window.open(a.url, '_blank', 'noopener');
        }
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

            {preview && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onClick={() => setPreview(null)}>
                    <div className="liquid-glass flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-edge px-4 py-3">
                            <button onClick={() => setPreview(null)} className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-900 dark:hover:text-ink-1">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                                Kembali
                            </button>
                            <p className="min-w-0 flex-1 truncate text-center text-[13px] font-semibold text-gray-800 dark:text-ink-1">{preview.name}</p>
                            <a href={preview.url} target="_blank" rel="noreferrer" className="shrink-0 text-[12px] font-semibold text-blue-600 dark:text-accent-text hover:underline">Buka penuh</a>
                        </div>
                        <div className="flex flex-1 items-center justify-center overflow-auto bg-gray-50 dark:bg-panel-3 p-4">
                            {loadFailed ? (
                                <div className="flex flex-col items-center gap-2 px-6 py-10 text-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 dark:text-ink-3"><path d="M12 9v4" /><path d="M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                                    <p className="text-[13px] font-semibold text-gray-700 dark:text-ink-1">Berkas tidak bisa dibuka</p>
                                    <p className="max-w-xs text-[12px] leading-relaxed text-gray-500 dark:text-ink-2">
                                        File “{preview.name}” tidak ada di penyimpanan server. Datanya masih tercatat, tapi berkas fisiknya hilang — minta requester mengunggah ulang.
                                    </p>
                                </div>
                            ) : isVideo(preview.name, preview.url) ? (
                                <video src={preview.url} controls onError={() => setLoadFailed(true)} className="max-h-[70vh] w-full rounded-lg" />
                            ) : (
                                <img
                                    src={preview.url}
                                    alt={preview.name}
                                    onError={() => setLoadFailed(true)}
                                    className="max-h-[70vh] max-w-full rounded-lg object-contain"
                                />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

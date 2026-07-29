import { useState } from 'react';
import useLockBodyScroll from '../lib/useLockBodyScroll';

function isImage(name = '', url = '') {
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name || url);
}

function isVideo(name = '', url = '') {
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
    useLockBodyScroll(!!preview);

    if (!attachments.length) return null;

    return (
        <>
            <div className={`flex flex-col gap-1.5 ${className}`}>
                {attachments.map((a) => (
                    <button
                        key={a.id}
                        type="button"
                        onClick={() => (isImage(a.name, a.url) || isVideo(a.name, a.url) ? setPreview(a) : window.open(a.url, '_blank', 'noopener'))}
                        className="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-left text-[13px] font-medium text-blue-600 dark:text-accent-text hover:bg-gray-100 dark:hover:bg-panel-hover hover:underline"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9" /></svg>
                        <span className="truncate">{a.name}</span>
                    </button>
                ))}
            </div>

            {preview && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/70 p-4" onClick={() => setPreview(null)}>
                    <div className="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-edge px-4 py-3">
                            <button onClick={() => setPreview(null)} className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-900 dark:hover:text-ink-1">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                                Kembali
                            </button>
                            <p className="min-w-0 flex-1 truncate text-center text-[13px] font-semibold text-gray-800 dark:text-ink-1">{preview.name}</p>
                            <a href={preview.url} target="_blank" rel="noreferrer" className="shrink-0 text-[12px] font-semibold text-blue-600 dark:text-accent-text hover:underline">Buka penuh</a>
                        </div>
                        <div className="flex flex-1 items-center justify-center overflow-auto bg-gray-50 dark:bg-panel-3 p-4">
                            {isVideo(preview.name, preview.url) ? (
                                <video src={preview.url} controls className="max-h-[70vh] w-full rounded-lg" />
                            ) : (
                                <img src={preview.url} alt={preview.name} className="max-h-[70vh] max-w-full rounded-lg object-contain" />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

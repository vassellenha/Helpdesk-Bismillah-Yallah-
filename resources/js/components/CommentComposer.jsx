import { useEffect, useRef, useState } from 'react';
import { isImage, isVideo } from './AttachmentViewer';
import useLockBodyScroll from '../lib/useLockBodyScroll';

// Kept in sync by hand with TicketDiscussion::ALLOWED_ATTACHMENT_MIMES and
// ::MAX_ATTACHMENT_KB server-side — this is a UX shortcut (reject obviously
// bad picks before a round trip), not the source of truth; the server
// re-validates everything regardless.
export const COMMENT_ATTACHMENT_ACCEPT = '.png,.jpg,.jpeg,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.mov,.webm';
export const MAX_COMMENT_ATTACHMENT_BYTES = 5 * 1024 * 1024;

const PAPERCLIP_PATH = 'M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9';

// Auto-grow bounds, in px. The floor keeps an empty box from collapsing to a
// single cramped line; the ceiling stops a long reply from pushing the Kirim
// button off-screen — past it the textarea scrolls internally instead.
const MIN_TEXTAREA_HEIGHT = 60;
const MAX_TEXTAREA_HEIGHT = 220;

/**
 * The "write a reply" row shared by every role's discussion thread
 * (Requester/Support/Support BPO/Approver — Team Lead has no reply UI at
 * all yet, unrelated to this). Message text stays controlled by the parent
 * (each already had `reply`/`setReply` state); the picked file is owned
 * locally here and only handed to the parent, as the raw File, when Send is
 * pressed — so callers don't each need their own file/attachError state.
 */
export default function CommentComposer({ value, onChange, onSend, sending, placeholder, sendingLabel, sendLabel }) {
    const [file, setFile] = useState(null);
    const [error, setError] = useState('');
    const [previewUrl, setPreviewUrl] = useState(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const textareaRef = useRef(null);
    useLockBodyScroll(previewOpen);

    // Driven off `value` rather than the change event so the box also shrinks
    // back after a send clears it — the parent owns the text, so that reset
    // arrives as a prop change with no event of its own.
    useEffect(() => {
        const el = textareaRef.current;
        if (!el) return;

        // Collapse first: scrollHeight only reports the content's true height
        // when the element isn't already stretched to the previous value.
        el.style.height = 'auto';
        el.style.height = `${Math.min(Math.max(el.scrollHeight, MIN_TEXTAREA_HEIGHT), MAX_TEXTAREA_HEIGHT)}px`;
    }, [value]);

    // A picked file has no server URL yet — a local blob: URL is the only way
    // to preview it before Send. Revoked on every change/unmount so a stream
    // of picked-then-cleared files doesn't leak memory for the session.
    useEffect(() => {
        if (!file) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    function openPreview() {
        if (!file || !previewUrl) return;
        if (isImage(file.name) || isVideo(file.name)) {
            setPreviewOpen(true);
        } else {
            window.open(previewUrl, '_blank', 'noopener');
        }
    }

    function pickFile(e) {
        const picked = e.target.files?.[0];
        e.target.value = '';
        if (!picked) return;

        if (picked.size > MAX_COMMENT_ATTACHMENT_BYTES) {
            setError('Lampiran maksimal 5MB.');
            return;
        }

        setError('');
        setFile(picked);
    }

    function send() {
        onSend(file);
        setFile(null);
        setError('');
    }

    return (
        <>
            <div className="mt-4 border-t border-gray-100 dark:border-edge pt-4">
                {file && (
                    <div className="mb-2 flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-1.5 text-[12px] font-medium text-gray-600 dark:text-ink-2">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0"><path d={PAPERCLIP_PATH} /></svg>
                        <button type="button" onClick={openPreview} className="truncate text-left hover:underline" title="Lihat pratinjau">
                            {file.name}
                        </button>
                        <button type="button" onClick={() => setFile(null)} className="ml-auto shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-ink-1">✕</button>
                    </div>
                )}
                {error && <p className="mb-2 text-[12px] text-red-600 dark:text-bad-text">{error}</p>}
                <textarea
                    ref={textareaRef}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className="block w-full resize-none overflow-y-auto rounded-xl border border-gray-200 dark:border-edge-strong px-3.5 py-2.5 text-[13px] outline-none focus:border-blue-400"
                />
                <div className="mt-2 flex items-center justify-end gap-2">
                    <label
                        title="Lampirkan berkas (maks. 5MB)"
                        className="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-panel-hover dark:hover:text-ink-2"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={PAPERCLIP_PATH} /></svg>
                        <input type="file" accept={COMMENT_ATTACHMENT_ACCEPT} className="hidden" onChange={pickFile} />
                    </label>
                    <button
                        onClick={send}
                        disabled={sending || (!value.trim() && !file)}
                        className="rounded-full bg-blue-600 dark:bg-blue-500 px-5 py-2 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {sending ? sendingLabel : sendLabel}
                    </button>
                </div>
            </div>

            {previewOpen && file && previewUrl && (
                <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" onClick={() => setPreviewOpen(false)}>
                    <div className="liquid-glass flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-edge px-4 py-3">
                            <button onClick={() => setPreviewOpen(false)} className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[13px] font-bold text-gray-600 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-900 dark:hover:text-ink-1">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                                Kembali
                            </button>
                            <p className="min-w-0 flex-1 truncate text-center text-[13px] font-semibold text-gray-800 dark:text-ink-1">{file.name}</p>
                            <a href={previewUrl} target="_blank" rel="noreferrer" className="shrink-0 text-[12px] font-semibold text-blue-600 dark:text-accent-text hover:underline">Buka penuh</a>
                        </div>
                        <div className="flex flex-1 items-center justify-center overflow-auto bg-gray-50 dark:bg-panel-3 p-4">
                            {isVideo(file.name) ? (
                                <video src={previewUrl} controls className="max-h-[70vh] w-full rounded-lg" />
                            ) : (
                                <img src={previewUrl} alt={file.name} className="max-h-[70vh] max-w-full rounded-lg object-contain" />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

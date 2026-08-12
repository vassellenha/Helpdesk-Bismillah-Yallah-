const PAPERCLIP_PATH = 'M21.4 11.1 12.7 19.8a4.5 4.5 0 0 1-6.4-6.4l8.7-8.7a3 3 0 0 1 4.2 4.2l-8.6 8.6a1.5 1.5 0 0 1-2.1-2.1l7.9-7.9';

/**
 * A discussion reply's attachment, shown as a small link inside the chat
 * bubble. `dark` switches the palette for replies rendered on the filled
 * blue "own message" bubble, where the AttachmentViewer's default
 * blue-on-white styling would be unreadable.
 */
export default function CommentAttachmentChip({ attachment, dark = false }) {
    if (!attachment) return null;

    return (
        <a
            href={attachment.url}
            target="_blank"
            rel="noreferrer"
            className={`mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-[12px] font-semibold ${dark ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-white dark:bg-panel-2 text-blue-600 dark:text-accent-text hover:underline'}`}
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={PAPERCLIP_PATH} /></svg>
            <span className="truncate">{attachment.name}</span>
        </a>
    );
}

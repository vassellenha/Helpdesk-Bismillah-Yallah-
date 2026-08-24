import AnchoredMenu from './AnchoredMenu';

const TONE = {
    default: 'text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]',
    danger: 'text-red-600 dark:text-bad-text hover:bg-red-50',
    success: 'text-emerald-600 dark:text-ok-text hover:bg-emerald-50',
};

/**
 * Small "•••" dropdown for a table row — Edit / Aktifkan / Nonaktifkan style
 * actions. Posisi, penutupan saat klik di luar, dan penyesuaian saat digulir
 * ditangani AnchoredMenu — pemanggil cukup menyimpan elemen tombolnya dan
 * menggantinya dengan `null` di `onClose`.
 */
export default function RowActionMenu({ anchorEl, items, onClose, width = 176 }) {
    return (
        <AnchoredMenu
            anchorEl={anchorEl}
            onClose={onClose}
            width={width}
            className="z-50 overflow-hidden rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-xl"
        >
            {items.map((item) => (
                <div key={item.label}>
                    {item.divider && <div className="my-1 h-px bg-gray-100 dark:bg-panel-3" />}
                    <button
                        type="button"
                        onClick={() => { item.onClick(); onClose(); }}
                        className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium transition-colors ${TONE[item.tone ?? 'default']}`}
                    >
                        {item.icon && (
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                                <path d={item.icon} />
                            </svg>
                        )}
                        {item.label}
                    </button>
                    {/* Warns before the click when an action cannot fully take effect. */}
                    {item.note && (
                        <p className="px-3 pb-2 text-[11px] leading-snug text-gray-400 dark:text-ink-3">{item.note}</p>
                    )}
                </div>
            ))}
        </AnchoredMenu>
    );
}

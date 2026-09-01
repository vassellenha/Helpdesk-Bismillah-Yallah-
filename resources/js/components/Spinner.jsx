/** Small circular spinner — inline icon, not a layout. */
export function Spinner({ size = 18, className = 'text-blue-600 dark:text-accent-text' }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            className={`animate-spin ${className}`}
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.2" strokeWidth="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

/** Centered spinner + label, for panels/pages waiting on their first fetch. */
export function LoadingState({ label = 'Memuat…', className = 'flex-1' }) {
    return (
        <div className={`flex flex-col items-center justify-center gap-3 py-16 ${className}`}>
            <Spinner size={26} />
            <p className="text-[13px] font-medium text-gray-400 dark:text-ink-3">{label}</p>
        </div>
    );
}

/** A single pulsing gray bar — building block for skeleton layouts. */
export function SkeletonBar({ className = 'h-3 w-full' }) {
    return <div className={`animate-pulse rounded-md bg-gray-200 dark:bg-panel-3 ${className}`} />;
}

/** Thin indeterminate progress bar, for a background refresh (data already on screen). */
export function TopProgressBar({ active }) {
    if (!active) return null;
    return (
        <div className="fixed inset-x-0 top-0 z-[100] h-[3px] overflow-hidden bg-blue-100 dark:bg-accent-soft">
            <div className="h-full w-1/3 animate-loading-bar rounded-r-full bg-blue-600 dark:bg-blue-500" />
        </div>
    );
}

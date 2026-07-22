/**
 * Read-only rendering of a Requester's post-close feedback — star rating on
 * top, their optional note directly beneath it, as one integrated unit.
 * Shared by the Requester, Approver, and Support (IT/BPO) ticket-detail
 * pages so every role sees the exact same feedback once a ticket is Closed.
 */
export default function FeedbackDisplay({ rating, note }) {
    if (!rating) {
        return null;
    }

    return (
        <div className="flex flex-col items-center gap-2 text-center">
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <svg
                        key={n}
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill={n <= rating ? '#f59e0b' : 'none'}
                        stroke={n <= rating ? '#f59e0b' : '#d1d5db'}
                        strokeWidth="1.6"
                        strokeLinejoin="round"
                    >
                        <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z" />
                    </svg>
                ))}
            </div>
            <span className="text-[11px] text-gray-400">{rating}/5 dari Requester</span>
            {note && (
                <p className="mt-1.5 w-full rounded-lg bg-gray-50 p-3 text-left text-[13px] leading-relaxed text-gray-700">
                    {note}
                </p>
            )}
        </div>
    );
}

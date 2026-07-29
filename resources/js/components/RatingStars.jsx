/**
 * The five-star row on its own — one definition shared by every place a
 * satisfaction rating is shown (each role's SLA panel and the Admin ticket
 * table), so a rating never renders differently depending on which screen you
 * happen to be looking at.
 *
 * `muted` greys the stars out for a rating an Admin has excluded from the
 * average (tickets.rating_active = false) — still visible, visibly not counted.
 */
export default function RatingStars({ rating, size = 14, muted = false }) {
    if (!rating) {
        return null;
    }

    return (
        <span className={`flex items-center gap-0.5 ${muted ? 'opacity-40 grayscale' : ''}`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <svg
                    key={n}
                    width={size}
                    height={size}
                    viewBox="0 0 24 24"
                    fill={n <= rating ? '#f59e0b' : 'none'}
                    stroke={n <= rating ? '#f59e0b' : '#d1d5db'}
                    strokeWidth="1.6"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z" />
                </svg>
            ))}
        </span>
    );
}

/**
 * Rating as it appears inside a ticket's SLA panel: label, stars, score, and
 * the requester's note underneath. Renders a plain "Belum dinilai" line when
 * the ticket has no rating yet, so the row never silently disappears and leave
 * the reader wondering whether it was never asked or never answered.
 */
export function SlaRatingRow({ rating, note, ratingActive = true }) {
    return (
        <div className="border-t border-gray-100 dark:border-edge pt-3.5">
            <div className="flex items-center justify-between">
                <span className="text-[13px] text-gray-500 dark:text-ink-2">Rating Requester</span>
                {rating ? (
                    <span className="flex items-center gap-1.5">
                        <RatingStars rating={rating} muted={!ratingActive} />
                        <span className="text-[13px] font-semibold text-gray-800 dark:text-ink-1">{rating}/5</span>
                    </span>
                ) : (
                    <span className="text-[13px] font-semibold text-gray-400 dark:text-ink-3">Belum dinilai</span>
                )}
            </div>
            {rating && !ratingActive && (
                <p className="mt-1 text-[11px] text-gray-400 dark:text-ink-3">Dikecualikan Admin dari perhitungan rata-rata.</p>
            )}
            {note && (
                <p className="mt-2 rounded-lg bg-gray-50 dark:bg-panel-3 p-2.5 text-[12px] leading-relaxed text-gray-700 dark:text-ink-2">
                    {note}
                </p>
            )}
        </div>
    );
}

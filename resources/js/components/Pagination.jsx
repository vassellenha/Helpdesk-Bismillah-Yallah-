import { t as trans } from '../lib/i18n';
import { pageCount, pageRange } from '../lib/pagination';

/**
 * Footer for the role ticket lists: the "showing" counter on the left and the
 * page controls on the right. Labels come from the `common` group, which every
 * layout ships, so this renders correctly on any role's screen.
 *
 * The counter stays visible on a single page — it is the only place a reader
 * can see how many tickets the current filter matched — while the prev/next
 * controls hide, since there is nowhere to go.
 */
export default function Pagination({ page, total, onPageChange }) {
    const totalPages = pageCount(total);
    const current = Math.min(Math.max(1, page), totalPages);
    const { from, to } = pageRange(current, total);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-4 py-3">
            <span className="text-xs text-gray-400 dark:text-ink-3">
                {total === 0 ? trans('common.pagination.empty') : trans('common.pagination.showing', { from, to, total })}
            </span>

            {totalPages > 1 && (
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => onPageChange(current - 1)}
                        disabled={current === 1}
                        className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-[13px] font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {trans('common.pagination.prev')}
                    </button>
                    <span className="text-[13px] text-gray-500 dark:text-ink-2">
                        {trans('common.pagination.page', { page: current, total: totalPages })}
                    </span>
                    <button
                        type="button"
                        onClick={() => onPageChange(current + 1)}
                        disabled={current === totalPages}
                        className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-[13px] font-medium text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {trans('common.pagination.next')}
                    </button>
                </div>
            )}
        </div>
    );
}

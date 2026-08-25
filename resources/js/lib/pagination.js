/**
 * Client-side paging for the ticket lists.
 *
 * The role list pages receive their whole result set from Blade in one prop,
 * so paging here is a slice over an array rather than a request per page. The
 * Audit Trail Viewer pages on the server instead — it reads a table that grows
 * without bound — and keeps its own logic.
 *
 * Same page size as the Audit Trail Viewer, so a reader moving between the two
 * screens sees pages of the same length.
 */
export const PAGE_SIZE = 15;

/** Always at least 1: an empty list is still "page 1 of 1", never "of 0". */
export function pageCount(total, size = PAGE_SIZE) {
    return Math.max(1, Math.ceil(total / size));
}

/**
 * Filtering can strand the reader past the last page — 43 rows narrowed to 4
 * while sitting on page 3 would render an empty table under working controls.
 */
export function clampPage(page, total, size = PAGE_SIZE) {
    return Math.min(Math.max(1, page), pageCount(total, size));
}

export function pageSlice(rows, page, size = PAGE_SIZE) {
    const start = (clampPage(page, rows.length, size) - 1) * size;
    return rows.slice(start, start + size);
}

/** One-based inclusive bounds for the "Menampilkan :from–:to dari :total" line. */
export function pageRange(page, total, size = PAGE_SIZE) {
    if (total === 0) return { from: 0, to: 0 };

    const current = clampPage(page, total, size);
    return { from: (current - 1) * size + 1, to: Math.min(current * size, total) };
}

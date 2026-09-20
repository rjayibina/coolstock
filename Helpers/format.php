<?php
/**
 * Helpers/format.php
 * Small display-formatting helpers shared across views. Loaded once from
 * index.php so every view can call these without an extra require.
 */

/** Formats a MySQL DATETIME/TIMESTAMP string ('2026-09-03 17:59:53') as
 *  'mm-dd-yyyy h:mm AM/PM' ('09-03-2026 5:59 PM') for display - the raw
 *  column value is yyyy-mm-dd and 24-hour time, which is what every
 *  Date column was showing before this existed (created_at is echoed
 *  straight from the DB in Views/transactions/index.php and
 *  Views/dashboard/index.php). Returns an em dash for empty/null, and
 *  the original string unchanged if it isn't a parseable date rather
 *  than silently hiding a bad value. */
function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $value = trim($value);
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    // DATE columns (transaction_date) carry no time, and rendering them
    // through the datetime format stamped a meaningless "12:00 AM" on
    // every row. Only show a time when the value actually has one.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return date('m-d-Y', $timestamp);
    }

    return date('m-d-Y g:i A', $timestamp);
}

/** Windowed page-number list for a pagination bar: always the first and
 *  last page, the current page plus $window neighbours either side, and
 *  a null entry (render as an ellipsis) wherever a gap is skipped.
 *  Every list page (Products, Transactions, Categories, Brands, Item
 *  Types, Locations, Users, Item Requests) built its own
 *  `for ($p = 1; $p <= $totalPages; $p++)` loop, which is fine at 5
 *  pages but prints one button per page forever - a year of
 *  transactions at 20/page is 50+ buttons wide. This is the one
 *  shared implementation; each view's loop calls it instead of
 *  counting to $totalPages itself.
 *
 *  Returns e.g. [1, null, 4, 5, 6, null, 12] for page 5 of 12. Returns
 *  every page number unchanged (no ellipsis) whenever $totalPages is
 *  small enough that a gap would save nothing. */
function paginate_page_numbers(int $current, int $totalPages, int $window = 1): array
{
    if ($totalPages <= 1) {
        return $totalPages === 1 ? [1] : [];
    }

    // Small enough that windowing would save nothing worth an ellipsis -
    // just list every page, exactly like the old unconditional loop did.
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $current = max(1, min($current, $totalPages));
    $pages = [1, $totalPages];
    for ($p = $current - $window; $p <= $current + $window; $p++) {
        if ($p >= 1 && $p <= $totalPages) {
            $pages[] = $p;
        }
    }
    $pages = array_values(array_unique($pages));
    sort($pages);

    $result = [];
    $prev = null;
    foreach ($pages as $p) {
        if ($prev !== null) {
            $gap = $p - $prev;
            if ($gap === 2) {
                // Exactly one page skipped - showing it costs the same
                // width as an ellipsis would, so just show it.
                $result[] = $prev + 1;
            } elseif ($gap > 2) {
                $result[] = null; // wider gap -> render as an ellipsis
            }
        }
        $result[] = $p;
        $prev = $p;
    }
    return $result;
}

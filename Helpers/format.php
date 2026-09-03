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
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('m-d-Y g:i A', $timestamp);
}

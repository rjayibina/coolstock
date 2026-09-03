# CoolStock — Project Status (as of 2026-09-03, end of session)

PHP/MySQL inventory system (custom MVC, no framework) for an AC parts/units
business. Router: `index.php` → `Controllers/*` → `Views/*`. No PHP CLI in
Claude's sandbox and no DB connection, so nothing this session was actually
executed — everything below is implemented and manually reviewed
(brace/paren balance + structural read-through) but **not yet run**. Test
in XAMPP before trusting it.

## What shipped this session (Phases 1–4, in order, each approved before the next)

### Phase 1 — Products: Stock Out overhaul
- Removed the `+` (Stock In) button/action from the Products table entirely —
  Stock In now only happens via Delivery.
- `−` button replaced with a labeled "Stock Out" button.
- Wired `item_types.requires_serial` into the app for the first time (see
  **Migration gotcha** below — this column's history is messier than it
  looked mid-session).
- Single Stock Out modal: Asset-type (or any `requires_serial` type)
  products get a dynamic serial-number-per-unit list instead of a plain
  quantity field; Consumables keep the plain quantity field.
- New "Stock Out Selected" bulk action on Products (checkbox bulk bar) →
  modal with one quantity-or-serial-list row per selected product, one
  shared location/date/released-by. Server-side: `TransactionController::
  bulkStockOut()`, validates every line's availability before writing
  anything (no partial bulk stock-outs, same convention Transfer already
  used), and re-validates serial-required items server-side (not just in
  the JS).
- Files: `Models/ItemType.php`, `Controllers/ItemTypeController.php`,
  `Views/itemtypes/index.php`, `Models/InventoryItem.php`,
  `Models/Transaction.php` (added `serial_number`), `Controllers/
  TransactionController.php`, `Views/products/index.php`, `index.php`
  (router allow-list).

### Phase 2 — Product Movement: order/transfer reference numbers + rename
- New `transactions.reference_number` column. Every row written by one
  Delivery or Transfer submission now shares one value: `DO-000001`,
  `DO-000002`, ... for Delivery, `TR-000001`, ... for Transfer.
  `Transaction::nextReferenceNumber($prefix)` generates it (no
  transaction/lock — fine for this single-user XAMPP setup, would matter
  if this ever went multi-user).
- Product Movement list: new "Order/Transfer #" column, clickable →
  fetches via new `TransactionController::batch()` JSON endpoint → modal
  listing every product in that delivery/transfer (with serial numbers
  where applicable).
- "Delivery" relabeled to "Stock In" **only on the Product Movement page**
  (`Transaction::movementLabel()`) — Dashboard's bar chart and recent-
  activity badge still say "Delivery" for the same rows, since the
  request was scoped "sa Product Movement". Flagged to the user as a
  decision, not confirmed changed elsewhere. If they want it dashboard-
  wide too, swap `typeLabel()` → `movementLabel()` on the two Dashboard
  call sites in `Views/dashboard/index.php`.
- Files: `Models/Transaction.php`, `Controllers/DeliveryController.php`,
  `Controllers/TransferController.php`, `Controllers/
  TransactionController.php` (`batch()`), `index.php`, `Views/
  transactions/index.php`.

### Phase 3 — Delivery & Transfer layout + pagination + manual product add
- Both product tables paginated client-side (10/page, rows hidden not
  removed — preserves already-typed quantities across pages and on a
  failed-validation re-render). Search suspends pagination and shows all
  matches, matching Products page convention.
- Delivery only: "Add Product Manually" section — click to append a row
  (Model, optional Category, optional Item Type, Quantity). On submit,
  each filled row creates a real catalog product first
  (`InventoryItem::create()`), then logs it as a normal delivery line
  against the new `item_id` — same reference number, same stock
  adjustment as every other line. Blank rows are silently dropped
  server-side. Not added to Transfer (a transfer moves stock that must
  already exist, so it doesn't apply there).
- New product gets `brand_id = null` and every AC-spec field left blank —
  same as any quick add-with-just-a-model product. Not asked for more
  than that; flagged as an easy extension if wanted later.
- Files: `Controllers/DeliveryController.php` (rewritten),
  `Views/delivery/index.php` (rewritten), `Views/transfer/index.php`
  (rewritten).

### Phase 4 — Predictive Stock Alert (MTBS, stock-out history — not sales)
- Implements `PREDICTIVE_STOCKOUT_ALERT.md`'s methodology as-written:
  Mean Time Between Stockouts per (item, location) pair, replayed from
  `transactions` history (Stock In/Out, Delivery, Transfer only — Item
  Request/Borrow/Return never move stock, excluded from the replay).
  A Transfer affects two pairs at once (negative at FROM, positive at
  TO), mirroring what `ItemStock::adjust()` does incrementally.
- `ItemStock::predictedStockouts($alertWindowDays = 7, $frequencyWindowDays = 90)`:
  two queries total (all item_stock pairs, all relevant transactions),
  then the whole replay happens in PHP — no N+1 queries. Returns:
  - `status = 'actual'`: quantity is 0 right now (no history needed).
  - `status = 'predicted'`: quantity > 0, n ≥ 2 past stockouts, and the
    projected next one falls within the alert window. Confidence tier:
    Low (n=2–3), Medium (n=4), High (n≥5), per the doc.
  - Every row also carries `stockout_frequency` (stockouts per 30 days,
    trailing 90-day window) as a secondary sortable-in-spirit figure —
    the doc's "chronically understocked" signal. Not made click-sortable
    in the UI; just displayed as a column.
- Surfaced as a new "Predicted Stockouts" table on the Dashboard, between
  the existing charts and Recent Transactions, reusing the existing
  `.alert-warning`/badge styling conventions.
- Files: `Models/ItemStock.php` (`predictedStockouts()`),
  `Controllers/DashboardController.php`, `Views/dashboard/index.php`.

## Database setup — now a single file

**Everything below about "run migration #X" is superseded.** Every
migration this session (and every one before it) has been traced in
full and consolidated into one authoritative file:
**`database/coolstock_full_setup.sql`**. It contains the complete
current schema (including the `item_types.requires_serial` gotcha fix
and the new `reference_number`/`serial_number` columns) plus
professional seed data — items #1–11 unchanged from before, plus two
new items (#12 LG Split Type, #13 Copper Pipe Coil) seeded with a real
Delivery (`DO-000001`, both products in one order), a Transfer
(`TR-000001`), and a serialized Stock Out, so a fresh install
demonstrates every feature built this session out of the box.

**To rebuild from scratch:** drop the database, recreate it empty, and
import only `coolstock_full_setup.sql`. Nothing else in `database/` is
needed anymore — every individual `migration_*.sql` file,
`mister_aircon.sql` (the original pre-migration schema), and
`seed_professional_data.sql` (redundant with the seed data now embedded
in the consolidated file) were deleted this session. Two user-facing
error messages that referenced the old files by name (`Controllers/
TransactionController.php`, `Controllers/DashboardController.php`) were
updated to point at `coolstock_full_setup.sql` instead — everywhere else
those filenames appear is just historical code-comment narrative
(harmless, left alone).

**If you're NOT starting fresh** (keeping your existing live DB with
real data), the migration gotcha fix and reference_number column still
need to be applied by hand — the exact `ALTER TABLE` statements are in
the git history / this session's transcript if you need them
individually rather than as a full rebuild.

## Migration gotcha found this session (historical — already folded into coolstock_full_setup.sql above)

While auditing the `.sql` files for cleanup, found that
`migration_move_requires_serial_to_itemtype.sql` (which Phase 1 assumed
had already put `requires_serial` on `item_types`) was **later reversed**
by `migration_strip_lookup_tables_to_id_name.sql`, which dropped it again
as part of a broader lookup-table simplification pass. Nothing after that
re-added it — so if migrations were applied in file order, the live DB
almost certainly does **not** have `item_types.requires_serial` right now,
which would make Phase 1's Item Type checkbox / Stock Out serial logic
throw a "Unknown column" SQL error.

Fixed with a new corrective migration:
**`database/migration_readd_itemtype_requires_serial.sql`** — re-adds the
column, reseeds Asset=1/Consumable=0. Run `DESCRIBE item_types;` first; if
`requires_serial` is somehow already there, skip this migration (it'll
just fail on a duplicate-column error, harmlessly).

## Migrations to run on the live DB — superseded, see "Database setup" above

This section originally listed two migrations to run individually. That's
no longer the recommended path: drop and reimport
`database/coolstock_full_setup.sql` instead (see above). Left here only
because the reasoning below (why 3 files were deleted, why others were
kept) is still accurate and worth keeping.

## SQL files removed this session (fully superseded, zero net effect on current schema)

First pass (mid-session, before the full consolidation):
- `migration_category_requires_serial.sql` — added `item_categories.
  requires_serial`; fully reversed by `migration_move_requires_serial_to_
  itemtype.sql` (which drops that column entirely and moves the flag to
  `item_types` instead).
- `migration_products_location_id.sql` — added `inventory_items.
  location_id`; permanently reversed by `migration_add_stock_by_location.
  sql` (per-location stock via `item_stock` replaced the single-location-
  per-product model).
- `migration_readd_location_to_products.sql` — re-added the same column a
  second time, also permanently reversed by the same
  `migration_add_stock_by_location.sql`.

Second pass (once `coolstock_full_setup.sql` was fully brought up to date
and verified column-for-column against every current Model file): **every
remaining `migration_*.sql` file, plus `mister_aircon.sql`** (the
original pre-migration schema — `item_name`/`quantity_on_hand`/
`serial_number`-on-products/etc., none of which exist anymore) **and
`seed_professional_data.sql`** (a standalone reseed script, now redundant
with the seed data embedded directly in `coolstock_full_setup.sql`) were
all deleted. `database/` now contains exactly one file.

The reasoning that made the *first pass* safe (only delete a migration
once its entire effect is provably gone from the current schema, not just
partially reversed) is what made the *second pass* safe too — but this
time by exhaustively cross-referencing the consolidated schema against
every Model file's actual column list, not just tracing individual
migrations against each other.

**`coolstock_full_setup.sql` was itself out of date going into this
pass** — missing `item_types.requires_serial`, `transactions.
reference_number`, and `transactions.serial_number` entirely. All three
are now in it, confirmed against `Models/ItemType.php` and `Models/
Transaction.php` directly (the actual source of truth for what the app
expects), not just against the migration files' stated intent.

Two user-facing error messages (`Controllers/TransactionController.php`,
`Controllers/DashboardController.php`) referenced the deleted files by
name and were updated to point at `coolstock_full_setup.sql` instead.
Every other reference to a migration filename left in the codebase is
historical code-comment narrative only (e.g. "see migration_add_stock_by_
location.sql" explaining *why* a column exists) — harmless, intentionally
left alone.

## Pending on your end

1. Drop the database, recreate it empty, import `coolstock_full_setup.sql`.
2. Test all four phases in XAMPP — nothing this session was executed
   against a real database (no PHP CLI, no DB access in the sandbox),
   only reviewed by hand (brace/paren balance + structural read-through).
3. Decide: should "Stock In" relabeling extend to the Dashboard too (Phase
   2 note above), or stay Product Movement-only as shipped?
4. Decide: should manually-added Delivery products (Phase 3) also capture
   Brand and/or AC spec fields, or is Model + Quantity enough?

## For your next session

Re-upload the current `coolstock.zip` and this file
(`coolstock_project_status.md` — already inside the zip too) once you've
imported `coolstock_full_setup.sql` and tested. Also re-share your
working notes memory file if you're using a fresh chat.


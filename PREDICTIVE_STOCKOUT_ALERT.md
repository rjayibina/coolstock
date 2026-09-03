# Predictive Stock Alert — Research Notes (stockout-history approach)

Research deliverable only — **no code changes in this pass**. This documents a
formula/methodology for a future "predicted stockout" alert, deliberately
built on *historical stockout events* instead of average daily sales, per
the request. Written against CoolStock's actual schema so it can be
implemented directly when picked up.

## Why not average daily sales

The textbook reorder-point formula is:

```
Reorder Point = (Average Daily Demand × Lead Time) + Safety Stock
```

This needs a *demand rate* — units sold/used per day — which doesn't fit
CoolStock well:

- Item Request/Borrow/Return don't move stock at all (post strict-ERD
  Phase 2 / per-location Stock In/Out redesign), so "demand" isn't a clean
  quantity-out signal the way retail sales are.
- Several products are Consumables used in irregular bursts (a refrigerant
  cylinder used on a callout, not steadily "sold" day by day) — an average
  over the full history flattens exactly the pattern that matters.
- A brand-new product or one with sparse movement has too little data for
  a daily-rate average to mean anything, but it can still usefully answer
  "how soon might this run out again" once it has even 2 stockouts.

Using **stockout history** instead asks a narrower, more directly useful
question: *given how often this item has actually hit zero at this
location before, when is it likely to hit zero again* — no demand-rate
modeling required, and it directly reuses data already in `transactions`
and `item_stock`.

## Core idea: Mean Time Between Stockouts (MTBS)

Adapted from reliability engineering's Mean Time Between Failures, applied
to "zero quantity" as the failure event, per (item, location) pair:

1. **Find stockout events.** Replay `transactions` rows for one
   (item_id, location_id) pair in date order, applying each row's signed
   quantity delta to a running balance (`+` for stock_in/delivery-in,
   `-` for stock_out/transfer-out, mirroring what `ItemStock::adjust()`
   already does incrementally). Record a stockout event each time the
   running balance transitions from > 0 to 0.
2. **Compute MTBS.** For a pair with stockout events on dates
   `d1 < d2 < ... < dn` (n ≥ 2):
   ```
   MTBS = (dn - d1) / (n - 1)          -- average days between consecutive stockouts
   StdDev = stdev of the (n-1) individual gaps  -- spread/confidence signal
   ```
3. **Predict the next stockout.**
   ```
   PredictedNextStockout = dn + MTBS
   ```
   Show a range too, not just a point estimate — e.g.
   `dn + (MTBS - StdDev)` to `dn + (MTBS + StdDev)` — since a single
   number reads as false precision with only a handful of data points.
4. **Confidence tiers**, since n will often be small on a thesis-scale
   dataset:
   - `n = 0`: never stocked out — no prediction, nothing to alert on.
   - `n = 1`: one stockout only — no MTBS yet. Optionally show "last
     stocked out on `d1`" as context, but don't project a date from a
     single point.
   - `n ≥ 2`: show the predicted range, labeled "Low confidence" for
     `n = 2–3` and "Medium/High confidence" as `n` grows (e.g. ≥5).

## Alert trigger

Two independent signals, both derivable the same way:

- **Actual stockout (not predictive):** `item_stock.quantity = 0` right
  now for that (item, location) — this is already knowable today with a
  simple query, no history needed. This is the "it's already out" case.
- **Predicted stockout (the new part):** today's date is within a
  configurable window (e.g. 7 days) of `PredictedNextStockout`, and the
  item currently has `quantity > 0` — this is the "it's about to happen
  again" case, which is what makes it worth calling *predictive*.

## Secondary signal: stockout frequency (for a "chronically understocked" view)

Independent of predicting a date, a simple frequency figure is useful for
ranking/triage — adapted from the standard stockout-frequency metric
(incidents ÷ opportunities) to fit data CoolStock actually has:

```
StockoutFrequency(item, location, window_days) =
    (count of stockout events in the last `window_days`) / (window_days / 30)
```

i.e. "stockouts per 30 days" over a trailing window (e.g. 90 or 180 days).
Useful as a sortable Dashboard column ("Most frequently out of stock")
independent of the date-prediction above, and meaningful even for an item
whose gaps are too irregular for MTBS to be a good single estimate.

## Where this would plug into the current codebase

No schema changes needed to *compute* this — it's fully derivable from
existing `transactions` + `item_stock` rows. Implementation sketch for
when this is picked up:

- A method on `ItemStock` (or a new lightweight model), e.g.
  `predictedStockouts(): array`, that runs the running-balance replay per
  (item_id, location_id) — either as a live query on each Dashboard load
  (fine at this dataset size) or a small cached/materialized table if it
  ever needs to scale.
- Surfaced on the Dashboard as a new widget/section (e.g. "Predicted
  Stockouts" list, alongside the existing stat cards), separate from
  Product Movement's own list — this is a derived insight, not a new
  transaction type.
- The existing `.alert-warning` / badge styling already used for Low
  Stock-style callouts elsewhere in the UI would extend naturally to this.

## Worked example

Item X at Warehouse: stockouts recorded on 2026-06-01, 2026-07-03,
2026-08-02 (n = 3 → gaps of 32 and 30 days).

```
MTBS = (Aug 2 - Jun 1) / 2 = 62 / 2 = 31 days
StdDev(gaps [32, 30]) ≈ 1.4 days
PredictedNextStockout ≈ Sep 2 (range: Aug 31 - Sep 4)
```

If today is Aug 27 and the item still has stock on hand, this is a
"Predicted stockout in ~6 days (medium confidence, n=3)" alert — a week
of lead time to reorder, derived purely from how this specific item has
actually behaved, not a generic daily-sales average.

## Sources consulted

- [Reorder Point Formula: How to Calculate It with Examples](https://www.prediko.io/blog/how-to-calculate-reorder-point)
- [How to Reduce the Probability of a Stock-Out — Netstock](https://www.netstock.com/blog/how-to-reduce-the-probability-of-a-stockout/)
- [Stock-out Frequency: Formula, Benchmarks & Prevention — Count](https://count.co/metric/stock-out-frequency)
- [How to Prevent Stockouts with Reorder Point & Forecasting — BoxHero](https://www.boxhero.io/en/blog/how-to-prevent-stockouts-with-reorder-point-tracking-and-forecasting)
- [Calculate Days Of Supply — ParcelPath](https://parcelpath.com/calculate-days-of-supply/)
- [Inventory Days of Supply — Profit.co](https://www.profit.co/blog/kpis-library/supply-chain/inventory-days-of-supply/)

## Open questions for next pass (not decided here)

- Per-location prediction (as modeled above) vs. summed across all
  locations for one item — per-location is more actionable (tells you
  *where* to reorder to) but splits already-sparse history further.
  Recommend per-location, since Delivery/Transfer already work at that
  granularity.
- Alert window size (7 days used above is just illustrative) and the
  n≥2/n≥5 confidence-tier cutoffs — arbitrary starting points, worth
  tuning once there's real data volume to look at.
- Whether "Transfer" reducing stock at the FROM location should count
  toward that location's stockout history the same as a Stock Out/Delivery
  Out would — recommend yes, since the running-balance replay treats every
  negative movement identically regardless of type.

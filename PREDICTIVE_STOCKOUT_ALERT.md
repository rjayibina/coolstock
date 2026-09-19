# Predictive Stock Alert — formula reference

Implemented in `ItemStock::predictedStockouts()`, surfaced on the Dashboard's
"Predicted Stockouts" table. This is the standard capstone-style formula —
"average daily sales" with **stock-outs substituted for sales** throughout,
since CoolStock logs stock-outs directly rather than a separate sales record.

## Flow

```
START
   |
Get Current Stock
   |
Get Stock Out History
   |
Calculate Average Daily Stock Outs
   |
Calculate Predicted Days Until Stockout
   |
Get Supplier Lead Time
   |
Is Predicted Stockout <= Lead Time?
   |
 YES -----------------> Generate Predictive Stock Alert
   |
 NO
   |
No Alert
   |
END
```

## Formula

Per product, summed across every location (the formula doesn't distinguish
locations — a product's current stock and stock-out history are both
totaled across `item_stock` rows first):

1. **Current Stock** — today's total quantity across every location.
2. **Stock Out History** — total units stocked out in the trailing
   `$lookbackDays` (default 30).
3. **Average Daily Stock Outs** = Stock Out History ÷ `$lookbackDays`.
4. **Predicted Days Until Stockout** = Current Stock ÷ Average Daily
   Stock Outs.
5. **Reorder Point** = (Average Daily Stock Outs × `$leadTimeDays`) +
   `$safetyStock` — the "more realistic" version of the check, used
   instead of a bare day-count comparison so a sudden demand spike isn't
   the difference between "fine" and "already too late."
6. **Alert fires** when Current Stock ≤ Reorder Point — equivalent to
   "Predicted Days Until Stockout ≤ Lead Time," just expressed as a
   single stock-level threshold instead of two separate figures.

A product with zero stock-out history in the window is skipped entirely —
there's no consumption rate to compute a prediction from — **unless**
current stock is already 0, which is always alertable regardless of
history (shown as "Out now" rather than a predicted count).

## Configuration

`$leadTimeDays` (default 7) and `$safetyStock` (default 3) are applied
uniformly to every product right now — this system doesn't have a
per-supplier lead time or per-product safety stock setting yet. That's a
reasonable capstone-scope simplification (matches the source formula's
own worked example), but a real deployment would want both configurable
per product/supplier rather than one global constant.

## Why this counts as *predictive*, not just a low-stock rule

If asked "what makes this predictive rather than just `if stock < 5, show
alert`" — the answer: the system uses historical stock-out data to
calculate the expected rate of product consumption and estimates how many
days remain before inventory is depleted, then compares that predicted
stockout period against the supplier's lead time to decide whether an
advance alert is warranted. That's a computation from real usage history,
not a fixed threshold picked in advance.

## Example (from the source formula)

| Product | Stock | Avg. Daily Stock-Outs | Predicted Stockout | Lead Time | Reorder Point | Status |
|---|---|---|---|---|---|---|
| 1.0 HP AC | 20 | 1.0 | 20 days | 7 days | 10 | Normal |
| 1.5 HP AC | 10 | 2.0 | 5 days | 7 days | 17 | Reorder |
| 2.0 HP AC | 8 | 0.5 | 16 days | 10 days | 8 | Normal (borderline) |
| 2.5 HP AC | 5 | 1.0 | 5 days | 7 days | 10 | Reorder |

## History

This replaces an earlier Mean-Time-Between-Stockouts (MTBS) implementation
that replayed the full transaction ledger per (item, location) pair to
detect repeated stockout *events* and their timing. That approach is no
longer in use — this document previously described it, but the code has
been replaced with the simpler average-daily-rate formula above.

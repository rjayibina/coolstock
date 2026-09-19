    </main>
</div>

<script>
/**
 * Views/partials/footer.php
 * Shell behaviour shared by every signed-in page: the mobile navigation
 * drawer, and the captions that let wide tables re-flow into cards on a
 * phone. Both are no-ops on desktop.
 */
(function () {
    'use strict';

    /* ---------- Mobile navigation drawer ---------- */
    var rail = document.getElementById('railNav');
    var scrim = document.getElementById('railScrim');
    var toggle = document.getElementById('railToggle');
    var closeBtn = document.getElementById('railClose');

    function openRail() {
        if (!rail) { return; }
        rail.classList.add('open');
        if (scrim) { scrim.classList.add('show'); }
        document.body.classList.add('rail-open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
        if (closeBtn) { closeBtn.focus(); }
    }

    function closeRail() {
        if (!rail) { return; }
        rail.classList.remove('open');
        if (scrim) { scrim.classList.remove('show'); }
        document.body.classList.remove('rail-open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (rail && rail.classList.contains('open')) { closeRail(); } else { openRail(); }
        });
    }
    if (closeBtn) { closeBtn.addEventListener('click', closeRail); }
    if (scrim) { scrim.addEventListener('click', closeRail); }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && rail && rail.classList.contains('open')) {
            closeRail();
            if (toggle) { toggle.focus(); }
        }
    });

    // Following a link inside the drawer should dismiss it, otherwise the
    // next page renders with the drawer still latched open.
    if (rail) {
        rail.addEventListener('click', function (e) {
            if (e.target.closest('a')) { closeRail(); }
        });
    }

    // Dragging the window back up to desktop must not leave the body
    // scroll-locked by a drawer that is no longer visible.
    var wide = window.matchMedia('(min-width: 901px)');
    var onWide = function (e) { if (e.matches) { closeRail(); } };
    if (wide.addEventListener) {
        wide.addEventListener('change', onWide);
    } else if (wide.addListener) {
        wide.addListener(onWide); // Safari < 14
    }

    /* ---------- Table captions for the stacked phone layout ----------
     * Below 640px each row becomes a card and the <thead> is hidden, so
     * every cell needs to say which column it came from. Rather than
     * hardcoding that in all 17 views, copy each table's own header text
     * onto its cells as data-label and let the stylesheet decide when to
     * show it. Purely additive - no nodes are moved or rewritten, so
     * inline handlers and existing listeners are untouched.
     */
    document.querySelectorAll('.table-card > table').forEach(function (table) {
        var headers = Array.prototype.map.call(
            table.querySelectorAll('thead th'),
            function (th) { return th.textContent.trim(); }
        );
        if (!headers.length) { return; }

        table.querySelectorAll('tbody tr').forEach(function (row) {
            var cells = row.children;
            // The empty-state row spans every column - it has no single
            // header to name, and reads fine as-is.
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) { return; }

            for (var i = 0; i < cells.length; i++) {
                var cell = cells[i];
                if (cell.hasAttribute('data-label')) { continue; }

                var label = headers[i] || '';
                if (!label && cell.querySelector('input[type="checkbox"]')) {
                    label = 'Select';
                }
                if (label) { cell.setAttribute('data-label', label); }
            }
        });
    });
}());
</script>
</body>
</html>

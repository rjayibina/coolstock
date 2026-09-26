    </main>
</div>

<script>
/**
 * Views/partials/footer.php
 * Shell behaviour shared by every signed-in page: the mobile navigation
 * drawer, flash messages promoted to dismissible toasts, form validation
 * states, modal keyboard accessibility, and the captions that let wide
 * tables re-flow into cards on a phone. All are no-ops where they don't
 * apply.
 */
(function () {
    'use strict';

    /* ---------- Modal accessibility: focus trap + focus return ----------
     * Every view opens/closes its own modals by toggling the .open class
     * (via its own openXModal()/closeModal() functions - there are over
     * a dozen, one set per page). Rather than editing each one, this
     * watches every .modal-overlay for that class change and adds the
     * three things none of them had: focus moves into the dialog when it
     * opens, Tab is trapped inside it while open (a mouse user can click
     * away, but a keyboard user could otherwise tab straight into the
     * page behind it), and focus returns to whatever opened it on close -
     * including on Escape, which is now handled here for every modal,
     * not just the few pages that had their own listener for it. */
    function focusableIn(container) {
        return Array.prototype.filter.call(
            container.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ),
            function (el) { return el.offsetParent !== null; } // skip hidden branches (e.g. a collapsed section)
        );
    }

    document.querySelectorAll('.modal-overlay').forEach(function (modal) {
        var wasOpen = modal.classList.contains('open');
        var trigger = null;

        new MutationObserver(function () {
            var isOpen = modal.classList.contains('open');
            if (isOpen && !wasOpen) {
                trigger = document.activeElement;
                var focusable = focusableIn(modal);
                (focusable[0] || modal).focus();
            } else if (!isOpen && wasOpen) {
                if (trigger && document.body.contains(trigger)) { trigger.focus(); }
                trigger = null;
            }
            wasOpen = isOpen;
        }).observe(modal, { attributes: true, attributeFilter: ['class'] });

        modal.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                modal.classList.remove('open');
                return;
            }
            if (e.key !== 'Tab') { return; }

            var focusable = focusableIn(modal);
            if (!focusable.length) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    });

    /* ---------- Flash messages as toasts ----------
     * A page-level .alert (the "created/updated/failed" banner a
     * redirect renders) is moved into a fixed stack and given a close
     * button; a success message also clears itself after a few seconds.
     * An .alert used inside a modal - a contextual, in-place message
     * like the empty Item Request search results - is left exactly
     * where its view put it: only alerts outside .modal-overlay move. */
    var pageAlerts = Array.prototype.filter.call(
        document.querySelectorAll('.alert'),
        function (el) { return !el.closest('.modal-overlay'); }
    );

    if (pageAlerts.length) {
        var stack = document.createElement('div');
        stack.className = 'toast-stack';
        document.body.appendChild(stack);

        pageAlerts.forEach(function (alert) {
            stack.appendChild(alert); // moves the existing node, not a copy

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'toast-close';
            closeBtn.setAttribute('aria-label', 'Dismiss message');
            closeBtn.innerHTML = '&times;';
            alert.appendChild(closeBtn);

            var dismissed = false;
            function dismiss() {
                if (dismissed) { return; }
                dismissed = true;
                alert.classList.add('toast-leaving');
                alert.addEventListener('animationend', function () { alert.remove(); }, { once: true });
            }
            closeBtn.addEventListener('click', dismiss);

            // Only a success toast clears itself - a warning or error may
            // name something the visitor still needs to act on or note.
            if (alert.classList.contains('alert-success')) {
                setTimeout(dismiss, 5000);
            }
        });
    }

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
    /* ---------- Form validation states + submit loading state ----------
     * Progressive enhancement over the server's own validation (the
     * page-level .alert-error banner stays the source of truth): native
     * HTML5 constraint validation (required, type, min/max, pattern)
     * drives an inline .field-error message and an .is-invalid border
     * instead of the browser's own tooltip, which looks and behaves
     * differently per browser. A submit that passes validation disables
     * its button and shows a spinner via .is-loading, so a slow request
     * (or an impatient double-click) doesn't look like a dead click.
     * novalidate is only added here, at runtime, so a page still submits
     * normally if this script fails to load. */
    var FRIENDLY_MESSAGE = {
        valueMissing: 'This field is required.',
        typeMismatch: 'Enter a valid value.',
        rangeUnderflow: function (f) { return 'Value must be at least ' + f.min + '.'; },
        rangeOverflow: function (f) { return 'Value must be at most ' + f.max + '.'; },
        tooShort: function (f) { return 'Must be at least ' + f.minLength + ' characters.'; },
        tooLong: function (f) { return 'Must be at most ' + f.maxLength + ' characters.'; },
        patternMismatch: function (f) { return f.title || 'Enter a value in the expected format.'; },
    };

    function messageFor(field) {
        var v = field.validity;
        for (var key in FRIENDLY_MESSAGE) {
            if (v[key]) {
                var m = FRIENDLY_MESSAGE[key];
                return typeof m === 'function' ? m(field) : m;
            }
        }
        return field.validationMessage || 'Check this field.';
    }

    function isValidatable(field) {
        var tag = field.tagName;
        if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') { return false; }
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button' || field.disabled) { return false; }
        return true;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var next = field.nextElementSibling;
        if (next && next.classList.contains('field-error')) { next.remove(); }
    }

    function showFieldError(field) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        var next = field.nextElementSibling;
        if (!next || !next.classList.contains('field-error')) {
            next = document.createElement('span');
            next.className = 'field-error';
            field.insertAdjacentElement('afterend', next);
        }
        next.textContent = messageFor(field);
    }

    function validateField(field) {
        if (!isValidatable(field)) { return true; }
        if (field.checkValidity()) {
            clearFieldError(field);
            return true;
        }
        showFieldError(field);
        return false;
    }

    // Extracted so it can be re-run (from ajaxPaginate below) against just
    // the fragment that was swapped in, not the whole document again.
    function applyTableCaptions(root) {
        root.querySelectorAll('.table-card > table').forEach(function (table) {
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
    }
    applyTableCaptions(document);

    // Same reasoning as applyTableCaptions() - wires up the same
    // novalidate/inline-error behaviour on any <form> inside a root, so a
    // form swapped in later (an AJAX-paginated list's bulk form) behaves
    // the same as one that was on the page at load time.
    function wireFormValidation(root) {
        root.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.validationWired === '1') { return; }
            form.dataset.validationWired = '1';
            form.setAttribute('novalidate', 'novalidate');

            form.addEventListener('blur', function (e) {
                if (isValidatable(e.target)) { validateField(e.target); }
            }, true);
            form.addEventListener('input', function (e) {
                if (isValidatable(e.target) && e.target.classList.contains('is-invalid')) {
                    validateField(e.target);
                }
            });
            form.addEventListener('change', function (e) {
                if (isValidatable(e.target) && e.target.classList.contains('is-invalid')) {
                    validateField(e.target);
                }
            });

            form.addEventListener('submit', function (e) {
                var fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
                var firstInvalid = null;
                fields.forEach(function (field) {
                    if (!validateField(field) && !firstInvalid) { firstInvalid = field; }
                });

                if (firstInvalid) {
                    e.preventDefault();
                    firstInvalid.scrollIntoView({ block: 'center' });
                    firstInvalid.focus();
                    return;
                }

                var submitBtn = e.submitter || form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.tagName === 'BUTTON') {
                    submitBtn.classList.add('is-loading');
                    submitBtn.disabled = true;
                }
            });
        });
    }
    wireFormValidation(document);

    /* ---------- AJAX pagination ----------
     * A listing view (Products, Product Movement, Item Requests, Users)
     * wraps its table + pagination bar in a container marked
     * data-ajax-list, with data-ajax-var naming the page's client-side
     * id-keyed lookup object (e.g. productsData) if it has one. The
     * matching controller's index() detects the X-Requested-With header
     * (is_ajax_request(), Helpers/auth.php) and returns
     * {html, data} JSON instead of a full page - html replaces the
     * container's contents, and data (if any) is merged into that lookup
     * object so row click handlers keep working for the newly-swapped rows. */
    function ajaxPaginate(container, url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                container.innerHTML = json.html;
                var varName = container.dataset.ajaxVar;
                if (varName && json.data) {
                    window[varName] = Object.assign(window[varName] || {}, json.data);
                }
                applyTableCaptions(container);
                wireFormValidation(container);
                container.scrollIntoView({ block: 'nearest' });
            })
            .catch(function () {
                // Best-effort progressive enhancement - if the fetch fails
                // for any reason, fall back to a normal full-page navigation.
                window.location.href = url;
            });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a.page-btn');
        if (!link || link.classList.contains('disabled') || link.classList.contains('active')) { return; }

        var container = link.closest('[data-ajax-list]');
        if (!container) { return; } // not an AJAX-enabled list - let it navigate normally

        e.preventDefault();
        ajaxPaginate(container, link.getAttribute('href'));
    });
}());
</script>
</body>
</html>

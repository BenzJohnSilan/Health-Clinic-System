/**
 * Prevents double-submitting forms (which causes 419 Page Expired after
 * Laravel regenerates the session on login/register).
 *
 * Usage: add `data-prevent-double-submit` to the <form>, and optionally
 * `data-loading-text="Logging in..."` to the submit <button>.
 */
(function () {
    'use strict';

    function lock(form) {
        form.dataset.submitting = 'true';

        form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
            if (!btn.dataset.originalHtml) {
                btn.dataset.originalHtml = btn.innerHTML;
            }
            var text = btn.dataset.loadingText || 'Please wait...';
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> " + text;
            btn.disabled = true;
            btn.style.cursor = 'not-allowed';
            btn.style.opacity = '0.8';
        });
    }

    function unlock(form) {
        delete form.dataset.submitting;

        form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
            }
            btn.disabled = false;
            btn.style.cursor = '';
            btn.style.opacity = '';
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-prevent-double-submit')) {
            return;
        }

        // Another script (e.g. the register wizard) already blocked this
        // submit because something is missing -> don't lock the button.
        if (e.defaultPrevented) {
            return;
        }

        // Already submitting -> block the 2nd submit (double click / Enter spam)
        if (form.dataset.submitting === 'true') {
            e.preventDefault();
            return;
        }

        // Lock after the browser has started the submission
        setTimeout(function () { lock(form); }, 0);
    });

    // If the user presses the Back button, the page may be restored from the
    // back/forward cache with the button still disabled. Reset it.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('form[data-prevent-double-submit]').forEach(unlock);
        }
    });
})();
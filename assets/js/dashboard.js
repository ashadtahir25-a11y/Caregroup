/* CARE Group - dashboard helpers
   Glass confirm dialog for risky actions, and auto-submitting date pickers. */
(function () {
    'use strict';

    /* ---------- Confirm dialog (replaces the browser's plain confirm box) ---------- */
    var dialog = document.createElement('div');
    dialog.className = 'modal';
    dialog.hidden = true;
    dialog.innerHTML =
        '<div class="modal__card glass" role="alertdialog" aria-modal="true" aria-labelledby="modal-text">' +
        '  <i class="fa-solid fa-triangle-exclamation modal__icon" aria-hidden="true"></i>' +
        '  <p id="modal-text"></p>' +
        '  <div class="modal__actions">' +
        '    <button type="button" class="btn btn--ghost btn--sm" data-modal-no>Keep it</button>' +
        '    <button type="button" class="btn btn--danger btn--sm" data-modal-yes>Yes, continue</button>' +
        '  </div>' +
        '</div>';
    document.body.appendChild(dialog);

    var pendingForm = null, lastFocus = null;
    var close = function () {
        dialog.classList.remove('is-open');
        window.setTimeout(function () { dialog.hidden = true; }, 200);
        pendingForm = null;
        if (lastFocus) lastFocus.focus();
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
        e.preventDefault();
        pendingForm = form;
        lastFocus = document.activeElement;
        dialog.querySelector('#modal-text').textContent = form.getAttribute('data-confirm');
        dialog.hidden = false;
        window.requestAnimationFrame(function () { dialog.classList.add('is-open'); });
        dialog.querySelector('[data-modal-no]').focus();
    });

    dialog.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-yes]') && pendingForm) {
            var f = pendingForm;
            f.dataset.confirmed = '1';
            close();
            f.submit();
        } else if (e.target.closest('[data-modal-no]') || e.target === dialog) {
            close();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !dialog.hidden) close();
    });

    /* ---------- Date inputs that load results as soon as a date is picked ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.value && input.form) input.form.submit();
        });
    });
})();
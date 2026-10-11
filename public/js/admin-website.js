/* Admin → Website Management: tabs, item modal, previews, confirmations. */
(function () {
    'use strict';

    var root = document.querySelector('.ws-container');
    if (!root) return;

    /* ---------- Tabs ---------- */
    var tabs = root.querySelectorAll('.ws-tab');
    var panels = root.querySelectorAll('.ws-panel');

    function showTab(key) {
        tabs.forEach(function (t) {
            var on = t.dataset.tab === key;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p) { p.classList.toggle('active', p.dataset.panel === key); });
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', key);
            window.history.replaceState(null, '', url);
        } catch (e) { /* history not available – harmless */ }
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { showTab(t.dataset.tab); }); });

    /* ---------- Saving feedback + double-submit guard ---------- */
    document.querySelectorAll('form[data-ws-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.dataset.label = btn.innerHTML;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Saving…";
                setTimeout(function () { btn.disabled = true; }, 0);
            }
        });
    });

    /* ---------- Confirm destructive actions ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    /* ---------- Image previews ---------- */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var box = document.querySelector(input.dataset.preview);
            var file = input.files && input.files[0];
            if (!box) return;
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                window.alert('That image is larger than 2 MB. Please choose a smaller one.');
                input.value = '';
                return;
            }
            var img = box.querySelector('img');
            img.src = URL.createObjectURL(file);
            box.hidden = false;
        });
    });

    /* ---------- Add / edit item modal ---------- */
    var modal = document.getElementById('wsItemModal');
    var form = document.getElementById('wsItemForm');
    var method = document.getElementById('wsItemMethod');
    var field = function (id) { return document.getElementById(id); };
    var lastFocus = null;

    function toggleOnly(name, show) {
        form.querySelectorAll('[data-only="' + name + '"]').forEach(function (el) {
            el.hidden = !show;
            el.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !show; });
        });
    }

    function updateIconPreview() {
        var v = field('wsItemIcon').value.trim();
        field('wsIconPreview').className = /^bx[sl]?-[a-z0-9-]+$/.test(v) ? 'bx ' + v : 'bx bx-help-circle';
    }
    field('wsItemIcon').addEventListener('input', updateIconPreview);

    function openModal(btn, item) {
        var d = btn.dataset, editing = !!item;
        item = item || {};
        lastFocus = btn;

        form.reset();
        form.action = editing ? d.update : d.store;
        method.disabled = !editing;
        method.value = 'PUT';
        field('wsItemSection').value = d.section;
        field('wsModalTitle').textContent = (editing ? 'Edit ' : 'Add ') + d.noun;

        var isFaq = d.isFaq === '1';
        field('wsItemTitleLabel').innerHTML = (isFaq ? 'Question' : 'Title') + ' <span class="ws-req">*</span>';
        field('wsItemBodyLabel').innerHTML = (isFaq ? 'Answer' : 'Description') + (d.hasBody === '1' ? ' <span class="ws-req">*</span>' : '');
        toggleOnly('faq', isFaq);
        toggleOnly('body', d.hasBody === '1');
        toggleOnly('icon', d.hasIcon === '1');
        toggleOnly('image', d.hasImage === '1');
        field('wsItemBody').required = d.hasBody === '1';
        field('wsItemIcon').required = d.hasIcon === '1';

        field('wsItemTitle').value = item.title || '';
        field('wsItemBody').value = item.body || '';
        field('wsItemIcon').value = item.icon || '';
        if (isFaq && item.category) field('wsItemCategory').value = item.category;
        field('wsItemActive').checked = editing ? !!item.active : true;
        updateIconPreview();

        var prev = field('wsItemImagePreview');
        prev.hidden = !item.image;
        if (item.image) prev.querySelector('img').src = item.image;
        field('wsRemoveImageWrap').hidden = !item.image;

        var save = form.querySelector('button[type="submit"]');
        save.disabled = false;
        save.innerHTML = "<i class='bx bx-save'></i> Save";

        modal.hidden = false;
        field('wsItemTitle').focus();
    }

    function closeModal() {
        modal.hidden = true;
        if (lastFocus) lastFocus.focus();
    }

    root.querySelectorAll('.js-ws-add').forEach(function (b) {
        b.addEventListener('click', function () { openModal(b, null); });
    });
    root.querySelectorAll('.js-ws-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            var item = {};
            try { item = JSON.parse(b.dataset.item); } catch (e) { /* fall through with empty item */ }
            openModal(b, item);
        });
    });
    modal.querySelectorAll('.js-ws-close').forEach(function (b) { b.addEventListener('click', closeModal); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
})();

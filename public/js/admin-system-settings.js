/* Admin → System Settings: UI helpers only. Every rule is enforced again on the server. */
(function () {
    'use strict';

    var form = document.getElementById('systemSettingsForm');
    if (!form) return;

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ================= Section navigation ================= */
    var navLinks = $$('.ss-nav a');
    var sections = navLinks.map(function (a) { return document.getElementById(a.dataset.section); }).filter(Boolean);

    function setActive(id) {
        navLinks.forEach(function (a) { a.classList.toggle('active', a.dataset.section === id); });
    }

    navLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            var target = document.getElementById(a.dataset.section);
            if (!target) return;
            e.preventDefault();
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            setActive(a.dataset.section);
            try { history.replaceState(null, '', '#' + a.dataset.section); } catch (err) { /* harmless */ }
        });
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) setActive(entry.target.id);
            });
        }, { rootMargin: '-25% 0px -65% 0px' });
        sections.forEach(function (s) { observer.observe(s); });
    }

    /* ---- mark sections that contain server-side errors ---- */
    function flagErrorSections() {
        var first = null;
        navLinks.forEach(function (a) {
            var section = document.getElementById(a.dataset.section);
            var bad = section && section.querySelector('.is-invalid, .ss-error:not([hidden])');
            a.classList.toggle('has-error', !!bad);
            if (bad && !first) first = section.querySelector('.is-invalid') || section;
        });
        return first;
    }
    var firstError = flagErrorSections();
    if (firstError && form.dataset.hasErrors === '1') {
        setTimeout(function () {
            firstError.scrollIntoView({ block: 'center' });
            if (firstError.focus) firstError.focus({ preventScroll: true });
        }, 150);
    }

    /* ================= Logo uploader ================= */
    var zone = $('#logoZone');
    var logoInput = $('#clinic_logo');
    var preview = $('#logoPreview');
    var placeholder = $('#logoPlaceholder');
    var logoName = $('#logoName');
    var logoBtnText = $('#logoBtnText');
    var logoError = $('#logoClientError');
    var removeBox = $('#removeLogo');
    var savedLogoSrc = preview ? preview.getAttribute('src') : '';
    var hadLogo = zone && zone.dataset.hasLogo === '1';
    var MAX_BYTES = 2 * 1024 * 1024;
    var TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    function showLogoError(msg) {
        if (!logoError) return;
        logoError.textContent = msg || '';
        logoError.hidden = !msg;
    }

    function showPreview(src) {
        if (!preview || !placeholder) return;
        if (src) { preview.src = src; preview.hidden = false; placeholder.hidden = true; }
        else { preview.removeAttribute('src'); preview.hidden = true; placeholder.hidden = false; }
    }

    function humanSize(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function resetLogoUI() {
        showPreview(hadLogo ? savedLogoSrc : '');
        if (logoName) logoName.textContent = hadLogo ? 'Current logo' : 'No logo uploaded';
        if (logoBtnText) logoBtnText.textContent = hadLogo ? 'Replace logo' : 'Upload logo';
        if (zone) zone.classList.remove('is-removing');
    }

    function handleLogoFile(file) {
        showLogoError('');
        if (!file) { resetLogoUI(); return; }
        if (TYPES.indexOf(file.type) === -1) {
            logoInput.value = '';
            showLogoError('The logo must be a JPG, PNG or WEBP image.');
            resetLogoUI();
            updateDirty();
            return;
        }
        if (file.size > MAX_BYTES) {
            logoInput.value = '';
            showLogoError('The logo may not be larger than 2 MB (yours is ' + humanSize(file.size) + ').');
            resetLogoUI();
            updateDirty();
            return;
        }
        if (removeBox) removeBox.checked = false;
        if (zone) zone.classList.remove('is-removing');
        showPreview(URL.createObjectURL(file));
        if (logoName) logoName.textContent = file.name + ' · ' + humanSize(file.size);
        if (logoBtnText) logoBtnText.textContent = 'Choose another';
        updateDirty();
    }

    if (logoInput) {
        logoInput.addEventListener('change', function () {
            handleLogoFile(logoInput.files && logoInput.files[0]);
        });
    }

    if (zone && logoInput) {
        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-drag'); });
        });
        zone.addEventListener('drop', function (e) {
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (!file) return;
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                logoInput.files = dt.files;
            } catch (err) { /* very old browser: fall back to the button */ return; }
            handleLogoFile(file);
        });
    }

    if (removeBox) {
        removeBox.addEventListener('change', function () {
            if (removeBox.checked) {
                if (logoInput) logoInput.value = '';
                showLogoError('');
                if (zone) zone.classList.add('is-removing');
                if (logoName) logoName.textContent = 'Logo will be removed when you save';
            } else {
                resetLogoUI();
            }
        });
        if (removeBox.checked) removeBox.dispatchEvent(new Event('change'));
    }

    /* ================= Lunch break switch ================= */
    var lunch = $('#lunch_enabled');
    var lunchFields = $('#lunchFields');
    function syncLunch() {
        if (!lunch || !lunchFields) return;
        lunchFields.classList.toggle('is-off', !lunch.checked);
        $$('input', lunchFields).forEach(function (i) { i.disabled = !lunch.checked; });
    }

    /* ================= Notification channel switches ================= */
    function syncChannel(masterId, channel) {
        var master = document.getElementById(masterId);
        if (!master) return;
        $$('[data-channel-cell="' + channel + '"]').forEach(function (td) {
            td.classList.toggle('is-muted', !master.checked);
        });
    }
    function syncChannels() {
        syncChannel('master_email', 'email');
        syncChannel('master_sms', 'sms');
    }

    /* ================= Booking-rule hints ================= */
    function syncRuleHints() {
        var h = parseInt(($('#booking_min_notice_hours') || {}).value, 10) || 0;
        var d = parseInt(($('#booking_max_advance_days') || {}).value, 10) || 0;
        var nh = $('#noticeHelp'), wh = $('#windowHelp');
        if (nh) nh.textContent = h > 0
            ? 'Patients must book at least ' + h + ' hour' + (h === 1 ? '' : 's') + ' ahead.'
            : '0 = patients may book any future slot.';
        if (wh) wh.textContent = d > 0
            ? 'Patients can book up to ' + d + ' day' + (d === 1 ? '' : 's') + ' ahead (staff and admins are not limited).'
            : '0 = no limit. Applies to patient self-booking only.';
    }

    /* ================= Live time-slot preview (mirrors ClinicSchedule::buildSlots) ================= */
    var slotList = $('#slotList');
    var slotCount = $('#slotCount');
    var slotWarn = $('#slotWarn');
    var scheduleProblem = '';

    function toMin(hhmm) {
        var m = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(hhmm || '');
        return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
    }
    function label(min) {
        var h = Math.floor(min / 60), m = min % 60, ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
    }

    function renderSlots() {
        if (!slotList) return;
        var checked = $('input[name="slot_minutes"]:checked');
        var slot = checked ? parseInt(checked.value, 10) : 30;
        var open = toMin(($('#opening_time') || {}).value);
        var close = toMin(($('#closing_time') || {}).value);
        var lunchOn = lunch && lunch.checked;
        var ls = toMin(($('#lunch_start') || {}).value);
        var le = toMin(($('#lunch_end') || {}).value);
        var problem = '';

        if (open === null || close === null) problem = 'Enter both an opening and a closing time.';
        else if (close <= open) problem = 'Closing time must be later than opening time.';
        else if (close - open < slot) problem = 'Operating hours must fit at least one appointment.';
        else if (lunchOn) {
            if (ls === null || le === null) problem = 'Enter when lunch starts and ends, or turn the lunch break off.';
            else if (le <= ls) problem = 'Lunch break must end after it starts.';
            else if (ls < open || le > close) problem = 'The lunch break must be inside the operating hours.';
        }

        scheduleProblem = problem;
        if (slotWarn) { slotWarn.textContent = problem; slotWarn.hidden = !problem; }

        if (problem) {
            slotList.innerHTML = '';
            if (slotCount) slotCount.textContent = '0 slots per day';
            return;
        }

        var html = '', count = 0, lunchAdded = false;
        for (var start = open; start + slot <= close; start += slot) {
            var end = start + slot;
            if (lunchOn && start < le && end > ls) {
                if (!lunchAdded) {
                    html += '<span class="ss-slot is-lunch">' + label(ls) + ' – ' + label(le) + ' (Lunch Break)</span>';
                    lunchAdded = true;
                }
                continue;
            }
            html += '<span class="ss-slot">' + label(start) + ' – ' + label(end) + '</span>';
            count++;
        }

        if (count === 0) {
            scheduleProblem = 'These hours leave no bookable appointment slots.';
            if (slotWarn) { slotWarn.textContent = scheduleProblem; slotWarn.hidden = false; }
        }
        slotList.innerHTML = html;
        if (slotCount) slotCount.textContent = count + ' slot' + (count === 1 ? '' : 's') + ' per day';
    }

    /* ================= Unsaved changes + save bar ================= */
    var saveBar = $('#saveBar');
    var saveBtn = $('#saveBtn');
    var discardBtn = $('#discardBtn');
    var statusText = $('#saveStatusText');
    var statusIcon = $('#saveStatus i');
    var submitting = false;

    function snapshot() {
        var parts = [];
        new FormData(form).forEach(function (value, key) {
            if (key === '_token' || key === '_method') return;
            parts.push(key + '=' + (value instanceof File ? 'file:' + value.name + ':' + value.size : value));
        });
        return parts.join('&');
    }
    var initial = snapshot();
    var serverErrors = form.dataset.hasErrors === '1';

    function updateDirty() {
        var dirty = serverErrors || snapshot() !== initial;
        if (saveBar) saveBar.classList.toggle('is-dirty', dirty);
        if (saveBtn) saveBtn.disabled = !dirty;
        if (discardBtn) discardBtn.disabled = !dirty;
        if (statusText) statusText.textContent = dirty ? 'You have unsaved changes' : 'All changes saved';
        if (statusIcon) statusIcon.className = 'bx ' + (dirty ? 'bx-error-circle' : 'bx-check-circle');
        return dirty;
    }

    function refreshAll() {
        syncLunch();
        syncChannels();
        syncRuleHints();
        renderSlots();
        updateDirty();
    }

    form.addEventListener('input', refreshAll);
    form.addEventListener('change', refreshAll);

    if (discardBtn) {
        discardBtn.addEventListener('click', function () {
            submitting = true; // skip the leave-page warning for this deliberate reload
            window.location.href = form.dataset.resetUrl;
        });
    }

    window.addEventListener('beforeunload', function (e) {
        if (!submitting && updateDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    form.addEventListener('submit', function (e) {
        if (scheduleProblem) {
            e.preventDefault();
            var target = document.getElementById('appointments');
            if (target) target.scrollIntoView({ block: 'start', behavior: 'smooth' });
            if (slotWarn) { slotWarn.hidden = false; slotWarn.textContent = scheduleProblem; }
            return;
        }
        submitting = true;
        if (saveBtn && !saveBtn.disabled) {
            saveBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Saving…";
            setTimeout(function () { saveBtn.disabled = true; }, 0);
        }
    });

    // Ctrl/Cmd + S saves when there is something to save.
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (saveBtn && !saveBtn.disabled) form.requestSubmit();
        }
    });

    refreshAll();
    // Re-sync the baseline after the first sync (it disables hidden lunch inputs, etc.).
    initial = snapshot();
    updateDirty();
})();
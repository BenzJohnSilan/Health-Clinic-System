/**
 * ClinicRMS — shared sidebar collapse/expand behavior.
 *
 * Used by all four role layouts (admin, doctor, staff, patient). Handles:
 *  - Toggling the collapsed (icon-only) state on desktop widths
 *  - Persisting that state in localStorage so it survives navigation/refresh
 *  - Mobile behavior: either a push-open menu ("shift") or, when the layout
 *    provides a #sidebarBackdrop element, a true overlay drawer
 *  - Accessible tooltips (title/aria-label) on nav icons while collapsed
 *  - Keeping the toggle button's aria-expanded state in sync
 *  - Remembering the sidebar's scroll position between page loads, so
 *    clicking a link near the bottom doesn't snap the menu back to the top
 *
 * The very-first-paint state (avoiding a flash of the expanded sidebar) is
 * applied by a small inline script in each layout, before this file loads —
 * see the comment near STORAGE_KEY below. This file takes over from there
 * for all interactive behavior.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'clinicrms_sidebar_collapsed';
    var DESKTOP_BREAKPOINT = 900;

    var sidebar     = document.getElementById('sidebar');
    var mainContent = document.getElementById('mainContent');
    var hamburger    = document.getElementById('hamburgerBtn');
    var backdrop     = document.getElementById('sidebarBackdrop');

    if (!sidebar || !mainContent || !hamburger) {
        return;
    }

    function isMobile() {
        return window.innerWidth <= DESKTOP_BREAKPOINT;
    }

    // Nav links (and, on the admin layout, the sidebar logout button) that
    // should get a hover/focus tooltip once their text label is hidden.
    function navItems() {
        return sidebar.querySelectorAll('.sidebar-nav-wrapper a, nav a, nav button.logout-link');
    }

    function updateTooltips(collapsed) {
        navItems().forEach(function (item) {
            var label = item.querySelector('span');
            var text  = label ? label.textContent.trim() : (item.getAttribute('data-label') || '');

            if (!text) return;

            if (collapsed) {
                item.setAttribute('title', text);
                item.setAttribute('aria-label', text);
            } else {
                item.removeAttribute('title');
                item.removeAttribute('aria-label');
            }
        });
    }

    function setCollapsed(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        mainContent.classList.toggle('collapsed', collapsed);
        updateTooltips(collapsed);
        syncAriaExpanded();

        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
        } catch (e) {
            // localStorage unavailable (private browsing, disabled storage, etc.)
            // — the toggle still works for this session, it just won't persist.
        }
    }

    function openMobileDrawer() {
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('show');
        document.body.classList.add('no-scroll');
        syncAriaExpanded();
    }

    function closeMobileDrawer() {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
        document.body.classList.remove('no-scroll');
        syncAriaExpanded();
    }

    function syncAriaExpanded() {
        var expanded = isMobile()
            ? sidebar.classList.contains('open')
            : !sidebar.classList.contains('collapsed');
        hamburger.setAttribute('aria-expanded', String(expanded));
    }

    // ----- Keep the sidebar's scroll position across page loads -----------
    // Every nav click is a full page load, which would otherwise reset the
    // scrollable menu to the top. The position is saved in sessionStorage
    // and put back right away (this script runs at the end of <body>, before
    // the first paint, so there is no visible jump).
    var SCROLL_KEY = 'clinicrms_sidebar_scroll';
    var navScroller = sidebar.querySelector('.sidebar-nav-wrapper');

    if (navScroller) {
        var savedScroll = null;
        try {
            savedScroll = sessionStorage.getItem(SCROLL_KEY);
        } catch (e) {}

        navScroller.style.scrollBehavior = 'auto';
        if (savedScroll !== null && !isNaN(parseInt(savedScroll, 10))) {
            navScroller.scrollTop = parseInt(savedScroll, 10);
        }

        // If the current page's link is still out of view (first visit,
        // direct link, or a different menu length), bring it into view.
        var activeLink = navScroller.querySelector('a.active');
        if (activeLink) {
            var navRect  = navScroller.getBoundingClientRect();
            var linkRect = activeLink.getBoundingClientRect();
            if (linkRect.top < navRect.top || linkRect.bottom > navRect.bottom) {
                navScroller.scrollTop += (linkRect.top - navRect.top)
                    - (navScroller.clientHeight / 2) + (linkRect.height / 2);
            }
        }

        var saveScroll = function () {
            try {
                sessionStorage.setItem(SCROLL_KEY, String(Math.round(navScroller.scrollTop)));
            } catch (e) {}
        };

        var scrollTicking = false;
        navScroller.addEventListener('scroll', function () {
            if (scrollTicking) return;
            scrollTicking = true;
            requestAnimationFrame(function () {
                saveScroll();
                scrollTicking = false;
            });
        }, { passive: true });

        // Also save right before leaving the page (link click, refresh, etc.)
        window.addEventListener('pagehide', saveScroll);
        navScroller.addEventListener('click', saveScroll);
    }

    // Sync tooltip/aria state with whatever the blocking init script in the
    // layout already applied before this file loaded.
    updateTooltips(sidebar.classList.contains('collapsed'));
    syncAriaExpanded();

    hamburger.addEventListener('click', function () {
        if (isMobile()) {
            if (backdrop) {
                if (sidebar.classList.contains('open')) {
                    closeMobileDrawer();
                } else {
                    openMobileDrawer();
                }
            } else {
                sidebar.classList.toggle('open');
                mainContent.classList.toggle('shift');
            }
        } else {
            setCollapsed(!sidebar.classList.contains('collapsed'));
        }
    });

    // Keyboard support for the toggle icon (it isn't a native <button>).
    hamburger.setAttribute('tabindex', hamburger.getAttribute('tabindex') || '0');
    hamburger.setAttribute('role', hamburger.getAttribute('role') || 'button');
    if (!hamburger.hasAttribute('aria-label')) {
        hamburger.setAttribute('aria-label', 'Toggle sidebar');
    }
    hamburger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
            e.preventDefault();
            hamburger.click();
        }
    });

    // Clicking a nav icon while collapsed must navigate normally — it must
    // NOT expand the sidebar. Anchor tags already behave this way by default
    // (no click handler intercepts navigation), so there is nothing to wire
    // up here beyond leaving normal <a href> behavior alone.

    if (backdrop) {
        // True overlay drawer (patient layout): tap backdrop to close,
        // picking a nav link closes the drawer, and resizing past the
        // breakpoint resets it so it doesn't get stuck open.
        backdrop.addEventListener('click', closeMobileDrawer);

        sidebar.querySelectorAll('.sidebar-nav-wrapper a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) closeMobileDrawer();
            });
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                closeMobileDrawer();
                return;
            }

            // Rotating/resizing from a collapsed desktop width: the icon-only
            // state must not leak into the mobile drawer (it would open as a
            // 68px strip with no labels). Visual only — the saved desktop
            // preference is untouched.
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('collapsed');
                updateTooltips(false);
            }
        });

        // Escape closes the open drawer (keyboard / external keyboard users).
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isMobile() && sidebar.classList.contains('open')) {
                closeMobileDrawer();
                hamburger.focus();
            }
        });
    }
})();
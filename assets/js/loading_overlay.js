'use strict';
// Global loading overlay controller for comissionamento.php (Edit mode)
(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    // Guards: only run on commission form page
    var isCommissionPage = (window.location.pathname || '').toLowerCase().indexOf('comissionamento.php') !== -1;
    if (!isCommissionPage) return;

    var overlay = null;
    var ready = {
        invertersListed: false,
        stringTables: false
    };
    var hideScheduled = false;

    function qs(id) { return document.getElementById(id); }
    function tryHideOverlay(reason) {
        if (!overlay) overlay = qs('global-loading-overlay');
        if (!overlay) return;

        // If both core readiness events happened, hide overlay
        if (ready.invertersListed && ready.stringTables) {
            if (hideScheduled) return;
            hideScheduled = true;
            // small debounce to absorb final DOM touches
            setTimeout(function () {
                overlay.classList.add('overlay-hidden');
                setTimeout(function () {
                    // remove entirely after fade
                    try { overlay.style.display = 'none'; } catch (_) { }
                }, 250);
            }, 150);
            if (window.console) console.log('[LoadingOverlay] Hidden (', reason || 'ready', ')');
        }
    }

    function forceHideAfter(ms) {
        setTimeout(function () {
            if (!overlay) overlay = qs('global-loading-overlay');
            if (!overlay) return;
            if (hideScheduled) return;
            hideScheduled = true;
            overlay.classList.add('overlay-hidden');
            setTimeout(function () { try { overlay.style.display = 'none'; } catch (_) { } }, 250);
            if (window.console) console.log('[LoadingOverlay] Hidden by fallback timeout');
        }, ms);
    }

    document.addEventListener('DOMContentLoaded', function () {
        overlay = qs('global-loading-overlay');

        // Only active in Edit Mode (PHP only renders overlay when reportId exists)
        if (!overlay) return;

        // If creating a new report, hide immediately just in case
        if (window.IS_NEW_REPORT) {
            overlay.classList.add('overlay-hidden');
            setTimeout(function () { try { overlay.style.display = 'none'; } catch (_) { } }, 200);
            return;
        }

        // Safety fallback: hide after 6 seconds no matter what
        forceHideAfter(6000);

        // When inverters list is published
        document.addEventListener('invertersListUpdated', function (e) {
            ready.invertersListed = true;
            tryHideOverlay('invertersListUpdated');
        }, { once: false });

        // When string tables finish rendering
        document.addEventListener('stringTablesReady', function (e) {
            ready.stringTables = true;
            tryHideOverlay('stringTablesReady');
        }, { once: false });

        // If both events were already fired before this controller binds (unlikely), do a quick check
        setTimeout(function () { tryHideOverlay('post-bind-check'); }, 400);
    });
})();

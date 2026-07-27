(function () {
    'use strict';

    const COOKIE_NAME = 'am_cookie_notice';
    const STORAGE_KEY = 'am_cookie_notice';
    const MAX_AGE_DAYS = 365;
    const AUTO_HIDE_MS = 30000;

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function markSeen() {
        const maxAge = MAX_AGE_DAYS * 24 * 60 * 60;
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE_NAME + '=1; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // ignore
        }
    }

    function hasSeen() {
        const banner = document.getElementById('cookieConsentBanner');
        if (banner && banner.dataset.seen === '1') {
            return true;
        }
        if (readCookie(COOKIE_NAME) === '1') {
            return true;
        }
        try {
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                return true;
            }
        } catch (e) {
            // ignore
        }
        return false;
    }

    function fulfillmentModalOpen() {
        const modal = document.getElementById('fulfillmentModal');
        return !!(modal && !modal.hidden);
    }

    let autoHideTimer = null;

    function showBanner() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner || hasSeen() || fulfillmentModalOpen()) {
            return;
        }
        banner.hidden = false;
        document.body.classList.add('has-cookie-consent-banner');
        if (autoHideTimer) {
            window.clearTimeout(autoHideTimer);
        }
        autoHideTimer = window.setTimeout(dismissBanner, AUTO_HIDE_MS);
    }

    function hideBanner() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner) {
            return;
        }
        banner.hidden = true;
        banner.dataset.seen = '1';
        document.body.classList.remove('has-cookie-consent-banner');
        if (autoHideTimer) {
            window.clearTimeout(autoHideTimer);
            autoHideTimer = null;
        }
    }

    function dismissBanner() {
        markSeen();
        hideBanner();
    }

    function bind() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner) {
            return;
        }

        const dismissBtn = document.getElementById('cookieConsentDismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', dismissBanner);
        }

        if (hasSeen()) {
            hideBanner();
            return;
        }

        if (!fulfillmentModalOpen()) {
            showBanner();
        }

        const modal = document.getElementById('fulfillmentModal');
        if (modal) {
            const observer = new MutationObserver(function () {
                if (fulfillmentModalOpen()) {
                    banner.hidden = true;
                    document.body.classList.remove('has-cookie-consent-banner');
                    if (autoHideTimer) {
                        window.clearTimeout(autoHideTimer);
                        autoHideTimer = null;
                    }
                    return;
                }
                showBanner();
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['hidden'] });
        }

        document.addEventListener('fulfillment:changed', function () {
            window.setTimeout(showBanner, 120);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();

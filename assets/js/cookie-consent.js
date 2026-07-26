(function () {
    'use strict';

    const COOKIE_NAME = 'am_cookie_consent';
    const STORAGE_KEY = 'am_cookie_consent';
    const MAX_AGE_DAYS = 365;

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function writeConsentCookie() {
        const maxAge = MAX_AGE_DAYS * 24 * 60 * 60;
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE_NAME + '=1; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // ignore
        }
    }

    function hasConsent() {
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

    function showBanner() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner || hasConsent() || fulfillmentModalOpen()) {
            return;
        }
        banner.hidden = false;
        document.body.classList.add('has-cookie-consent-banner');
    }

    function hideBanner() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner) {
            return;
        }
        banner.hidden = true;
        banner.dataset.consent = '1';
        document.body.classList.remove('has-cookie-consent-banner');
    }

    function acceptConsent() {
        writeConsentCookie();
        hideBanner();
    }

    function bind() {
        const banner = document.getElementById('cookieConsentBanner');
        if (!banner) {
            return;
        }

        const acceptBtn = document.getElementById('cookieConsentAccept');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', acceptConsent);
        }

        if (hasConsent()) {
            hideBanner();
            return;
        }

        // Wait until pickup/delivery chooser is closed so both aren't competing.
        showBanner();

        const modal = document.getElementById('fulfillmentModal');
        if (modal) {
            const observer = new MutationObserver(function () {
                if (!fulfillmentModalOpen()) {
                    showBanner();
                } else {
                    banner.hidden = true;
                    document.body.classList.remove('has-cookie-consent-banner');
                }
            });
            observer.observe(modal, { attributes: true, attributeFilter: ['hidden'] });
        }

        document.addEventListener('fulfillment:changed', function () {
            window.setTimeout(showBanner, 150);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();

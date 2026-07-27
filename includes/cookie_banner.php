<?php

declare(strict_types=1);

$cookieNoticeSeen = !empty($_COOKIE['am_cookie_notice']);
$fulfillmentBlocking = delivery_enabled() && !fulfillment_mode_chosen();
?>
<div
    class="cookie-consent-banner"
    id="cookieConsentBanner"
    role="status"
    aria-live="polite"
    aria-label="Cookie notice"
    <?= ($cookieNoticeSeen || $fulfillmentBlocking) ? 'hidden' : '' ?>
    data-seen="<?= $cookieNoticeSeen ? '1' : '0' ?>"
>
    <div class="cookie-consent-inner">
        <div class="cookie-consent-icon" aria-hidden="true">
            <i class="bi bi-info-circle"></i>
        </div>
        <div class="cookie-consent-copy">
            <strong>Cookies notice</strong>
            <p id="cookieConsentText">This site uses cookies to keep you signed in, remember pickup or delivery, and keep your cart working.</p>
        </div>
        <button type="button" class="cookie-consent-dismiss" id="cookieConsentDismiss" aria-label="Dismiss cookie notice">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
</div>

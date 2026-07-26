<?php

declare(strict_types=1);

$cookieConsentGiven = !empty($_COOKIE['am_cookie_consent']);
$fulfillmentBlocking = delivery_enabled() && !fulfillment_mode_chosen();
$hideCookieBanner = $cookieConsentGiven || $fulfillmentBlocking;
?>
<div
    class="cookie-consent-banner"
    id="cookieConsentBanner"
    role="dialog"
    aria-live="polite"
    aria-label="Cookie notice"
    aria-describedby="cookieConsentText"
    <?= $hideCookieBanner ? 'hidden' : '' ?>
    data-consent="<?= $cookieConsentGiven ? '1' : '0' ?>"
>
    <div class="cookie-consent-inner">
        <div class="cookie-consent-icon" aria-hidden="true">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="cookie-consent-copy">
            <strong>We use cookies</strong>
            <p id="cookieConsentText">
                We use cookies to keep you signed in, remember pickup or delivery, and keep your cart working.
            </p>
        </div>
        <div class="cookie-consent-actions">
            <button type="button" class="cookie-consent-accept" id="cookieConsentAccept">
                Accept
            </button>
        </div>
    </div>
</div>

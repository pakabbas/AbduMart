(function () {
    'use strict';

    const STORAGE_KEY = 'am_fulfillment_chosen';
    const MODE_KEY = 'am_fulfillment';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiUrl = document.querySelector('meta[name="fulfillment-url"]')?.content || '/api/fulfillment.php';

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function currentMode() {
        const bodyMode = document.body?.dataset?.fulfillment;
        if (bodyMode === 'pickup' || bodyMode === 'delivery') {
            return bodyMode;
        }
        const cookie = readCookie(MODE_KEY);
        if (cookie === 'pickup' || cookie === 'delivery') {
            return cookie;
        }
        return 'pickup';
    }

    function syncUi(mode) {
        document.body.dataset.fulfillment = mode;
        document.querySelectorAll('.js-fulfillment-toggle').forEach(function (group) {
            group.querySelectorAll('[data-mode]').forEach(function (btn) {
                const active = btn.getAttribute('data-mode') === mode;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });
        document.querySelectorAll('.js-fulfillment-label').forEach(function (el) {
            el.textContent = mode === 'delivery' ? 'Delivery' : 'Pickup';
        });
        document.dispatchEvent(new CustomEvent('fulfillment:changed', { detail: { mode: mode } }));
    }

    async function setMode(mode, opts) {
        opts = opts || {};
        if (mode !== 'pickup' && mode !== 'delivery') {
            return null;
        }
        if (mode === 'delivery' && document.body?.dataset?.deliveryEnabled === '0') {
            throw new Error('Delivery is currently unavailable');
        }

        const res = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
            body: (function () {
                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('mode', mode);
                return fd;
            })(),
        });

        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not save preference');
        }

        try {
            localStorage.setItem(STORAGE_KEY, '1');
            localStorage.setItem(MODE_KEY, mode);
        } catch (e) {
            // ignore storage failures
        }

        syncUi(mode);

        if (opts.reload) {
            window.location.reload();
        }

        return data;
    }

    function openModal() {
        const modal = document.getElementById('fulfillmentModal');
        if (!modal) {
            return;
        }
        modal.hidden = false;
        document.body.classList.add('fulfillment-modal-open');
    }

    function closeModal() {
        const modal = document.getElementById('fulfillmentModal');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('fulfillment-modal-open');
    }

    function shouldForceModal() {
        const modal = document.getElementById('fulfillmentModal');
        if (!modal) {
            return false;
        }
        if (modal.dataset.chosen === '1') {
            return false;
        }
        try {
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                return false;
            }
        } catch (e) {
            // ignore
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncUi(currentMode());

        document.querySelectorAll('.js-fulfillment-toggle [data-mode]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const mode = btn.getAttribute('data-mode');
                try {
                    await setMode(mode, { reload: btn.hasAttribute('data-reload') });
                } catch (err) {
                    alert(err.message || 'Could not update preference');
                }
            });
        });

        document.querySelectorAll('.js-fulfillment-pick').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const mode = btn.getAttribute('data-mode');
                try {
                    await setMode(mode);
                    closeModal();
                } catch (err) {
                    alert(err.message || 'Could not save preference');
                }
            });
        });

        document.querySelectorAll('[data-fulfillment-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
                // First visit requires a choice — do not dismiss without selecting.
                if (!shouldForceModal()) {
                    closeModal();
                }
            });
        });

        if (shouldForceModal()) {
            openModal();
        }
    });

    window.AbduFulfillment = {
        getMode: currentMode,
        setMode: setMode,
        syncUi: syncUi,
    };
})();

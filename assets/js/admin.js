(function () {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminOverlay');
    const menuBtn = document.getElementById('adminMenuBtn');

    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    menuBtn?.addEventListener('click', function () {
        if (sidebar?.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay?.addEventListener('click', closeSidebar);

    // Settings section nav highlight on scroll
    const settingsNav = document.querySelector('.settings-nav');
    if (settingsNav) {
        const links = settingsNav.querySelectorAll('a[href^="#"]');
        const sections = Array.from(links).map(function (a) {
            return document.querySelector(a.getAttribute('href'));
        }).filter(Boolean);

        function onScroll() {
            let current = sections[0];
            sections.forEach(function (sec) {
                if (sec.getBoundingClientRect().top <= 120) current = sec;
            });
            links.forEach(function (a) {
                a.classList.toggle('active', a.getAttribute('href') === '#' + current.id);
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        links.forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    closeSidebar();
                }
            });
        });
    }

    // Dashboard arrival polling
    if (window.ADMIN_POLL_URL) {
        const waitingEl = document.getElementById('waiting-count');
        const badgeEl = document.getElementById('arrival-badge');
        const listEl = document.getElementById('arrivals-list');

        async function poll() {
            try {
                const res = await fetch(window.ADMIN_POLL_URL);
                const data = await res.json();
                if (waitingEl) waitingEl.textContent = data.waiting_count;
                if (badgeEl) badgeEl.textContent = data.waiting_count;
                if (listEl && data.arrivals) {
                    if (data.arrivals.length === 0) {
                        listEl.innerHTML = '<div class="admin-empty"><i class="bi bi-car-front"></i><p>No customers checked in yet.</p></div>';
                    } else {
                        listEl.innerHTML = data.arrivals.map(function (a) {
                            var csrf = document.querySelector('meta[name="csrf-token"]');
                            var csrfToken = csrf ? csrf.getAttribute('content') : '';
                            var callBtn = '';
                            if (a.phone) {
                                var digits = String(a.phone).replace(/\D+/g, '');
                                if (digits.length >= 10) {
                                    callBtn = '<a href="tel:' + digits + '" class="admin-btn admin-btn-outline admin-btn-sm" aria-label="Call customer" title="Call customer"><i class="bi bi-telephone-fill"></i> <span>Call</span></a>';
                                }
                            }
                            return '<div class="arrival-row"><div class="d-flex justify-content-between align-items-start mb-1"><strong>' +
                                esc(a.customer_name) + '</strong><span class="admin-badge admin-badge-red">' + esc(a.order_number) + '</span></div>' +
                                '<div class="small text-muted mb-2">Arrived ' + esc(a.arrived_label) +
                                (a.vehicle ? ' · ' + esc(a.vehicle) : '') + '</div>' +
                                '<div class="d-flex gap-2 flex-wrap">' +
                                '<a href="orders.php?id=' + a.id + '" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a>' +
                                '<form method="post" class="m-0">' +
                                '<input type="hidden" name="csrf_token" value="' + esc(csrfToken) + '">' +
                                '<input type="hidden" name="action" value="picked_up">' +
                                '<input type="hidden" name="order_id" value="' + a.id + '">' +
                                '<button type="submit" class="admin-btn admin-btn-outline admin-btn-sm"><i class="bi bi-bag-check"></i> Picked up</button>' +
                                '</form>' +
                                callBtn +
                                '</div></div>';
                        }).join('');
                    }
                }
            } catch (e) { /* silent */ }
        }

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s || '';
            return d.innerHTML;
        }

        setInterval(poll, 8000);
    }

    // Browser push notifications (admin)
    (function initAdminPush() {
        const statusEl = document.getElementById('adminPushStatus');
        const enableBtn = document.getElementById('adminPushEnableBtn');
        const disableBtn = document.getElementById('adminPushDisableBtn');
        const apiMeta = document.querySelector('meta[name="admin-push-url"]');
        const swMeta = document.querySelector('meta[name="admin-push-sw"]');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!apiMeta || !swMeta || !csrfMeta) {
            return;
        }

        const apiUrl = apiMeta.getAttribute('content');
        const swUrl = swMeta.getAttribute('content');
        const csrf = csrfMeta.getAttribute('content');

        function setStatus(text) {
            if (statusEl) statusEl.textContent = text;
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        async function getConfig() {
            const res = await fetch(apiUrl, { credentials: 'same-origin' });
            return res.json();
        }

        async function syncStatus() {
            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                setStatus('Browser push is not supported in this browser.');
                return;
            }
            try {
                const cfg = await getConfig();
                if (!cfg.configured) {
                    setStatus('Generate VAPID keys first, then enable on this device.');
                    return;
                }
                const reg = await navigator.serviceWorker.getRegistration(swUrl);
                const sub = reg ? await reg.pushManager.getSubscription() : null;
                if (Notification.permission === 'granted' && sub) {
                    setStatus('Enabled on this device.');
                } else if (Notification.permission === 'denied') {
                    setStatus('Blocked by the browser. Allow notifications for this site.');
                } else {
                    setStatus('Not enabled on this device yet.');
                }
            } catch (err) {
                setStatus('Could not check notification status.');
            }
        }

        async function enablePush() {
            try {
                const cfg = await getConfig();
                if (!cfg.configured || !cfg.publicKey) {
                    setStatus('Generate VAPID keys in Settings first.');
                    return;
                }
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    setStatus('Permission not granted.');
                    return;
                }
                const reg = await navigator.serviceWorker.register(swUrl, { scope: '/' });
                await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(cfg.publicKey),
                    });
                }
                const payload = sub.toJSON();
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'subscribe',
                        csrf_token: csrf,
                        endpoint: payload.endpoint,
                        keys: payload.keys,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error || 'Subscribe failed');
                }
                setStatus('Enabled on this device.');
            } catch (err) {
                setStatus(err.message || 'Could not enable notifications.');
            }
        }

        async function disablePush() {
            try {
                const reg = await navigator.serviceWorker.getRegistration(swUrl);
                const sub = reg ? await reg.pushManager.getSubscription() : null;
                if (sub) {
                    await fetch(apiUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'unsubscribe',
                            csrf_token: csrf,
                            endpoint: sub.endpoint,
                        }),
                    });
                    await sub.unsubscribe();
                }
                setStatus('Disabled on this device.');
            } catch (err) {
                setStatus('Could not disable notifications.');
            }
        }

        enableBtn?.addEventListener('click', enablePush);
        disableBtn?.addEventListener('click', disablePush);

        const generateForm = document.getElementById('generate-vapid-form');
        generateForm?.addEventListener('submit', function () {
            const subject = document.querySelector('#webpush input[name="vapid_subject"]')?.value || '';
            let input = generateForm.querySelector('input[name="vapid_subject"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'vapid_subject';
                generateForm.appendChild(input);
            }
            input.value = subject;
        });

        syncStatus();
    })();
})();

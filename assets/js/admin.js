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
                            return '<div class="arrival-row"><div class="d-flex justify-content-between align-items-start mb-1"><strong>' +
                                esc(a.customer_name) + '</strong><span class="admin-badge admin-badge-red">' + esc(a.order_number) + '</span></div>' +
                                '<div class="small text-muted mb-2">Arrived ' + esc(a.arrived_label) +
                                (a.vehicle ? ' · ' + esc(a.vehicle) : '') + '</div>' +
                                '<a href="orders.php?id=' + a.id + '" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a></div>';
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
})();

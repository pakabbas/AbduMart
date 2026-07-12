(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="csrf_token"]')?.value;

    // Add to cart via AJAX
    document.querySelectorAll('.add-to-cart-form').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Added';
                    btn.classList.replace('btn-danger', 'btn-success');
                    updateCartBadge(data.cart_count);
                    setTimeout(function () {
                        btn.innerHTML = original;
                        btn.classList.replace('btn-success', 'btn-danger');
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert(data.error || 'Could not add to cart');
                    btn.innerHTML = original;
                    btn.disabled = false;
                }
            } catch (err) {
                form.submit();
            }
        });
    });

    function updateCartBadge(count) {
        let badge = document.querySelector('.cart-badge');
        const cartBtn = document.querySelector('a[href="cart.php"]');
        if (!cartBtn) return;
        if (!badge && count > 0) {
            badge = document.createElement('span');
            badge.className = 'cart-badge';
            cartBtn.appendChild(badge);
        }
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-flex' : 'none';
        }
    }

    // Cart quantity controls
    document.querySelectorAll('.qty-form').forEach(function (form) {
        const input = form.querySelector('.qty-input');
        const minus = form.querySelector('.qty-minus');
        const plus = form.querySelector('.qty-plus');

        function submitQty() {
            form.submit();
        }

        minus?.addEventListener('click', function () {
            input.value = Math.max(1, parseInt(input.value, 10) - 1);
            submitQty();
        });
        plus?.addEventListener('click', function () {
            const max = parseInt(input.max, 10) || 99;
            input.value = Math.min(max, parseInt(input.value, 10) + 1);
            submitQty();
        });
    });

    // I'm Here button
    document.querySelectorAll('.im-here-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const orderId = btn.dataset.orderId;
            const panel = btn.closest('.im-here-panel');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Notifying mart...';

            try {
                const res = await fetch('api/im-here.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken || ''
                    },
                    body: JSON.stringify({ order_id: orderId, csrf_token: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    panel.innerHTML = '<div class="text-center"><i class="bi bi-geo-alt-fill text-danger fs-1"></i>' +
                        '<h3 class="h5 mt-2">We\'re on our way!</h3>' +
                        '<p class="text-muted mb-0">' + (data.message || 'Please stay in your vehicle.') + '</p></div>';
                } else {
                    alert(data.error || 'Check-in failed');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> I\'M HERE';
                }
            } catch (err) {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-geo-alt-fill"></i> I\'M HERE';
            }
        });
    });

    // Admin dashboard polling for arrivals
    if (window.ADMIN_POLL_URL) {
        const waitingEl = document.getElementById('waiting-count');
        const badgeEl = document.getElementById('arrival-badge');
        const listEl = document.getElementById('arrivals-list');

        async function pollArrivals() {
            try {
                const res = await fetch(window.ADMIN_POLL_URL);
                const data = await res.json();
                if (waitingEl) waitingEl.textContent = data.waiting_count;
                if (badgeEl) badgeEl.textContent = data.waiting_count;

                if (listEl && data.arrivals) {
                    if (data.arrivals.length === 0) {
                        listEl.innerHTML = '<p class="text-muted text-center py-5 mb-0">No customers checked in yet.</p>';
                    } else {
                        listEl.innerHTML = '<div class="list-group list-group-flush">' +
                            data.arrivals.map(function (a) {
                                return '<div class="list-group-item arrival-item">' +
                                    '<div class="d-flex justify-content-between"><strong>' + escapeHtml(a.customer_name) + '</strong>' +
                                    '<span class="text-danger fw-semibold">' + escapeHtml(a.order_number) + '</span></div>' +
                                    '<div class="small text-muted">Arrived ' + escapeHtml(a.arrived_label) +
                                    (a.vehicle ? ' · ' + escapeHtml(a.vehicle) : '') + '</div>' +
                                    '<div class="mt-2"><a href="orders.php?id=' + a.id + '" class="btn btn-sm btn-danger">Manage Order</a></div>' +
                                    '</div>';
                            }).join('') + '</div>';
                    }
                }
            } catch (e) { /* silent */ }
        }

        setInterval(pollArrivals, 8000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
})();

(function () {
    'use strict';

    const root = document.getElementById('floatingCart');
    if (!root) {
        return;
    }

    const fab = document.getElementById('floatingCartFab');
    const fabBadge = document.getElementById('floatingCartFabBadge');
    const panel = document.getElementById('floatingCartPanel');
    const overlay = document.getElementById('floatingCartOverlay');
    const closeBtn = document.getElementById('floatingCartClose');
    const body = document.getElementById('floatingCartBody');
    const footer = document.getElementById('floatingCartFooter');
    const footerClosed = document.getElementById('floatingCartFooterClosed');
    const basketUrl = root.dataset.basketUrl;
    const shopUrl = root.dataset.shopUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let isOpen = false;
    let isLoading = false;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function updateHeaderTotal(label) {
        if (label === undefined || label === null) {
            return;
        }
        document.querySelectorAll('.js-header-cart-total').forEach(function (el) {
            el.textContent = label;
        });
    }

    function updateBadges(count, subtotalLabel) {
        if (count !== undefined && count !== null && count !== '') {
            const n = parseInt(count, 10) || 0;
            root.dataset.count = String(n);

            if (fabBadge) {
                fabBadge.textContent = n;
                fabBadge.hidden = n <= 0;
            }

            document.querySelectorAll('.js-floating-cart-open').forEach(function (navBtn) {
                let navBadge = navBtn.querySelector('.cart-badge');
                if (!navBadge && n > 0) {
                    navBadge = document.createElement('span');
                    navBadge.className = 'cart-badge';
                    const iconWrap = navBtn.querySelector('.site-header-cart-icon-wrap, .mobile-bottom-nav-cart-wrap');
                    (iconWrap || navBtn).appendChild(navBadge);
                }
                if (navBadge) {
                    navBadge.textContent = String(n);
                    navBadge.classList.toggle('is-empty', n <= 0);
                    navBadge.style.display = n > 0 ? 'inline-flex' : 'none';
                }

                const bagIcon = navBtn.querySelector('.bi-bag, .bi-bag-fill');
                if (bagIcon) {
                    bagIcon.classList.toggle('bi-bag-fill', n > 0);
                    bagIcon.classList.toggle('bi-bag', n <= 0);
                }
            });
        }

        updateHeaderTotal(subtotalLabel);
    }

    function openPanel() {
        isOpen = true;
        panel.classList.add('is-open');
        overlay.hidden = false;
        document.body.classList.add('floating-cart-open');
        loadBasket();
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('is-open');
        overlay.hidden = true;
        document.body.classList.remove('floating-cart-open');
    }

    function renderEmpty() {
        footer.hidden = true;
        if (footerClosed) {
            footerClosed.hidden = true;
        }
        body.innerHTML =
            '<div class="floating-cart-empty text-center py-5">' +
            '<i class="bi bi-bag display-6 text-danger"></i>' +
            '<p class="text-muted mt-3 mb-0">Your cart is empty</p>' +
            '</div>';
    }

    function renderBasket(data) {
        updateBadges(data.count);

        footer.hidden = true;
        if (footerClosed) {
            footerClosed.hidden = true;
        }

        if (!data.items || data.items.length === 0) {
            renderEmpty();
            return;
        }

        const useClosedFooter = root.dataset.storeClosed === '1' && footerClosed;
        if (useClosedFooter) {
            footerClosed.hidden = false;
            document.getElementById('floatingCartSubtotalClosed').textContent = data.subtotal_label;
            document.getElementById('floatingCartTaxClosed').textContent = data.tax_label;
            document.getElementById('floatingCartTotalClosed').textContent = data.total_label;
        } else {
            footer.hidden = false;
            document.getElementById('floatingCartSubtotal').textContent = data.subtotal_label;
            document.getElementById('floatingCartTax').textContent = data.tax_label;
            document.getElementById('floatingCartTotal').textContent = data.total_label;
        }

        body.innerHTML = data.items.map(function (item) {
            const thumb = item.image_url
                ? '<img src="' + escapeHtml(item.image_url) + '" alt="" class="fc-item-img" loading="lazy">'
                : '<span class="fc-item-initials">' + escapeHtml(item.initials) + '</span>';

            return '<div class="fc-item" data-product-id="' + item.product_id + '">' +
                '<div class="fc-item-thumb">' + thumb + '</div>' +
                '<div class="fc-item-info">' +
                '<strong class="fc-item-name">' + escapeHtml(item.name) + '</strong>' +
                '<span class="fc-item-price">' + escapeHtml(item.price_label) + '</span>' +
                '<div class="fc-item-qty">' +
                '<button type="button" class="fc-qty-btn" data-action="minus" data-id="' + item.product_id + '" aria-label="Decrease">−</button>' +
                '<span class="fc-qty-val">' + item.quantity + '</span>' +
                '<button type="button" class="fc-qty-btn" data-action="plus" data-id="' + item.product_id + '" data-max="' + item.inventory + '" aria-label="Increase">+</button>' +
                '</div>' +
                '</div>' +
                '<div class="fc-item-end">' +
                '<span class="fc-item-line">' + escapeHtml(item.line_total_label) + '</span>' +
                '<button type="button" class="fc-remove-btn" data-id="' + item.product_id + '" aria-label="Remove"><i class="bi bi-trash"></i></button>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    async function loadBasket() {
        if (isLoading) {
            return;
        }
        isLoading = true;
        try {
            const res = await fetch(basketUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const raw = await res.text();
            const data = JSON.parse(raw);
            if (data.success) {
                renderBasket(data);
                updateBadges(data.count, data.subtotal_label);
            } else if (data.login_required) {
                window.location.href = 'login.php';
            }
        } catch (e) {
            body.innerHTML = '<p class="text-danger text-center py-4">Could not load cart.</p>';
        } finally {
            isLoading = false;
        }
    }

    async function shopAction(action, productId, quantity) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', action);
        formData.append('product_id', String(productId));
        if (quantity !== undefined) {
            formData.append('quantity', String(quantity));
        }

        const res = await fetch(shopUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
        });

        const raw = await res.text();
        const data = JSON.parse(raw);
        if (!data.success) {
            throw new Error(data.error || 'Cart update failed');
        }
        updateBadges(data.cart_count, data.cart_subtotal_label);
        await loadBasket();
        return data;
    }

    fab.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);

    document.querySelectorAll('.js-floating-cart-open').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openPanel();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) {
            closePanel();
        }
    });

    body.addEventListener('click', async function (e) {
        const minus = e.target.closest('[data-action="minus"]');
        const plus = e.target.closest('[data-action="plus"]');
        const remove = e.target.closest('.fc-remove-btn');

        try {
            if (minus) {
                const id = parseInt(minus.dataset.id, 10);
                const row = minus.closest('.fc-item');
                const qty = Math.max(0, parseInt(row.querySelector('.fc-qty-val').textContent, 10) - 1);
                await shopAction(qty === 0 ? 'remove' : 'update', id, qty || 1);
            } else if (plus) {
                const id = parseInt(plus.dataset.id, 10);
                const max = parseInt(plus.dataset.max, 10) || 99;
                const row = plus.closest('.fc-item');
                const qty = Math.min(max, parseInt(row.querySelector('.fc-qty-val').textContent, 10) + 1);
                await shopAction('update', id, qty);
            } else if (remove) {
                await shopAction('remove', parseInt(remove.dataset.id, 10));
            }
        } catch (err) {
            alert(err.message || 'Could not update cart');
        }
    });

    document.addEventListener('cart:updated', function (e) {
        const detail = e.detail || {};
        if (detail.count !== undefined || detail.subtotal_label !== undefined) {
            updateBadges(detail.count, detail.subtotal_label);
        }
        if (detail.openPanel) {
            openPanel();
        } else if (isOpen) {
            loadBasket();
        } else if (detail.subtotal_label === undefined && detail.count !== undefined) {
            // Refresh totals when add-to-cart didn't include a subtotal label.
            fetch(basketUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        updateBadges(data.count, data.subtotal_label);
                    }
                })
                .catch(function () { /* ignore */ });
        }
    });

    window.FloatingCart = {
        open: openPanel,
        close: closePanel,
        refresh: loadBasket,
        updateBadge: updateBadges,
    };

    updateBadges(root.dataset.count || 0);
})();

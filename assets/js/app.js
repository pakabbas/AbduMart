(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const siteHeader = document.getElementById('siteHeader');
        if (siteHeader) {
            let headerScrollTicking = false;
            let isCompact = siteHeader.classList.contains('is-compact');
            let lastScrollY = window.scrollY || window.pageYOffset || 0;
            let lockUntil = 0;

            function syncHeaderCompact(force) {
                headerScrollTicking = false;
                const now = performance.now();
                const y = window.scrollY || window.pageYOffset || 0;
                const dy = y - lastScrollY;
                lastScrollY = y;

                // After a compact toggle the header height changes and the browser
                // adjusts scrollY. Ignore those synthetic jumps so we don't flap.
                if (!force && now < lockUntil) {
                    return;
                }

                // Fixed thresholds (do not measure collapsing bars — that feeds flicker).
                const enterAt = 56;
                const exitAt = 10;
                let nextCompact = isCompact;

                if (isCompact) {
                    // Only expand when truly near the top (and preferably scrolling up).
                    nextCompact = !(y <= exitAt && dy <= 0);
                    if (y <= 2) {
                        nextCompact = false;
                    }
                } else if (y >= enterAt && dy >= 0) {
                    nextCompact = true;
                }

                if (nextCompact === isCompact) {
                    return;
                }

                const heightBefore = siteHeader.offsetHeight;
                isCompact = nextCompact;
                siteHeader.classList.toggle('is-compact', isCompact);
                const heightAfter = siteHeader.offsetHeight;
                const delta = heightBefore - heightAfter;

                // Keep page content from jumping when sticky header height changes.
                if (delta !== 0) {
                    const adjusted = Math.max(0, (window.scrollY || 0) - delta);
                    if (Math.abs(adjusted - (window.scrollY || 0)) > 0.5) {
                        window.scrollTo(0, adjusted);
                    }
                    lastScrollY = window.scrollY || window.pageYOffset || 0;
                }

                lockUntil = performance.now() + 350;
            }

            window.addEventListener('scroll', function () {
                if (headerScrollTicking) {
                    return;
                }
                headerScrollTicking = true;
                requestAnimationFrame(function () {
                    syncHeaderCompact(false);
                });
            }, { passive: true });

            window.addEventListener('resize', function () {
                lockUntil = 0;
                syncHeaderCompact(true);
            }, { passive: true });

            syncHeaderCompact(true);
        }

        (function initProductSearchSuggest() {
            const suggestUrlMeta = document.querySelector('meta[name="search-suggest-url"]');
            const suggestUrl = suggestUrlMeta ? suggestUrlMeta.getAttribute('content') : '/api/search-suggest.php';
            const forms = document.querySelectorAll('.js-product-search');
            if (!forms.length || !suggestUrl) {
                return;
            }

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str || '';
                return div.innerHTML;
            }

            forms.forEach(function (form) {
                const input = form.querySelector('.js-product-search-input');
                const panelId = form.getAttribute('data-suggest-panel');
                const panel = panelId ? document.getElementById(panelId) : form.querySelector('.search-suggest');
                if (!input || !panel) {
                    return;
                }

                let debounceTimer = null;
                let activeIndex = -1;
                let currentItems = [];
                let abortController = null;
                let latestQuery = '';

                function setExpanded(open) {
                    input.setAttribute('aria-expanded', open ? 'true' : 'false');
                    panel.hidden = !open;
                }

                function clearActive() {
                    activeIndex = -1;
                    panel.querySelectorAll('.search-suggest-item').forEach(function (el) {
                        el.classList.remove('is-active');
                    });
                }

                function hidePanel() {
                    setExpanded(false);
                    panel.innerHTML = '';
                    currentItems = [];
                    clearActive();
                }

                function setActive(index) {
                    const items = panel.querySelectorAll('.search-suggest-item');
                    if (!items.length) {
                        return;
                    }
                    activeIndex = (index + items.length) % items.length;
                    items.forEach(function (el, i) {
                        el.classList.toggle('is-active', i === activeIndex);
                    });
                    items[activeIndex].scrollIntoView({ block: 'nearest' });
                }

                function renderResults(data, query) {
                    const products = Array.isArray(data.products) ? data.products : [];
                    currentItems = products;
                    clearActive();

                    if (products.length === 0) {
                        panel.innerHTML = '<div class="search-suggest-empty">No products found for &ldquo;' +
                            escapeHtml(query) + '&rdquo;</div>';
                        setExpanded(true);
                        return;
                    }

                    const listHtml = products.map(function (product, index) {
                        const meta = [product.category, product.in_stock ? null : 'Out of stock']
                            .filter(Boolean)
                            .join(' · ');
                        return '<a class="search-suggest-item" role="option" href="' + escapeHtml(product.url) + '" data-index="' + index + '">' +
                            '<img class="search-suggest-thumb" src="' + escapeHtml(product.image) + '" alt="" width="42" height="42" loading="lazy">' +
                            '<span class="search-suggest-copy">' +
                            '<span class="search-suggest-name">' + escapeHtml(product.name) + '</span>' +
                            (meta ? '<span class="search-suggest-meta">' + escapeHtml(meta) + '</span>' : '') +
                            '</span>' +
                            '<span class="search-suggest-price">' + escapeHtml(product.price) + '</span>' +
                            '</a>';
                    }).join('');

                    const viewAll = data.view_all_url
                        ? '<div class="search-suggest-footer"><a href="' + escapeHtml(data.view_all_url) + '">View all results</a></div>'
                        : '';

                    panel.innerHTML = listHtml + viewAll;
                    setExpanded(true);
                }

                function fetchSuggestions(query) {
                    latestQuery = query;
                    if (query.length < 2) {
                        hidePanel();
                        return;
                    }

                    if (abortController) {
                        abortController.abort();
                    }
                    abortController = new AbortController();

                    fetch(suggestUrl + '?q=' + encodeURIComponent(query) + '&limit=8', {
                        headers: { Accept: 'application/json' },
                        signal: abortController.signal,
                        credentials: 'same-origin',
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (query !== latestQuery) {
                                return;
                            }
                            renderResults(data || {}, query);
                        })
                        .catch(function (err) {
                            if (err && err.name === 'AbortError') {
                                return;
                            }
                            hidePanel();
                        });
                }

                input.addEventListener('input', function () {
                    const query = (input.value || '').trim();
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(function () {
                        fetchSuggestions(query);
                    }, 180);
                });

                input.addEventListener('keydown', function (event) {
                    if (panel.hidden) {
                        return;
                    }
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActive(activeIndex + 1);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActive(activeIndex - 1);
                    } else if (event.key === 'Escape') {
                        hidePanel();
                    } else if (event.key === 'Enter' && activeIndex >= 0 && currentItems[activeIndex]) {
                        event.preventDefault();
                        window.location.href = currentItems[activeIndex].url;
                    }
                });

                input.addEventListener('focus', function () {
                    const query = (input.value || '').trim();
                    if (query.length >= 2 && panel.innerHTML.trim() !== '') {
                        setExpanded(true);
                    } else if (query.length >= 2) {
                        fetchSuggestions(query);
                    }
                });

                document.addEventListener('click', function (event) {
                    if (!form.contains(event.target)) {
                        hidePanel();
                    }
                });
            });
        })();

        const categoryGrid = document.getElementById('categoryGrid');
        const categorySeeMoreBtn = document.getElementById('categorySeeMoreBtn');

        if (categoryGrid && categorySeeMoreBtn) {
            categorySeeMoreBtn.addEventListener('click', function () {
                const expanded = categoryGrid.classList.toggle('is-expanded');
                categoryGrid.classList.toggle('is-collapsed', !expanded);
                categorySeeMoreBtn.textContent = expanded ? 'See Less' : 'See More';
                categorySeeMoreBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        }

        const featuredCarousel = document.getElementById('featuredCarousel');
        const featuredPrev = document.getElementById('featuredCarouselPrev');
        const featuredNext = document.getElementById('featuredCarouselNext');

        if (featuredCarousel && featuredPrev && featuredNext) {
            const slides = Array.from(featuredCarousel.querySelectorAll('.featured-carousel-slide'));
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let originalWidth = 0;
            let autoTimer = null;
            let autoPaused = false;
            let normalizeTimer = null;

            function getScrollStep() {
                const slide = slides[0];
                if (!slide) {
                    return 236;
                }
                const gap = parseFloat(getComputedStyle(featuredCarousel).gap) || 16;
                return slide.offsetWidth + gap;
            }

            function measureOriginalWidth() {
                if (slides.length === 0) {
                    return 0;
                }
                const last = slides[slides.length - 1];
                return last.offsetLeft + last.offsetWidth;
            }

            function withoutSmoothScroll(run) {
                const previous = featuredCarousel.style.scrollBehavior;
                featuredCarousel.style.scrollBehavior = 'auto';
                run();
                featuredCarousel.style.scrollBehavior = previous;
            }

            function normalizeScrollPosition() {
                if (originalWidth <= 0) {
                    return;
                }
                withoutSmoothScroll(function () {
                    if (featuredCarousel.scrollLeft >= originalWidth) {
                        featuredCarousel.scrollLeft -= originalWidth;
                    } else if (featuredCarousel.scrollLeft < 0) {
                        featuredCarousel.scrollLeft += originalWidth;
                    }
                });
            }

            function scheduleNormalizeScrollPosition() {
                if (normalizeTimer) {
                    window.clearTimeout(normalizeTimer);
                }
                normalizeTimer = window.setTimeout(function () {
                    normalizeTimer = null;
                    normalizeScrollPosition();
                }, 450);
            }

            function scrollFeaturedBy(direction) {
                const step = getScrollStep() * direction;

                if (direction < 0 && featuredCarousel.scrollLeft <= 1) {
                    withoutSmoothScroll(function () {
                        featuredCarousel.scrollLeft = originalWidth;
                    });
                }

                featuredCarousel.scrollBy({ left: step, behavior: 'smooth' });
                scheduleNormalizeScrollPosition();
            }

            function pauseFeaturedAuto() {
                autoPaused = true;
                if (autoTimer) {
                    window.clearInterval(autoTimer);
                    autoTimer = null;
                }
            }

            function startFeaturedAuto() {
                if (autoPaused || prefersReducedMotion || slides.length <= 1) {
                    return;
                }
                if (autoTimer) {
                    window.clearInterval(autoTimer);
                }
                autoTimer = window.setInterval(function () {
                    scrollFeaturedBy(1);
                }, 3000);
            }

            function resumeFeaturedAutoAfter(delayMs) {
                pauseFeaturedAuto();
                window.setTimeout(function () {
                    autoPaused = false;
                    if (!document.hidden) {
                        startFeaturedAuto();
                    }
                }, delayMs);
            }

            if (slides.length > 1) {
                const fragment = document.createDocumentFragment();
                slides.forEach(function (slide) {
                    const clone = slide.cloneNode(true);
                    clone.classList.add('featured-carousel-slide-clone');
                    clone.setAttribute('aria-hidden', 'true');
                    fragment.appendChild(clone);
                });
                featuredCarousel.appendChild(fragment);
                originalWidth = measureOriginalWidth();

                window.addEventListener('resize', function () {
                    originalWidth = measureOriginalWidth();
                    normalizeScrollPosition();
                });

                featuredPrev.addEventListener('click', function () {
                    scrollFeaturedBy(-1);
                    resumeFeaturedAutoAfter(5000);
                });

                featuredNext.addEventListener('click', function () {
                    scrollFeaturedBy(1);
                    resumeFeaturedAutoAfter(5000);
                });

                featuredCarousel.addEventListener('mouseenter', pauseFeaturedAuto);
                featuredCarousel.addEventListener('mouseleave', function () {
                    autoPaused = false;
                    startFeaturedAuto();
                });
                featuredCarousel.addEventListener('focusin', pauseFeaturedAuto);
                featuredCarousel.addEventListener('focusout', function () {
                    autoPaused = false;
                    startFeaturedAuto();
                });

                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        pauseFeaturedAuto();
                    } else {
                        autoPaused = false;
                        startFeaturedAuto();
                    }
                });

                startFeaturedAuto();
            } else {
                featuredPrev.addEventListener('click', function () {
                    featuredCarousel.scrollBy({ left: -getScrollStep(), behavior: 'smooth' });
                });

                featuredNext.addEventListener('click', function () {
                    featuredCarousel.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
                });
            }
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="csrf_token"]')?.value;

        document.querySelectorAll('.mart-buy-form').forEach(function (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                if (!btn) {
                    return;
                }

                const original = btn.innerHTML;
                const formCsrf = form.querySelector('input[name="csrf_token"]')?.value || csrfToken || '';
                const postUrl = form.getAttribute('action')
                    || document.querySelector('meta[name="mart-line-url"]')?.content
                    || '/mart-line.php';
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                try {
                    const res = await fetch(postUrl, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-Token': formCsrf,
                        },
                    });

                    const raw = await res.text();
                    let data = null;
                    const trimmed = raw.trim();

                    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
                        try {
                            data = JSON.parse(trimmed);
                        } catch (parseError) {
                            console.error('Cart JSON parse error', parseError, raw.slice(0, 300));
                            throw new Error('Invalid response from server');
                        }
                    } else {
                        // Some hosts inject warnings/HTML before JSON. Try to recover by extracting the first JSON object.
                        const firstBrace = trimmed.indexOf('{');
                        const lastBrace = trimmed.lastIndexOf('}');
                        if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
                            const maybeJson = trimmed.slice(firstBrace, lastBrace + 1);
                            try {
                                data = JSON.parse(maybeJson);
                            } catch (parseError) {
                                console.error('Cart non-JSON response', res.status, raw.slice(0, 300));
                                throw new Error('Unexpected server response');
                            }
                        } else {
                            console.error('Cart non-JSON response', res.status, raw.slice(0, 300));
                            throw new Error(trimmed === '' ? 'Empty response (blocked by browser extension?)' : 'Unexpected server response');
                        }
                    }

                    if (data.success) {
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Added';
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-success');

                        // Auto-open the side cart only for the first item (empty → non-empty).
                        const prevCount = parseInt(
                            document.getElementById('floatingCart')?.dataset.count
                            || document.querySelector('.js-floating-cart-open .cart-badge')?.textContent
                            || '0',
                            10
                        ) || 0;
                        const nextCount = parseInt(data.cart_count, 10) || 0;
                        document.dispatchEvent(new CustomEvent('cart:updated', {
                            detail: {
                                count: nextCount,
                                subtotal_label: data.cart_subtotal_label,
                                openPanel: prevCount === 0 && nextCount > 0,
                            },
                        }));

                        setTimeout(function () {
                            btn.innerHTML = original;
                            btn.classList.remove('btn-success');
                            btn.classList.add('btn-danger');
                            btn.disabled = false;
                        }, 1200);
                    } else if (data.login_required) {
                        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    } else {
                        alert(data.error || 'Could not add to cart');
                        btn.innerHTML = original;
                        btn.disabled = false;
                    }
                } catch (err) {
                    console.error('Add to cart failed', err);
                    alert(err.message || 'Could not add to cart. Try disabling ad blockers for this site, then refresh.');
                    btn.innerHTML = original;
                    btn.disabled = false;
                }
            });
        });

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

        document.querySelectorAll('.im-here-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const orderId = btn.dataset.orderId;
                const panel = btn.closest('.im-here-panel');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Notifying mart...';

                try {
                    const pickupUrl = document.querySelector('meta[name="pickup-here-url"]')?.content || 'pickup-here.php';
                    const res = await fetch(pickupUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken || '',
                        },
                        body: JSON.stringify({ order_id: orderId, csrf_token: csrfToken }),
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

        document.querySelectorAll('.catalog-tile-img').forEach(function (img) {
            function showInitials() {
                img.classList.add('is-hidden');
                const media = img.closest('.catalog-tile-media');
                if (media) {
                    media.classList.add('show-initials');
                }
            }

            img.addEventListener('error', showInitials);
            if (img.complete && img.naturalWidth === 0) {
                showInitials();
            }
        });
    });

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

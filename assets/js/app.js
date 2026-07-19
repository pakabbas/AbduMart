(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const siteHeader = document.getElementById('siteHeader');
        if (siteHeader) {
            const topBar = siteHeader.querySelector('.site-header-top');
            const navBar = siteHeader.querySelector('.site-header-nav-bar');
            let headerScrollTicking = false;
            let isCompact = siteHeader.classList.contains('is-compact');

            function measureHideOffset() {
                const topH = topBar && !isCompact ? topBar.offsetHeight : (topBar ? topBar.scrollHeight : 0);
                const navH = navBar && !isCompact ? navBar.offsetHeight : (navBar ? navBar.scrollHeight : 0);
                // Collapse once the top bar is mostly scrolled past.
                return Math.max(24, Math.round(topH * 0.6) || 24);
            }

            function syncHeaderCompact() {
                headerScrollTicking = false;
                const y = window.scrollY || window.pageYOffset || 0;
                // Hysteresis: enter compact after scrolling past the top bar,
                // leave compact only when near the very top. Prevents blink
                // when collapsing the sticky header changes layout/scroll.
                const enterAt = measureHideOffset();
                const exitAt = 8;
                let nextCompact = isCompact;

                if (isCompact) {
                    nextCompact = y > exitAt;
                } else {
                    nextCompact = y >= enterAt;
                }

                if (nextCompact === isCompact) {
                    return;
                }

                isCompact = nextCompact;
                siteHeader.classList.toggle('is-compact', isCompact);
            }

            window.addEventListener('scroll', function () {
                if (headerScrollTicking) {
                    return;
                }
                headerScrollTicking = true;
                requestAnimationFrame(syncHeaderCompact);
            }, { passive: true });

            window.addEventListener('resize', function () {
                if (!isCompact) {
                    syncHeaderCompact();
                }
            }, { passive: true });

            syncHeaderCompact();
        }

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

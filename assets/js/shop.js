(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const shopApp = document.getElementById('shopApp');
        const categoryNav = document.getElementById('shopCategoryNav');
        const categoryTrack = document.getElementById('shopCategoryTrack');
        const categoryPrev = document.getElementById('shopCategoryPrev');
        const categoryNext = document.getElementById('shopCategoryNext');

        if (categoryTrack && categoryPrev && categoryNext) {
            const scrollStep = 220;

            function updateCarouselButtons() {
                const maxScroll = categoryTrack.scrollWidth - categoryTrack.clientWidth;
                categoryPrev.disabled = categoryTrack.scrollLeft <= 4;
                categoryNext.disabled = categoryTrack.scrollLeft >= maxScroll - 4;
            }

            categoryPrev.addEventListener('click', function () {
                categoryTrack.scrollBy({ left: -scrollStep, behavior: 'smooth' });
            });

            categoryNext.addEventListener('click', function () {
                categoryTrack.scrollBy({ left: scrollStep, behavior: 'smooth' });
            });

            categoryTrack.addEventListener('scroll', updateCarouselButtons, { passive: true });
            window.addEventListener('resize', updateCarouselButtons);
            updateCarouselButtons();
        }

        if (!shopApp || !categoryNav || !categoryTrack) {
            return;
        }

        const chips = Array.from(categoryTrack.querySelectorAll('.shop-cat-chip[data-target]'));
        const sections = Array.from(document.querySelectorAll('[data-category-section]'));
        const activeFromServer = shopApp.getAttribute('data-active-category') || '';

        function setActiveChip(targetId) {
            chips.forEach(function (chip) {
                const target = chip.getAttribute('data-target') || '';
                chip.classList.toggle('is-active', target === targetId);
            });
        }

        function scrollChipIntoView(chip) {
            if (!chip || !categoryTrack) {
                return;
            }
            const trackRect = categoryTrack.getBoundingClientRect();
            const chipRect = chip.getBoundingClientRect();
            const offset = chipRect.left - trackRect.left - (trackRect.width / 2) + (chipRect.width / 2);
            categoryTrack.scrollBy({ left: offset, behavior: 'smooth' });
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function (e) {
                const targetId = chip.getAttribute('data-target') || '';
                if (!targetId || targetId === 'shopMenuTop') {
                    setActiveChip('shopMenuTop');
                    return;
                }

                const section = document.getElementById(targetId);
                if (!section) {
                    return;
                }

                e.preventDefault();
                const headerOffset = parseFloat(
                    getComputedStyle(document.documentElement).getPropertyValue('--site-header-sticky-height')
                ) || 72;
                const navHeight = categoryNav.offsetHeight + 12;
                const top = section.getBoundingClientRect().top + window.scrollY - navHeight - headerOffset;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                setActiveChip(targetId);
                scrollChipIntoView(chip);
            });
        });

        if (sections.length > 0 && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function (entries) {
                const visible = entries
                    .filter(function (entry) { return entry.isIntersecting; })
                    .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });

                if (visible.length === 0) {
                    return;
                }

                const id = visible[0].target.getAttribute('id');
                if (id) {
                    setActiveChip(id);
                    const activeChip = chips.find(function (chip) {
                        return chip.getAttribute('data-target') === id;
                    });
                    if (activeChip) {
                        scrollChipIntoView(activeChip);
                    }
                }
            }, {
                root: null,
                rootMargin: '-40% 0px -45% 0px',
                threshold: [0.1, 0.35, 0.6],
            });

            sections.forEach(function (section) {
                observer.observe(section);
            });
        }

        if (activeFromServer) {
            const targetChip = chips.find(function (chip) {
                return chip.getAttribute('data-target') === activeFromServer;
            });
            const targetSection = document.getElementById(activeFromServer);
            if (targetChip && targetSection) {
                setActiveChip(activeFromServer);
                scrollChipIntoView(targetChip);
                window.setTimeout(function () {
                    const headerOffset = parseFloat(
                        getComputedStyle(document.documentElement).getPropertyValue('--site-header-sticky-height')
                    ) || 72;
                    const navHeight = categoryNav.offsetHeight + 12;
                    const top = targetSection.getBoundingClientRect().top + window.scrollY - navHeight - headerOffset;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                }, 120);
            }
        }
    });
})();

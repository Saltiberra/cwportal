/**
 * CleanWatts Portal - Animation Controller
 * Uses Anime.js for smooth, professional animations
 * 
 * Features:
 * - Fade-in animations on scroll
 * - Counter animations for numbers
 * - Button ripple effects
 * - Tab transitions
 * - Staggered list animations
 */

(function () {
    'use strict';

    // Check if anime.js is loaded
    const animeAvailable = typeof anime !== 'undefined';

    // ============================================
    // INTERSECTION OBSERVER FOR SCROLL ANIMATIONS
    // ============================================

    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll('.anim-fade-up, .anim-slide-left, .anim-slide-right, .anim-scale-in, .section-heading');

        if (!animatedElements.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');

                    if (animeAvailable) {
                        anime({
                            targets: entry.target,
                            opacity: [0, 1],
                            translateY: entry.target.classList.contains('anim-fade-up') ? [30, 0] : [0, 0],
                            translateX: entry.target.classList.contains('anim-slide-left') ? [-40, 0] :
                                entry.target.classList.contains('anim-slide-right') ? [40, 0] : [0, 0],
                            scale: entry.target.classList.contains('anim-scale-in') ? [0.85, 1] : [1, 1],
                            duration: 600,
                            easing: 'easeOutQuart'
                        });
                    }

                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        animatedElements.forEach(el => observer.observe(el));
    }

    // ============================================
    // COUNTER ANIMATIONS
    // ============================================

    function animateCounter(element, endValue, duration = 1000) {
        if (!animeAvailable) {
            element.textContent = endValue;
            return;
        }

        const obj = { value: 0 };
        const isDecimal = String(endValue).includes('.') || endValue % 1 !== 0;

        anime({
            targets: obj,
            value: endValue,
            duration: duration,
            easing: 'easeOutExpo',
            round: isDecimal ? 100 : 1,
            update: function () {
                element.textContent = isDecimal ? obj.value.toFixed(2) : Math.round(obj.value);
            }
        });
    }

    function initCounterAnimations() {
        const counters = document.querySelectorAll('[data-counter]');

        if (!counters.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const endValue = parseFloat(target.dataset.counter);
                    const duration = parseInt(target.dataset.counterDuration) || 1200;

                    animateCounter(target, endValue, duration);
                    observer.unobserve(target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(el => observer.observe(el));
    }

    // ============================================
    // BUTTON RIPPLE EFFECT
    // ============================================

    function createRipple(event) {
        const button = event.currentTarget;

        // Remove existing ripples
        const existingRipple = button.querySelector('.ripple-effect');
        if (existingRipple) existingRipple.remove();

        const ripple = document.createElement('span');
        ripple.classList.add('ripple-effect');

        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);

        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';

        button.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);
    }

    function initButtonRipples() {
        document.querySelectorAll('.btn-primary, .btn-success, .btn-info, .btn-warning').forEach(btn => {
            btn.addEventListener('click', createRipple);
        });
    }

    // ============================================
    // STAGGERED LIST ANIMATIONS
    // ============================================

    function animateStaggeredList(container, itemSelector = '.stagger-item') {
        const items = container.querySelectorAll(itemSelector);

        if (!items.length || !animeAvailable) return;

        anime({
            targets: items,
            opacity: [0, 1],
            translateY: [20, 0],
            delay: anime.stagger(80),
            duration: 500,
            easing: 'easeOutQuart'
        });
    }

    // ============================================
    // CARD ENTRANCE ANIMATIONS
    // ============================================

    function animateCards() {
        const cards = document.querySelectorAll('.card:not(.animated-card)');

        if (!cards.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    card.classList.add('animated-card');

                    if (animeAvailable) {
                        anime({
                            targets: card,
                            opacity: [0, 1],
                            translateY: [40, 0],
                            duration: 600,
                            delay: index * 100,
                            easing: 'easeOutQuart'
                        });
                    } else {
                        card.style.opacity = 1;
                    }

                    observer.unobserve(card);
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(card => {
            card.style.opacity = 0;
            observer.observe(card);
        });
    }

    // ============================================
    // TAB TRANSITION ANIMATIONS
    // ============================================

    function initTabAnimations() {
        document.querySelectorAll('[data-bs-toggle="tab"], .nav-link[onclick*="switchTab"]').forEach(tab => {
            tab.addEventListener('click', function () {
                const targetId = this.dataset.bsTarget || this.getAttribute('data-bs-target');
                if (!targetId) return;

                const targetPane = document.querySelector(targetId);
                if (!targetPane || !animeAvailable) return;

                anime({
                    targets: targetPane,
                    opacity: [0, 1],
                    translateY: [15, 0],
                    duration: 400,
                    easing: 'easeOutQuart'
                });
            });
        });
    }

    // ============================================
    // NAVBAR SCROLL EFFECT
    // ============================================

    function initNavbarScroll() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;

        let lastScroll = 0;

        window.addEventListener('scroll', function () {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }

            lastScroll = currentScroll;
        }, { passive: true });
    }

    // ============================================
    // TABLE ROW ANIMATIONS
    // ============================================

    window.animateNewRow = function (row) {
        if (!row) return;

        row.classList.add('row-new');

        if (animeAvailable) {
            anime({
                targets: row,
                opacity: [0, 1],
                translateX: [-30, 0],
                backgroundColor: ['rgba(44, 204, 211, 0.2)', 'rgba(44, 204, 211, 0)'],
                duration: 600,
                easing: 'easeOutQuart'
            });
        }

        setTimeout(() => row.classList.remove('row-new'), 600);
    };

    window.animateRemoveRow = function (row, callback) {
        if (!row) {
            if (callback) callback();
            return;
        }

        row.classList.add('row-exit');

        if (animeAvailable) {
            anime({
                targets: row,
                opacity: 0,
                translateX: 30,
                duration: 400,
                easing: 'easeInQuart',
                complete: function () {
                    if (callback) callback();
                }
            });
        } else {
            setTimeout(callback, 400);
        }
    };

    // ============================================
    // SUCCESS/ERROR FEEDBACK
    // ============================================

    window.flashSuccess = function (element) {
        if (!element) return;
        element.classList.add('flash-success');
        setTimeout(() => element.classList.remove('flash-success'), 600);
    };

    window.shakeError = function (element) {
        if (!element) return;

        if (animeAvailable) {
            anime({
                targets: element,
                translateX: [0, -8, 8, -8, 8, -4, 4, 0],
                duration: 500,
                easing: 'easeInOutQuad'
            });
        } else {
            element.classList.add('shake-error');
            setTimeout(() => element.classList.remove('shake-error'), 500);
        }
    };

    // ============================================
    // LOADING ANIMATIONS
    // ============================================

    window.showSkeleton = function (container, count = 3, height = 40) {
        container.innerHTML = '';
        for (let i = 0; i < count; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton mb-2';
            skeleton.style.height = height + 'px';
            container.appendChild(skeleton);
        }
    };

    // ============================================
    // MODAL ENHANCEMENTS
    // ============================================

    function initModalAnimations() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function () {
                if (!animeAvailable) return;

                const modalContent = this.querySelector('.modal-content');
                anime({
                    targets: modalContent,
                    scale: [0.9, 1],
                    opacity: [0, 1],
                    duration: 300,
                    easing: 'easeOutQuart'
                });
            });
        });
    }

    // ============================================
    // TOOLTIP ANIMATIONS (if using Bootstrap tooltips)
    // ============================================

    function initTooltipAnimations() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(el => {
            new bootstrap.Tooltip(el, {
                animation: true
            });
        });
    }

    // ============================================
    // PAGE LOAD ANIMATION
    // ============================================

    function initPageLoadAnimation() {
        document.body.style.opacity = '0';

        window.addEventListener('load', function () {
            if (animeAvailable) {
                anime({
                    targets: 'body',
                    opacity: [0, 1],
                    duration: 400,
                    easing: 'easeOutQuart'
                });
            } else {
                document.body.style.opacity = '1';
            }
        });
    }

    // ============================================
    // INITIALIZE ALL ANIMATIONS
    // ============================================

    function init() {
        // Don't run page load animation - can be jarring
        // initPageLoadAnimation();

        initScrollAnimations();
        initCounterAnimations();
        initButtonRipples();
        initTabAnimations();
        initNavbarScroll();
        initModalAnimations();

        // Animate cards with slight delay
        setTimeout(animateCards, 100);

        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            try { initTooltipAnimations(); } catch (e) { }
        }

        console.log('✨ CleanWatts Animations initialized' + (animeAvailable ? ' (with Anime.js)' : ' (CSS fallback)'));
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose utility functions globally
    window.CWAnimations = {
        animateCounter: animateCounter,
        animateStaggeredList: animateStaggeredList,
        flashSuccess: window.flashSuccess,
        shakeError: window.shakeError,
        animateNewRow: window.animateNewRow,
        animateRemoveRow: window.animateRemoveRow
    };

})();

/**
 * CleanWatts Portal - GSAP Animation Controller
 * Professional, impactful animations using GSAP
 * 
 * Features:
 * - Bounce/Elastic card entries
 * - Staggered list animations
 * - Page transitions
 * - Scroll-triggered animations
 * - Magnetic buttons
 * - Text reveals
 */

(function () {
    'use strict';

    // Check if GSAP is loaded
    if (typeof gsap === 'undefined') {
        console.warn('GSAP not loaded. Animations disabled.');
        return;
    }

    // Register ScrollTrigger if available
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
    }

    // ============================================
    // CONFIGURATION
    // ============================================
    const config = {
        // Animation durations
        duration: {
            fast: 0.3,
            normal: 0.6,
            slow: 1.0,
            bounce: 1.2
        },
        // Easing presets
        ease: {
            bounce: 'elastic.out(1, 0.5)',
            elastic: 'elastic.out(1, 0.3)',
            smooth: 'power3.out',
            back: 'back.out(1.7)',
            expo: 'expo.out'
        },
        // Stagger settings
        stagger: {
            fast: 0.05,
            normal: 0.1,
            slow: 0.15
        }
    };

    // ============================================
    // PAGE LOAD ANIMATIONS
    // ============================================
    function initPageLoad() {
        // Hide elements initially
        gsap.set('.gsap-fade-up', { opacity: 0, y: 60 });
        gsap.set('.gsap-fade-left', { opacity: 0, x: -60 });
        gsap.set('.gsap-fade-right', { opacity: 0, x: 60 });
        gsap.set('.gsap-scale-in', { opacity: 0, scale: 0.8 });
        gsap.set('.gsap-bounce-in', { opacity: 0, scale: 0, y: 30 });

        // Animate cards with bounce effect
        const cards = document.querySelectorAll('.card.gsap-bounce-in, .gsap-bounce-in');
        if (cards.length) {
            gsap.to(cards, {
                opacity: 1,
                scale: 1,
                y: 0,
                duration: config.duration.bounce,
                stagger: config.stagger.normal,
                ease: config.ease.elastic,
                delay: 0.2
            });
        }

        // Animate fade-up elements
        const fadeUpElements = document.querySelectorAll('.gsap-fade-up');
        if (fadeUpElements.length) {
            gsap.to(fadeUpElements, {
                opacity: 1,
                y: 0,
                duration: config.duration.normal,
                stagger: config.stagger.normal,
                ease: config.ease.smooth,
                delay: 0.1
            });
        }

        // Animate fade-left elements
        const fadeLeftElements = document.querySelectorAll('.gsap-fade-left');
        if (fadeLeftElements.length) {
            gsap.to(fadeLeftElements, {
                opacity: 1,
                x: 0,
                duration: config.duration.normal,
                stagger: config.stagger.normal,
                ease: config.ease.back,
                delay: 0.15
            });
        }

        // Animate fade-right elements
        const fadeRightElements = document.querySelectorAll('.gsap-fade-right');
        if (fadeRightElements.length) {
            gsap.to(fadeRightElements, {
                opacity: 1,
                x: 0,
                duration: config.duration.normal,
                stagger: config.stagger.normal,
                ease: config.ease.back,
                delay: 0.15
            });
        }

        // Animate scale-in elements
        const scaleInElements = document.querySelectorAll('.gsap-scale-in');
        if (scaleInElements.length) {
            gsap.to(scaleInElements, {
                opacity: 1,
                scale: 1,
                duration: config.duration.normal,
                stagger: config.stagger.fast,
                ease: config.ease.back,
                delay: 0.2
            });
        }
    }

    // ============================================
    // SCROLL-TRIGGERED ANIMATIONS
    // ============================================
    function initScrollAnimations() {
        if (typeof ScrollTrigger === 'undefined') return;

        // Cards that appear on scroll with bounce
        const scrollCards = document.querySelectorAll('.gsap-scroll-bounce');
        scrollCards.forEach(card => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 85%',
                    toggleActions: 'play none none none'
                },
                opacity: 0,
                scale: 0.8,
                y: 50,
                duration: config.duration.bounce,
                ease: config.ease.elastic
            });
        });

        // Staggered list items on scroll
        const scrollLists = document.querySelectorAll('.gsap-scroll-stagger');
        scrollLists.forEach(list => {
            const items = list.querySelectorAll('li, .list-item, tr, .stagger-item');
            if (items.length) {
                gsap.from(items, {
                    scrollTrigger: {
                        trigger: list,
                        start: 'top 80%',
                        toggleActions: 'play none none none'
                    },
                    opacity: 0,
                    x: -30,
                    duration: config.duration.normal,
                    stagger: config.stagger.fast,
                    ease: config.ease.smooth
                });
            }
        });

        // Fade up sections
        const scrollFadeUp = document.querySelectorAll('.gsap-scroll-fade');
        scrollFadeUp.forEach(el => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el,
                    start: 'top 85%',
                    toggleActions: 'play none none none'
                },
                opacity: 0,
                y: 40,
                duration: config.duration.normal,
                ease: config.ease.smooth
            });
        });
    }

    // ============================================
    // INTERACTIVE CARD ANIMATIONS
    // ============================================
    function initCardHoverEffects() {
        const cards = document.querySelectorAll('.card-hover-lift, .card');

        cards.forEach(card => {
            // Skip cards that opt-out
            if (card.classList.contains('no-hover-effect')) return;

            card.addEventListener('mouseenter', () => {
                gsap.to(card, {
                    y: -8,
                    scale: 1.02,
                    boxShadow: '0 20px 40px rgba(0, 0, 0, 0.15)',
                    duration: config.duration.fast,
                    ease: config.ease.smooth
                });
            });

            card.addEventListener('mouseleave', () => {
                gsap.to(card, {
                    y: 0,
                    scale: 1,
                    boxShadow: '0 0.5rem 1rem rgba(0, 0, 0, 0.15)',
                    duration: config.duration.fast,
                    ease: config.ease.smooth
                });
            });
        });
    }

    // ============================================
    // MAGNETIC BUTTON EFFECT
    // ============================================
    function initMagneticButtons() {
        const buttons = document.querySelectorAll('.btn-magnetic, .btn-primary, .btn-success');

        buttons.forEach(btn => {
            btn.addEventListener('mousemove', (e) => {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;

                gsap.to(btn, {
                    x: x * 0.2,
                    y: y * 0.2,
                    duration: config.duration.fast,
                    ease: 'power2.out'
                });
            });

            btn.addEventListener('mouseleave', () => {
                gsap.to(btn, {
                    x: 0,
                    y: 0,
                    duration: config.duration.normal,
                    ease: config.ease.elastic
                });
            });
        });
    }

    // ============================================
    // TEXT REVEAL ANIMATION
    // ============================================
    function initTextReveal() {
        const textElements = document.querySelectorAll('.gsap-text-reveal');

        textElements.forEach(el => {
            const text = el.textContent;
            el.innerHTML = '';

            // Split into words
            const words = text.split(' ');
            words.forEach((word, i) => {
                const span = document.createElement('span');
                span.textContent = word + (i < words.length - 1 ? ' ' : '');
                span.style.display = 'inline-block';
                span.style.opacity = '0';
                span.style.transform = 'translateY(20px)';
                el.appendChild(span);
            });

            // Animate on scroll
            if (typeof ScrollTrigger !== 'undefined') {
                const spans = el.querySelectorAll('span');
                gsap.to(spans, {
                    scrollTrigger: {
                        trigger: el,
                        start: 'top 80%',
                        toggleActions: 'play none none none'
                    },
                    opacity: 1,
                    y: 0,
                    duration: config.duration.normal,
                    stagger: 0.05,
                    ease: config.ease.back
                });
            }
        });
    }

    // ============================================
    // COUNTER ANIMATION (GSAP VERSION)
    // ============================================
    function animateCounter(element, endValue, duration = 2) {
        const obj = { value: 0 };
        const isDecimal = String(endValue).includes('.') || endValue % 1 !== 0;
        const suffix = element.dataset.suffix || '';
        const prefix = element.dataset.prefix || '';

        gsap.to(obj, {
            value: endValue,
            duration: duration,
            ease: 'power2.out',
            onUpdate: function () {
                const displayValue = isDecimal ? obj.value.toFixed(2) : Math.round(obj.value);
                element.textContent = prefix + displayValue.toLocaleString() + suffix;
            }
        });
    }

    function initCounterAnimations() {
        const counters = document.querySelectorAll('[data-gsap-counter]');

        counters.forEach(counter => {
            const endValue = parseFloat(counter.dataset.gsapCounter);
            const duration = parseFloat(counter.dataset.counterDuration) || 2;

            if (typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.create({
                    trigger: counter,
                    start: 'top 80%',
                    once: true,
                    onEnter: () => animateCounter(counter, endValue, duration)
                });
            } else {
                // Fallback without ScrollTrigger
                setTimeout(() => animateCounter(counter, endValue, duration), 500);
            }
        });
    }

    // ============================================
    // TABLE ROW ANIMATIONS
    // ============================================
    function animateNewTableRow(row) {
        gsap.from(row, {
            opacity: 0,
            x: -50,
            backgroundColor: 'rgba(44, 204, 211, 0.3)',
            duration: config.duration.normal,
            ease: config.ease.back,
            onComplete: () => {
                gsap.to(row, {
                    backgroundColor: 'transparent',
                    duration: 1,
                    delay: 0.5
                });
            }
        });
    }

    function animateRemoveTableRow(row, callback) {
        gsap.to(row, {
            opacity: 0,
            x: 50,
            height: 0,
            padding: 0,
            duration: config.duration.fast,
            ease: 'power2.in',
            onComplete: () => {
                if (callback) callback();
            }
        });
    }

    // ============================================
    // MODAL ANIMATIONS
    // ============================================
    function initModalAnimations() {
        document.addEventListener('show.bs.modal', (e) => {
            const modal = e.target;
            const dialog = modal.querySelector('.modal-dialog');

            if (dialog) {
                gsap.fromTo(dialog,
                    {
                        opacity: 0,
                        scale: 0.8,
                        y: -50
                    },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: config.duration.normal,
                        ease: config.ease.back
                    }
                );
            }
        });

        document.addEventListener('hide.bs.modal', (e) => {
            const modal = e.target;
            const dialog = modal.querySelector('.modal-dialog');

            if (dialog) {
                gsap.to(dialog, {
                    opacity: 0,
                    scale: 0.9,
                    y: -30,
                    duration: config.duration.fast,
                    ease: 'power2.in'
                });
            }
        });
    }

    // ============================================
    // SUCCESS/ERROR FEEDBACK ANIMATIONS
    // ============================================
    function flashSuccess(element) {
        const tl = gsap.timeline();
        tl.to(element, {
            scale: 1.05,
            boxShadow: '0 0 30px rgba(44, 204, 211, 0.6)',
            duration: 0.2,
            ease: 'power2.out'
        })
            .to(element, {
                scale: 1,
                boxShadow: '0 0.5rem 1rem rgba(0, 0, 0, 0.15)',
                duration: 0.4,
                ease: config.ease.elastic
            });
    }

    function shakeError(element) {
        gsap.to(element, {
            x: [-10, 10, -8, 8, -5, 5, 0],
            duration: 0.5,
            ease: 'power2.out'
        });

        const tl = gsap.timeline();
        tl.to(element, {
            boxShadow: '0 0 20px rgba(220, 53, 69, 0.5)',
            borderColor: '#dc3545',
            duration: 0.2
        })
            .to(element, {
                boxShadow: '0 0.5rem 1rem rgba(0, 0, 0, 0.15)',
                borderColor: '',
                duration: 0.5,
                delay: 0.5
            });
    }

    // ============================================
    // PULSE ANIMATION
    // ============================================
    function pulseElement(element, times = 3) {
        gsap.to(element, {
            scale: 1.1,
            duration: 0.3,
            repeat: times * 2 - 1,
            yoyo: true,
            ease: 'power2.inOut'
        });
    }

    // ============================================
    // NAVBAR SCROLL EFFECT
    // ============================================
    function initNavbarScroll() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;

        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                gsap.to(navbar, {
                    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.15)',
                    duration: 0.3
                });
            } else {
                gsap.to(navbar, {
                    boxShadow: 'none',
                    duration: 0.3
                });
            }

            // Hide/show on scroll (optional - uncomment if wanted)
            /*
            if (currentScroll > lastScroll && currentScroll > 100) {
                gsap.to(navbar, { y: -100, duration: 0.3 });
            } else {
                gsap.to(navbar, { y: 0, duration: 0.3 });
            }
            */

            lastScroll = currentScroll;
        });
    }

    // ============================================
    // TAB TRANSITION ANIMATIONS
    // ============================================
    function initTabAnimations() {
        document.addEventListener('shown.bs.tab', (e) => {
            const targetPane = document.querySelector(e.target.dataset.bsTarget || e.target.getAttribute('href'));
            if (!targetPane) return;

            // Animate tab content
            gsap.fromTo(targetPane,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.4, ease: config.ease.smooth }
            );

            // Stagger child elements
            const children = targetPane.querySelectorAll('.card, .form-group, .mb-3, .row > div');
            if (children.length) {
                gsap.fromTo(children,
                    { opacity: 0, y: 15 },
                    {
                        opacity: 1,
                        y: 0,
                        duration: 0.4,
                        stagger: 0.05,
                        ease: config.ease.smooth,
                        delay: 0.1
                    }
                );
            }
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runInit);
        } else {
            runInit();
        }
    }

    function runInit() {
        initPageLoad();
        initScrollAnimations();
        initCardHoverEffects();
        initMagneticButtons();
        initTextReveal();
        initCounterAnimations();
        initModalAnimations();
        initNavbarScroll();
        initTabAnimations();
    }

    init();

    // ============================================
    // PUBLIC API
    // ============================================
    window.CWGsap = {
        // Animate a new element in
        animateIn: function (element, type = 'bounce') {
            switch (type) {
                case 'bounce':
                    gsap.from(element, {
                        opacity: 0,
                        scale: 0.5,
                        y: 30,
                        duration: config.duration.bounce,
                        ease: config.ease.elastic
                    });
                    break;
                case 'fade':
                    gsap.from(element, {
                        opacity: 0,
                        y: 30,
                        duration: config.duration.normal,
                        ease: config.ease.smooth
                    });
                    break;
                case 'slide':
                    gsap.from(element, {
                        opacity: 0,
                        x: -50,
                        duration: config.duration.normal,
                        ease: config.ease.back
                    });
                    break;
            }
        },

        // Animate element out
        animateOut: function (element, callback) {
            gsap.to(element, {
                opacity: 0,
                scale: 0.8,
                duration: config.duration.fast,
                ease: 'power2.in',
                onComplete: callback
            });
        },

        // Table row animations
        newRow: animateNewTableRow,
        removeRow: animateRemoveTableRow,

        // Feedback animations
        success: flashSuccess,
        error: shakeError,
        pulse: pulseElement,

        // Counter animation
        counter: animateCounter,

        // Stagger animation for list
        staggerIn: function (elements, options = {}) {
            gsap.from(elements, {
                opacity: 0,
                y: options.y || 20,
                x: options.x || 0,
                duration: options.duration || config.duration.normal,
                stagger: options.stagger || config.stagger.normal,
                ease: options.ease || config.ease.smooth,
                delay: options.delay || 0
            });
        },

        // Timeline helper
        timeline: () => gsap.timeline(),

        // Direct GSAP access
        gsap: gsap
    };

})();

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);

/**
 * Site header: condenses after scrolling and hosts the mobile menu.
 */
Alpine.data('siteHeader', () => ({
    scrolled: false,
    mobileOpen: false,

    init() {
        const update = () => (this.scrolled = window.scrollY > 24);
        update();
        window.addEventListener('scroll', update, { passive: true });

        this.$watch('mobileOpen', (open) => document.documentElement.classList.toggle('overflow-hidden', open));
    },
}));

/**
 * Desktop dropdown that opens on hover and on keyboard focus/click.
 */
Alpine.data('dropdown', () => ({
    open: false,
    timer: null,

    show() {
        clearTimeout(this.timer);
        this.open = true;
    },

    hide(delay = 120) {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (this.open = false), delay);
    },

    toggle() {
        this.open ? this.hide(0) : this.show();
    },
}));

window.Alpine = Alpine;
Alpine.start();

/**
 * Reveal-on-scroll for elements marked with [data-reveal].
 * Skipped entirely for visitors who prefer reduced motion.
 */
const revealTargets = document.querySelectorAll('[data-reveal]');

if (revealTargets.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 },
    );

    revealTargets.forEach((el) => {
        el.classList.add('reveal');
        observer.observe(el);
    });
}

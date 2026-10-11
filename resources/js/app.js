import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import intersect from '@alpinejs/intersect';

Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.plugin(intersect);

/**
 * Public forms (contact, campaign leads): submit without reloading the page,
 * show field errors inline, and add a reCAPTCHA v3 token when configured.
 * The script for reCAPTCHA only loads once the visitor starts filling the form.
 */
Alpine.data('siteForm', (recaptchaKey = null) => ({
    busy: false,
    done: false,
    errors: {},
    message: '',

    loadRecaptcha() {
        if (!recaptchaKey || window.grecaptcha || document.getElementById('recaptcha-js')) return;
        const script = document.createElement('script');
        script.id = 'recaptcha-js';
        script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(recaptchaKey)}`;
        script.async = true;
        document.head.appendChild(script);
    },

    async token() {
        if (!recaptchaKey) return null;
        this.loadRecaptcha();

        for (let i = 0; i < 50 && !(window.grecaptcha && window.grecaptcha.execute); i++) {
            await new Promise((r) => setTimeout(r, 100));
        }
        if (!window.grecaptcha) return null;

        return new Promise((resolve) => window.grecaptcha.ready(() => window.grecaptcha.execute(recaptchaKey, { action: 'submit' }).then(resolve, () => resolve(null))));
    },

    async submit(form) {
        if (this.busy) return;
        this.busy = true;
        this.errors = {};
        this.message = '';

        try {
            const body = new FormData(form);
            const token = await this.token();
            if (token) body.set('recaptcha_token', token);

            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                this.done = true;
                form.closest('[id^="form-"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.dataLayer?.push({ event: 'form_submit', form_id: form.action });
                window.fbq?.('track', 'Lead');
            } else if (response.status === 422) {
                this.errors = data.errors ?? {};
                this.message = data.errors ? '' : data.message;
                this.$nextTick(() => form.querySelector('[aria-invalid="true"]')?.focus());
            } else if (response.status === 429) {
                this.message = form.dataset.tooMany || 'Too many attempts. Please wait a minute and try again.';
            } else {
                this.message = data.message || 'Something went wrong. Please try again or call us.';
            }
        } catch {
            this.message = 'Network error. Please check your connection and try again.';
        } finally {
            this.busy = false;
        }
    },
}));

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

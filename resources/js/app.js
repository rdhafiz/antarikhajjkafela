import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import intersect from '@alpinejs/intersect';

Alpine.plugin(collapse);
Alpine.plugin(focus);
Alpine.plugin(intersect);

/**
 * Hero parallax: as the page scrolls, the background photo ([data-parallax-bg])
 * moves at half speed and slowly zooms, while the text ([data-parallax-content])
 * drifts down and fades out. Transform and opacity only, one rAF per frame,
 * softer on phones and off for visitors who prefer reduced motion.
 */
function heroParallax() {
    const heroes = [...document.querySelectorAll('[data-hero]')];
    if (!heroes.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const phone = window.matchMedia('(max-width: 767px)');
    const parts = heroes.map((hero) => ({
        hero,
        bg: hero.querySelectorAll('[data-parallax-bg]'),
        content: hero.querySelectorAll('[data-parallax-content]'),
    }));
    let queued = false;

    const update = () => {
        queued = false;
        const scrolled = window.scrollY;
        const strength = phone.matches ? 0.6 : 1;

        for (const { hero, bg, content } of parts) {
            const height = hero.offsetHeight;
            // Heroes open the page, so scroll distance equals progress through them.
            if (scrolled > hero.offsetTop + height) continue;

            const progress = Math.min(1, scrolled / height);
            bg.forEach((el) => {
                el.style.transform = `translate3d(0, ${(scrolled * 0.5 * strength).toFixed(1)}px, 0) scale(${(1 + progress * 0.12).toFixed(3)})`;
            });
            content.forEach((el) => {
                el.style.transform = `translate3d(0, ${(scrolled * 0.32 * strength).toFixed(1)}px, 0)`;
                el.style.opacity = Math.max(0, 1 - progress * 1.8).toFixed(3);
            });
        }
    };

    const queue = () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(update);
        }
    };

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue, { passive: true });
    update();
}

heroParallax();

/**
 * Album lightbox: photos and YouTube videos with keyboard, button and swipe
 * navigation. Neighbouring photos are preloaded for instant paging.
 */
Alpine.data('lightbox', (slides = []) => ({
    slides,
    current: null,
    startX: null,

    get slide() {
        return this.current === null ? null : this.slides[this.current];
    },

    get position() {
        return this.current === null ? '' : `${this.current + 1} / ${this.slides.length}`;
    },

    open(index) {
        this.current = index;
        this.preload();
    },

    close() {
        this.current = null;
    },

    go(step) {
        if (this.current === null || this.slides.length < 2) return;
        this.current = (this.current + step + this.slides.length) % this.slides.length;
        this.preload();
    },

    next() {
        this.go(document.dir === 'rtl' ? -1 : 1);
    },

    prev() {
        this.go(document.dir === 'rtl' ? 1 : -1);
    },

    preload() {
        [1, -1].forEach((step) => {
            const slide = this.slides[(this.current + step + this.slides.length) % this.slides.length];
            if (slide?.type === 'image') new Image().src = slide.src;
        });
    },

    key(event) {
        if (this.current === null) return;
        if (event.key === 'Escape') this.close();
        if (event.key === 'ArrowRight') this.next();
        if (event.key === 'ArrowLeft') this.prev();
    },

    touchStart(event) {
        this.startX = event.changedTouches[0].clientX;
    },

    touchEnd(event) {
        if (this.startX === null) return;
        const distance = event.changedTouches[0].clientX - this.startX;
        this.startX = null;
        if (Math.abs(distance) > 50) distance < 0 ? this.next() : this.prev();
    },
}));

/**
 * reCAPTCHA v3, loaded only once a visitor starts filling a form.
 */
function loadRecaptcha(key) {
    if (!key || window.grecaptcha || document.getElementById('recaptcha-js')) return;
    const script = document.createElement('script');
    script.id = 'recaptcha-js';
    script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(key)}`;
    script.async = true;
    document.head.appendChild(script);
}

async function recaptchaToken(key) {
    if (!key) return null;
    loadRecaptcha(key);

    for (let i = 0; i < 50 && !(window.grecaptcha && window.grecaptcha.execute); i++) {
        await new Promise((r) => setTimeout(r, 100));
    }
    if (!window.grecaptcha) return null;

    return new Promise((resolve) => window.grecaptcha.ready(() => window.grecaptcha.execute(key, { action: 'submit' }).then(resolve, () => resolve(null))));
}

/**
 * Booking form: live price summary (the server recalculates everything)
 * and a reCAPTCHA token before the normal form post.
 */
Alpine.data('bookingForm', (config) => ({
    ...config,
    busy: false,
    verified: false,

    get selected() {
        return this.options.find((o) => o.id === this.option) ?? this.options[0];
    },

    get quantity() {
        if (this.selected.unit === 'group') return 1;
        return Math.max(1, Number(this.selected.unit === 'room' ? this.rooms : this.travelers) || 1);
    },

    get total() {
        return Math.round(this.selected.price * this.quantity * 100) / 100;
    },

    get advanceAmount() {
        const travelers = Math.max(1, Number(this.travelers) || 1);
        const amount = this.advance.type === 'fixed'
            ? this.advance.value * travelers
            : this.advance.type === 'percentage' ? (this.total * this.advance.value) / 100 : 0;
        return Math.min(Math.round(amount * 100) / 100, this.total);
    },

    number() {
        return new Intl.NumberFormat(this.locale === 'bn' ? 'bn-BD' : 'en-IN', { maximumFractionDigits: 2 });
    },

    money(value) {
        return '৳' + this.number().format(value);
    },

    digits(value) {
        return this.number().format(value);
    },

    loadRecaptcha() {
        loadRecaptcha(this.recaptcha);
    },

    async submit(event) {
        if (this.verified) return;
        event.preventDefault();
        if (this.busy) return;
        this.busy = true;

        const token = await recaptchaToken(this.recaptcha);
        if (token) this.$refs.token.value = token;
        this.verified = true;
        event.target.submit();
    },
}));

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
        loadRecaptcha(recaptchaKey);
    },

    token() {
        return recaptchaToken(recaptchaKey);
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

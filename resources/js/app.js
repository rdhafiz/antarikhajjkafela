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
    // Where the menu portal grows from: the centre of the button that opened it.
    origin: '100% 0%',

    init() {
        const update = () => (this.scrolled = window.scrollY > 24);
        update();
        window.addEventListener('scroll', update, { passive: true });

        this.$watch('mobileOpen', (open) => document.documentElement.classList.toggle('overflow-hidden', open));
    },

    openMenu(button) {
        const r = button.getBoundingClientRect();
        this.origin = `${r.left + r.width / 2}px ${r.top + r.height / 2}px`;
        this.mobileOpen = true;
    },

    closeMenu() {
        if (!this.mobileOpen) return;
        this.mobileOpen = false;
        this.$refs.menuButton?.focus({ preventScroll: true });
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

/**
 * Process stepper: tabs beside a stage. While on screen it moves to the next
 * step every few seconds (--t on the root fills the active step's rail).
 * Hover or focus pauses it; a click, or reduced motion, stops it for good.
 */
Alpine.data('journey', (count, duration = 6500) => ({
    active: 0,
    auto: count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    held: false,
    visible: false,
    t: 0,

    init() {
        this.paint();
        if (!this.auto) return;

        new IntersectionObserver(([entry]) => (this.visible = entry.isIntersecting), { threshold: 0.4 }).observe(this.$el);

        let last = performance.now();
        const tick = (now) => {
            const dt = Math.min(now - last, 100);
            last = now;
            if (!this.auto) return this.paint();
            if (this.visible && !this.held && !document.hidden) {
                this.t += dt / duration;
                if (this.t >= 1) this.go((this.active + 1) % count);
                this.paint();
            }
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    },

    paint() {
        this.$el.style.setProperty('--t', this.auto ? this.t.toFixed(4) : 1);
    },

    go(index) {
        this.active = index;
        this.t = 0;
    },

    select(index) {
        this.auto = false;
        this.go(index);
        this.paint();
    },

    hold(on) {
        this.held = on;
    },

    keys(event) {
        const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        const rtl = document.documentElement.dir === 'rtl' && ['ArrowRight', 'ArrowLeft'].includes(event.key);
        let next = null;
        if (step) next = (this.active + (rtl ? -step : step) + count) % count;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = count - 1;
        if (next === null) return;
        event.preventDefault();
        this.select(next);
        this.$nextTick(() => this.$el.querySelectorAll('[role="tab"]')[next]?.focus());
    },
}));

window.Alpine = Alpine;
Alpine.start();

/**
 * Scroll motion for the page sections. Skipped for visitors who prefer
 * reduced motion; without JavaScript everything simply stays visible.
 *
 * [data-reveal]            Reveals when it enters the screen and resets when it
 *                          leaves, in both directions: it comes up from below
 *                          while scrolling down and down from above while
 *                          scrolling up. Optional value: up (default), left,
 *                          right, scale or wipe. --reveal-delay staggers items.
 * [data-parallax="0.1"]    Moves by speed × its distance from the screen centre
 *                          (negative values move against the scroll). Uses the
 *                          CSS translate property, so it combines with reveals.
 * [data-scroll-progress]   Gets --progress from 0 to 1 while it crosses the screen.
 */
function scrollMotion() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

    const reveals = document.querySelectorAll('[data-reveal]');
    const revealer = new IntersectionObserver(
        (entries) => {
            for (const { target, isIntersecting, intersectionRatio, boundingClientRect } of entries) {
                if (isIntersecting && intersectionRatio >= 0.12) {
                    target.classList.add('is-revealed');
                } else if (!isIntersecting) {
                    // Remember which side it left from, so it re-enters from there.
                    target.classList.remove('is-revealed');
                    target.dataset.from = boundingClientRect.top < 0 ? 'above' : 'below';
                }
            }
        },
        { threshold: [0, 0.12] },
    );

    reveals.forEach((el) => {
        el.classList.add('reveal');
        el.dataset.from = 'below';
        revealer.observe(el);
    });

    const movers = [...document.querySelectorAll('[data-parallax], [data-scroll-progress]')];
    if (!movers.length) return;

    const active = new Set();
    const watcher = new IntersectionObserver(
        (entries) => {
            entries.forEach(({ target, isIntersecting }) => (isIntersecting ? active.add(target) : active.delete(target)));
            queue();
        },
        { rootMargin: '25% 0px' },
    );
    movers.forEach((el) => watcher.observe(el));

    const phone = window.matchMedia('(max-width: 767px)');
    let queued = false;

    const update = () => {
        queued = false;
        const vh = window.innerHeight;
        const scale = phone.matches ? 0.5 : 1;

        for (const el of active) {
            const rect = el.getBoundingClientRect();

            if (el.dataset.parallax !== undefined) {
                const offset = (rect.top + rect.height / 2 - vh / 2) * parseFloat(el.dataset.parallax || '0.1') * scale;
                el.style.translate = `0 ${(-offset).toFixed(1)}px`;
            }

            if (el.dataset.scrollProgress !== undefined) {
                const progress = Math.min(1, Math.max(0, (vh * 0.85 - rect.top) / (rect.height + vh * 0.35)));
                el.style.setProperty('--progress', progress.toFixed(3));
            }
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
}

scrollMotion();

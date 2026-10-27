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

    // A blocked or stalled reCAPTCHA must not hold the form: after 10 seconds send without a token (the server decides).
    const token = new Promise((resolve) => window.grecaptcha.ready(() => window.grecaptcha.execute(key, { action: 'submit' }).then(resolve, () => resolve(null))));
    return Promise.race([token, new Promise((resolve) => setTimeout(() => resolve(null), 10000))]);
}

/** Phone and email checks shared by the public forms (the server checks again, more strictly for phones). */
const validators = {
    // A Bangladeshi mobile (01XXXXXXXXX, optionally +88/88 in front) or an international number with its + country code.
    phone: (value) => {
        const v = value.replace(/[\s\-().]/g, '');
        return /^(?:\+?88)?01[3-9]\d{8}$/.test(v) || /^\+(?!88)[1-9]\d{7,14}$/.test(v);
    },
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@.]{2,}$/.test(value.trim()),
};

/**
 * Booking form: live price summary (the server recalculates everything),
 * checks as the visitor goes, and a background submit with a reCAPTCHA token.
 */
Alpine.data('bookingForm', (config) => ({
    ...config,
    busy: false,
    progress: null,
    formError: '',

    get busyLabel() {
        return this.progress !== null && this.progress < 100
            ? this.messages.uploading.replace(':percent', this.progress)
            : this.messages.sending;
    },

    get selected() {
        return this.options.find((o) => o.id === this.option) ?? this.options[0];
    },

    /** A count as the server will take it: a whole number from 1 to the maximum (the summary never shows more). */
    count(value) {
        return Math.min(this.maxTravelers, Math.max(1, Math.floor(Number(value)) || 1));
    },

    get quantity() {
        if (this.selected.unit === 'group') return 1;
        return this.count(this.selected.unit === 'room' ? this.rooms : this.travelers);
    },

    get total() {
        return Math.round(this.selected.price * this.quantity * 100) / 100;
    },

    /** The chosen travel date for the summary, e.g. "November 2026". */
    get travelDate() {
        const year = this.yearLabels?.[this.travelYear] ?? '';
        if (this.travelMode === 'hajj_year') return year;
        const month = this.monthNames?.[this.travelMonth];
        return month && year ? `${month} ${year}` : '';
    },

    get advanceAmount() {
        const travelers = this.count(this.travelers);
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

    /**
     * Check the fields before sending, with a message under each field in the
     * page's language (the form is `novalidate`, so the browser shows none of
     * its own). The server checks everything again.
     */
    check(form) {
        form.querySelectorAll('.error-client').forEach((el) => el.remove());
        let first = null;

        for (const el of form.elements) {
            if (el.dataset.clientInvalid) this.unflag(el);
            this.validate(el);
            if (!el.willValidate || el.checkValidity()) continue;
            // A radio group gets one message.
            if (el.type === 'radio' && form.querySelector(`.error-client[data-for="${el.name}"]`)) continue;
            this.flag(el);
            first ??= el;
        }

        if (!first) return true;
        first.closest('[data-field]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.focus({ preventScroll: true });
        return false;
    },

    /** Phone and email fields (data-validate) get a custom message when the value does not look right. */
    validate(el) {
        const kind = el.dataset?.validate;
        if (!kind || !validators[kind]) return;
        el.setCustomValidity(el.value.trim() && !validators[kind](el.value) ? this.messages[kind] : '');
    },

    flag(el, message = null) {
        const box = el.closest('[data-field]');
        const v = el.validity;
        const choice = el.type === 'radio' || el.type === 'checkbox';
        const text = v.valueMissing
            ? (choice ? (el.dataset.message ?? this.messages.choose) : (el.dataset.requiredMessage ?? this.messages.required))
            : v.customError ? el.validationMessage
            : (v.rangeOverflow || v.rangeUnderflow || v.badInput || v.stepMismatch) && el.dataset.rangeMessage ? el.dataset.rangeMessage
            : v.typeMismatch && el.type === 'email' ? this.messages.email : (el.dataset.message ?? this.messages.invalid);
        const id = `${el.id || el.name}-client-error`;
        box?.querySelector(`#${CSS.escape(id)}`)?.remove();

        el.setAttribute('aria-invalid', 'true');
        el.setAttribute('aria-describedby', id);
        el.dataset.clientInvalid = '1';
        if (!box) return;

        const p = document.createElement('p');
        p.className = 'error-site error-client';
        p.id = id;
        p.dataset.for = el.name;
        p.textContent = message ?? text;
        p.setAttribute('role', 'alert');
        box.append(p);
    },

    unflag(el) {
        el.removeAttribute('aria-invalid');
        el.removeAttribute('aria-describedby');
        delete el.dataset.clientInvalid;
    },

    /**
     * Drop a field's messages (ours and the server's) once it is valid again,
     * or right away (force) when its files were removed.
     */
    clearError(el, force = false) {
        if (!el?.name || !el.form) return;
        this.validate(el);
        if (!force && !el.checkValidity()) {
            // Too many (or too few) travelers: say so while typing, before the summary misleads.
            if (el.validity.rangeOverflow || el.validity.rangeUnderflow) this.flag(el);
            return;
        }
        const group = el.type === 'radio' ? [...el.form.querySelectorAll(`[name="${el.name}"]`)] : [el];
        group.forEach((g) => this.unflag(g));
        const box = el.closest('[data-field]');
        box?.querySelectorAll('.error-client, .error-site:not([x-text])').forEach((p) => p.remove());
        el.form.querySelector(`.error-client[data-for="${el.name}"]`)?.remove();
        if (!el.form.querySelector('[aria-invalid="true"]')) this.formError = '';
    },

    /**
     * Take the documents the visitor chose or dropped, keeping only the ones
     * the server will accept: JPG, PNG, WebP or PDF, 5 MB each, 10 files and
     * 15 MB across both cards. Returns the kept names and why others were left out.
     */
    acceptFiles(input, list) {
        const limits = this.uploads;
        const others = [...input.form.querySelectorAll('input[type="file"]')].filter((i) => i !== input).flatMap((i) => [...i.files]);
        let count = others.length;
        let bytes = others.reduce((sum, f) => sum + f.size, 0);
        const keep = new DataTransfer();
        const rejected = [];
        const say = (key, file) => rejected.push(this.messages[key].replace(':name', file.name));

        for (const file of list) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!limits.extensions.includes(extension) || (file.type && !/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(file.type))) say('fileType', file);
            else if (file.size > limits.maxBytes) say('fileSize', file);
            else if (count >= limits.maxCount) say('fileCount', file);
            else if (bytes + file.size > limits.maxTotalBytes) say('fileTotal', file);
            else {
                keep.items.add(file);
                count++;
                bytes += file.size;
            }
        }

        input.files = keep.files;
        this.clearError(input, true);
        return { names: [...keep.files].map((f) => f.name), rejected };
    },

    /** Show the server's messages under their fields (keys like "phone" or "passport.0"). */
    showErrors(form, errors) {
        let first = null;
        let general = null;
        for (const [key, messages] of Object.entries(errors)) {
            const name = key.split('.')[0];
            const el = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
            if (!el) {
                // Not tied to a field (reCAPTCHA, for one): show it above the button.
                general ??= messages[0];
                continue;
            }
            this.flag(el, messages[0]);
            first ??= el;
        }
        this.formError = general ?? this.messages.fix;
        if (first) {
            first.closest('[data-field]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first.focus({ preventScroll: true });
        }
    },

    /**
     * Send the form in the background so nothing the visitor entered (files
     * included) is lost when the server finds a problem. Uploads report their
     * progress; nothing waits for ever.
     */
    send(form) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 180000;
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload = () => {
                let data = {};
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch {
                    data = {};
                }
                resolve({ status: xhr.status, data });
            };
            xhr.onerror = () => reject(new Error('network'));
            xhr.ontimeout = () => reject(new Error('timeout'));
            xhr.send(new FormData(form));
        });
    },

    async submit(event) {
        event.preventDefault();
        if (this.busy) return;
        const form = event.target;
        this.formError = '';
        if (!this.check(form)) return;
        this.busy = true;
        this.progress = null;

        try {
            const token = await recaptchaToken(this.recaptcha);
            if (token) this.$refs.token.value = token;

            const { status, data } = await this.send(form);

            if (status >= 200 && status < 300 && data.redirect) {
                window.location.assign(data.redirect);
                return; // Leaving the page: keep the button busy.
            }

            if (status === 422 && data.errors) this.showErrors(form, data.errors);
            else this.formError = { 419: this.messages.expired, 429: this.messages.tooMany, 413: this.messages.tooLarge }[status] ?? this.messages.failed;
        } catch (error) {
            this.formError = error.message === 'timeout' ? this.messages.timeout : this.messages.network;
        }

        this.busy = false;
        this.progress = null;
    },
}));

/**
 * Air ticket quote: the trip type shows the return date (Return) or more
 * flights (Multi-city). Each added flight starts from where the last one
 * landed; only the last one can be removed, so the order stays simple.
 */
Alpine.data('fareQuote', (max = 4) => ({
    tripType: '',
    legs: 0,

    init() {
        this.tripType = this.field('trip_type')?.value ?? '';
        this.$watch('tripType', (type) => {
            if (type === 'Multi-city' && this.legs === 0) this.add();
        });
    },

    field(name) {
        return this.$el.closest('form')?.querySelector(`[name="${name}"]`);
    },

    add() {
        if (this.legs >= max) return;
        const previousTo = this.legs === 0 ? this.field('to_city')?.value : this.field(`legs[${this.legs - 1}][to]`)?.value;
        this.legs++;
        this.$nextTick(() => {
            const from = this.field(`legs[${this.legs - 1}][from]`);
            if (from && !from.value && previousTo) from.value = previousTo;
            (from && !from.value ? from : this.field(`legs[${this.legs - 1}][to]`))?.focus();
        });
    },

    remove() {
        if (this.legs === 0) return;
        ['from', 'to', 'date'].forEach((part) => {
            const input = this.field(`legs[${this.legs - 1}][${part}]`);
            if (input?._flatpickr) input._flatpickr.clear();
            else if (input) input.value = '';
        });
        this.legs--;
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
                this.$dispatch('form-sent');
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
 * Floating WhatsApp button: on phones it slides out of the way while the
 * visitor scrolls down (so it never covers a button they are reaching for)
 * and comes back as soon as they scroll up. Always shown near the top.
 */
Alpine.data('floatingContact', () => ({
    tucked: false,

    init() {
        const phone = window.matchMedia('(max-width: 639px)');
        let last = window.scrollY;

        window.addEventListener('scroll', () => {
            const y = window.scrollY;
            if (Math.abs(y - last) < 8) return;
            this.tucked = phone.matches && y > last && y > 160;
            last = y;
        }, { passive: true });
    },
}));

/**
 * Character counter under a message box with a maxlength: "120 / 2000",
 * amber near the limit and red at it. The digits follow the page language.
 */
Alpine.data('charCount', (max) => ({
    max,
    count: 0,

    init() {
        const field = this.$el.querySelector('textarea, input');
        if (!field) return;
        const update = () => (this.count = field.value.length);
        update();
        field.addEventListener('input', update);
    },

    get label() {
        const n = new Intl.NumberFormat(document.documentElement.lang.startsWith('bn') ? 'bn-BD' : 'en');
        return `${n.format(this.count)} / ${n.format(this.max)}`;
    },

    get tone() {
        return this.count >= this.max ? 'text-red-600' : this.count >= this.max * 0.9 ? 'text-amber-700' : 'text-ink-3';
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

/**
 * Hero slider. The leaving slide stays underneath while the next one fades in
 * over it (CSS in .hs-*); the current dot fills with the time left (--t).
 * Autoplay waits while the hero is hovered, focused, off-screen or the tab is
 * hidden, can be paused, and is off with reduced motion.
 */
Alpine.data('heroSlider', (count, duration = 7000) => ({
    i: 0,
    leaving: null,
    auto: count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    paused: false,
    holds: { hover: false, focus: false },
    visible: true,
    t: 0,
    timer: null,
    touchX: null,

    init() {
        this.paint();
        if (!this.auto) return;

        new IntersectionObserver(([entry]) => (this.visible = entry.isIntersecting), { threshold: 0.25 }).observe(this.$el);

        let last = performance.now();
        const tick = (now) => {
            const dt = Math.min(now - last, 100);
            last = now;
            if (!this.paused && !this.holds.hover && !this.holds.focus && this.visible && !document.hidden) {
                this.t += dt / duration;
                if (this.t >= 1) this.go(this.i + 1);
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
        const next = (index + count) % count;
        this.t = 0;
        this.paint();
        if (next === this.i) return;

        this.leaving = this.i;
        this.i = next;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (this.leaving = null), 1200);
    },

    step(delta) {
        this.go(this.i + delta);
    },

    hold(reason, on) {
        this.holds[reason] = on;
    },

    toggle() {
        this.paused = !this.paused;
    },

    swipeStart(event) {
        this.touchX = event.touches[0].clientX;
    },

    swipeEnd(event) {
        if (this.touchX === null) return;
        const dx = event.changedTouches[0].clientX - this.touchX;
        this.touchX = null;
        if (Math.abs(dx) > 50) this.step(dx < 0 ? 1 : -1);
    },
}));

/**
 * Sticky in-page section bar: marks the section in the middle of the screen
 * and keeps its link scrolled into view in the (sideways scrolling) bar.
 */
Alpine.data('sectionNav', () => ({
    active: null,

    init() {
        const links = [...this.$refs.links.querySelectorAll('[data-target]')];
        const sections = links.map((link) => document.getElementById(link.dataset.target)).filter(Boolean);

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        this.active = entry.target.id;
                        const link = links.find((l) => l.dataset.target === entry.target.id);
                        const bar = this.$refs.links;
                        if (link && bar.scrollWidth > bar.clientWidth) {
                            bar.scrollTo({ left: link.offsetLeft - bar.clientWidth / 2 + link.offsetWidth / 2, behavior: 'smooth' });
                        }
                    }
                }
            },
            { rootMargin: '-45% 0px -50% 0px' },
        );
        sections.forEach((section) => observer.observe(section));
    },
}));

/**
 * Count-up figures: [data-count] elements count every number in their text from 0
 * to its value whenever they scroll into view (both directions, like the reveals).
 * The formatting is kept exactly: Bangla or Latin digits, lakh grouping
 * (1,35,000), decimals, and surrounding text such as ৳, "+", "days" or "35–40".
 * Years (1900–2099) never count; they always show as they are.
 * Screen readers get the final text from a hidden copy; the animated copy is hidden from them.
 */
function countUp() {
    const els = document.querySelectorAll('[data-count]');
    if (!els.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

    const BN = '০১২৩৪৫৬৭৮৯';
    const toLatin = (s) => s.replace(/[০-৯]/g, (d) => BN.indexOf(d));
    const number = /[0-9০-৯]+(?:[,.][0-9০-৯]+)*/g;
    const isYear = (m) => /^(19|20)\d{2}$/.test(toLatin(m));

    const format = (value, sample) => {
        const bangla = /[০-৯]/.test(sample);
        const latin = toLatin(sample);
        const decimals = latin.includes('.') ? latin.split('.').pop().length : 0;
        let text = value.toFixed(decimals);
        if (latin.includes(',')) {
            // Same grouping as the original: lakh (1,35,000, as Money::format) or thousands (150,000).
            const lakh = /,\d{2},\d{3}(?:\.|$)/.test(latin);
            const [int, frac] = text.split('.');
            const last3 = int.slice(-3);
            const rest = int.slice(0, -3).replace(lakh ? /\B(?=(\d{2})+(?!\d))/g : /\B(?=(\d{3})+(?!\d))/g, ',');
            text = (rest ? rest + ',' : '') + last3 + (frac ? '.' + frac : '');
        }
        return bangla ? text.replace(/\d/g, (d) => BN[d]) : text;
    };

    const prepare = (el) => {
        // Keep the real text for screen readers; animate an aria-hidden copy.
        const sr = document.createElement('span');
        sr.className = 'sr-only';
        sr.textContent = el.textContent.trim();
        const shown = document.createElement('span');
        shown.setAttribute('aria-hidden', 'true');
        shown.append(...el.childNodes);
        el.append(sr, shown);

        const parts = [];
        const walker = document.createTreeWalker(shown, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            const original = node.nodeValue;
            // Only text with a number other than a year (e.g. 2016, which stays as it is).
            if (!(original.match(number) || []).some((m) => !isYear(m))) continue;
            parts.push({
                node,
                original,
                render: (p) =>
                    (node.nodeValue = original.replace(number, (m) => (isYear(m) ? m : format(Number(toLatin(m).replace(/,/g, '')) * p, m)))),
            });
        }
        return parts;
    };

    const run = (el) => {
        cancelAnimationFrame(el._countFrame);
        const parts = el._countParts;
        const start = performance.now();
        const duration = 1600;
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 4);
            parts.forEach((part) => (t < 1 ? part.render(eased) : (part.node.nodeValue = part.original)));
            if (t < 1) el._countFrame = requestAnimationFrame(tick);
        };
        el._countFrame = requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            for (const { target, isIntersecting, intersectionRatio } of entries) {
                if (isIntersecting && intersectionRatio >= 0.6 && !target._counted) {
                    target._counted = true;
                    run(target);
                } else if (!isIntersecting) {
                    // Gone from view: count again next time it appears.
                    target._counted = false;
                    target._countParts.forEach((part) => part.render(0));
                }
            }
        },
        { threshold: [0, 0.6] },
    );

    els.forEach((el) => {
        el._countParts = prepare(el);
        if (el._countParts.length) observer.observe(el);
    });
}

/**
 * Live local time in Dhaka (Asia/Dhaka), in the page language, refreshed every 15 seconds.
 */
Alpine.data('dhakaClock', (locale = 'en-GB') => ({
    time: '',
    day: '',
    iso: '',

    init() {
        const time = new Intl.DateTimeFormat(locale, { timeZone: 'Asia/Dhaka', hour: 'numeric', minute: '2-digit', hour12: true });
        const day = new Intl.DateTimeFormat(locale, { timeZone: 'Asia/Dhaka', weekday: 'long', day: 'numeric', month: 'long' });
        const tick = () => {
            const now = new Date();
            this.time = time.format(now);
            this.day = day.format(now);
            this.iso = now.toISOString();
        };
        tick();
        setInterval(tick, 15000);
    },
}));

window.Alpine = Alpine;
Alpine.start();

// Date fields become flatpickr calendars; the picker is only downloaded where a form has one.
const dateFields = document.querySelectorAll('input[data-datepicker]');
if (dateFields.length) {
    import('./datepicker.js').then(({ default: datepickers }) => datepickers(dateFields));
}

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

    // Long rich text (policies, articles): each paragraph, list or image reveals on its own.
    document.querySelectorAll('[data-reveal-children]').forEach((parent) => {
        [...parent.children].forEach((child) => child.setAttribute('data-reveal', parent.dataset.revealChildren || ''));
    });

    // Content blocks (package sections): each list item, paragraph, table or image matching the
    // selector reveals on its own, a beat after its siblings.
    document.querySelectorAll('[data-reveal-items]').forEach((root) => {
        root.querySelectorAll(root.dataset.revealItems || 'li').forEach((item) => {
            const index = [...item.parentElement.children].indexOf(item);
            item.setAttribute('data-reveal', '');
            item.style.setProperty('--reveal-delay', `${(index % 4) * 80}ms`);
        });
    });

    const reveals = document.querySelectorAll('[data-reveal]');
    const revealer = new IntersectionObserver(
        (entries) => {
            for (const { target, isIntersecting, intersectionRatio, intersectionRect, boundingClientRect } of entries) {
                // 12% in view, or (for elements taller than the screen) a fifth of the screen filled.
                if (isIntersecting && (intersectionRatio >= 0.12 || intersectionRect.height >= window.innerHeight * 0.2)) {
                    target.classList.add('is-revealed');
                } else if (!isIntersecting) {
                    // Remember which side it left from, so it re-enters from there.
                    target.classList.remove('is-revealed');
                    target.dataset.from = boundingClientRect.top < 0 ? 'above' : 'below';
                }
            }
        },
        { threshold: [0, 0.02, 0.05, 0.12] },
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
countUp();

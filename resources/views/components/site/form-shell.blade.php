@props(['section', 'submitLabel', 'success', 'plain' => false])
{{--
    Card around a public form: Alpine handles submission without a page
    reload; without JavaScript the form still posts and redirects back.
    The fields go in the slot.
--}}
@php($recaptcha = settings('tracking.recaptcha_site_key') && settings('tracking.recaptcha_secret_key') ? settings('tracking.recaptcha_site_key') : null)
{{-- plain: no card styling or reveal of its own (for forms inside another panel). --}}
<div {{ $attributes->class(['rounded-[1.75rem] bg-white p-6 shadow-[var(--shadow-lift)] ring-1 ring-line sm:p-9' => ! $plain, 'text-ink'])->merge($plain ? [] : ['data-reveal' => '']) }} id="form-{{ $section->id }}">
    @if (session('form_success') === $section->id)
        <div class="flex flex-col items-center py-10 text-center" role="status">
            <span class="flex size-14 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-7" /></span>
            <p class="mt-5 font-display text-display-sm text-brand">{{ $success }}</p>
        </div>
    @else
        <form
            method="POST"
            action="{{ route('forms.submit', $section) }}#form-{{ $section->id }}"
            x-data="siteForm(@js($recaptcha))"
            @submit.prevent="submit($el)"
            @focusin.once="loadRecaptcha()"
            novalidate
        >
            @csrf
            {{-- Honeypot: real visitors never see or fill this field. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website-{{ $section->id }}">Website</label>
                <input id="website-{{ $section->id }}" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <template x-if="done">
                <div class="flex flex-col items-center py-10 text-center" role="status">
                    <span class="flex size-14 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-7" /></span>
                    <p class="mt-5 font-display text-display-sm text-brand">{{ $success }}</p>
                </div>
            </template>

            <div x-show="!done">
                <div class="grid gap-5 sm:grid-cols-2">
                    {{ $slot }}
                </div>

                <p x-show="message" x-text="message" x-cloak class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-[14px] font-medium text-red-700" role="alert"></p>

                <button type="submit" class="btn btn-primary btn-lg mt-7 w-full" :disabled="busy">
                    <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    {{ $submitLabel }}
                </button>
                @if ($recaptcha)
                    <p class="mt-4 text-center text-[12px] text-ink-3">{{ __('Protected by reCAPTCHA.') }}</p>
                @endif
            </div>
        </form>
    @endif
</div>

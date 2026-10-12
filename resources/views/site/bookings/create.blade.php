@php
    use App\Support\Money;

    $recaptcha = settings('tracking.recaptcha_site_key') && settings('tracking.recaptcha_secret_key') ? settings('tracking.recaptcha_site_key') : null;
    $advance = ['type' => $package->advance_type ?: 'none', 'value' => (float) $package->advance_value];
    $canPayOnline = $gateway && $advance['type'] !== 'none';
    $config = [
        'options' => $options,
        'option' => (int) old('option', $selected['id']),
        'travelers' => (int) old('travelers', 1),
        'rooms' => (int) old('rooms', 1),
        'payment' => old('payment', $canPayOnline ? 'now' : 'later'),
        'advance' => $advance,
        'recaptcha' => $recaptcha,
        'locale' => app()->getLocale(),
    ];
    $invalid = fn (string $name) => $errors->has($name) ? 'aria-invalid="true" aria-describedby="'.e($name).'-error"' : '';
@endphp
<x-site.layout>
    <section class="section-light bg-sand-50 py-10 sm:py-14">
        <div class="container-site">
            {{-- No hero here: a compact title so the form starts near the top. --}}
            <div class="mb-8 flex flex-col gap-3 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">{{ __('Book your journey') }}</p>
                    <h1 class="mt-3 font-display text-display-md text-brand">{{ $name }}</h1>
                </div>
                <a href="{{ $package->url() }}" class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-brand hover:text-gold-deep">
                    <x-site.icon name="chevron-left" class="size-4 rtl:rotate-180" />
                    {{ __('Back to the package') }}
                </a>
            </div>

            <form method="POST" action="{{ route('bookings.store', $package->slug) }}" enctype="multipart/form-data" novalidate
                  x-data="bookingForm(@js($config))" @submit="submit($event)" @focusin.once="loadRecaptcha()"
                  class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                @csrf
                <input type="hidden" name="recaptcha_token" x-ref="token">
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="space-y-6">
                    @if ($errors->any())
                        <div class="rounded-2xl bg-red-50 px-5 py-4 text-[14px] text-red-800" role="alert" tabindex="-1" x-init="$el.focus()">
                            <p class="font-semibold">{{ __('Please check the highlighted fields.') }}</p>
                            @foreach (['recaptcha', 'passport', 'nid'] as $key)
                                @error($key)<p class="mt-1">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    @endif

                    {{-- 1. Option --}}
                    <fieldset class="card-site p-6 sm:p-8">
                        <legend class="sr-only">{{ __('Choose your package option') }}</legend>
                        <p class="flex items-center gap-3 font-display text-display-sm text-brand" aria-hidden="true"><span class="flex size-8 items-center justify-center rounded-full bg-gold text-[15px] font-bold text-brand-950">{{ Money::digits(1) }}</span>{{ __('Choose your package option') }}</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($options as $option)
                                <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 p-4 transition-colors"
                                       :class="option === {{ $option['id'] }} ? 'border-brand bg-brand-50/60' : 'border-line bg-white hover:border-brand/40'">
                                    <input type="radio" name="option" value="{{ $option['id'] }}" x-model.number="option" class="sr-only">
                                    <span class="text-[12px] font-bold tracking-wider text-ink-3 uppercase">{{ $option['type'] }}</span>
                                    <span class="mt-1 font-semibold text-ink">{{ $option['title'] }}</span>
                                    <span class="mt-2 font-display text-[1.25rem] font-semibold text-brand">{{ Money::html($option['price']) }} <span class="font-sans text-[13px] font-normal text-ink-3">{{ $option['unit_label'] }}</span></span>
                                    <span x-show="option === {{ $option['id'] }}" class="absolute top-3 right-3 flex size-6 items-center justify-center rounded-full bg-brand text-white"><x-site.icon name="check" class="size-3.5" /></span>
                                </label>
                            @endforeach
                        </div>
                        @error('option')<p class="error-site">{{ $message }}</p>@enderror

                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="travelers" class="label-site">{{ __('Number of travelers') }}<span class="text-red-600">*</span></label>
                                <div class="flex items-stretch">
                                    <button type="button" class="flex w-12 items-center justify-center rounded-l-xl border border-r-0 border-line bg-white text-xl text-brand hover:bg-sand-100" @click="travelers = Math.max(1, travelers - 1)" aria-label="{{ __('Fewer travelers') }}">−</button>
                                    <input id="travelers" name="travelers" type="number" min="1" max="50" inputmode="numeric" x-model.number="travelers" class="input-site rounded-none text-center" {!! $invalid('travelers') !!}>
                                    <button type="button" class="flex w-12 items-center justify-center rounded-r-xl border border-l-0 border-line bg-white text-xl text-brand hover:bg-sand-100" @click="travelers = Math.min(50, travelers + 1)" aria-label="{{ __('More travelers') }}">+</button>
                                </div>
                                @error('travelers')<p id="travelers-error" class="error-site">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="selected.unit === 'room'" x-cloak>
                                <label for="rooms" class="label-site">{{ __('Number of rooms') }}</label>
                                <input id="rooms" name="rooms" type="number" min="1" max="50" inputmode="numeric" x-model.number="rooms" class="input-site" :disabled="selected.unit !== 'room'">
                                @error('rooms')<p class="error-site">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    {{-- 2. Contact --}}
                    <fieldset class="card-site p-6 sm:p-8">
                        <legend class="sr-only">{{ __('Your details') }}</legend>
                        <p class="flex items-center gap-3 font-display text-display-sm text-brand" aria-hidden="true"><span class="flex size-8 items-center justify-center rounded-full bg-gold text-[15px] font-bold text-brand-950">{{ Money::digits(2) }}</span>{{ __('Your details') }}</p>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            @foreach ([
                                ['name', __('Full name'), 'text', true, 'name'],
                                ['phone', __('Mobile number'), 'tel', true, 'tel'],
                                ['email', __('Email'), 'email', false, 'email'],
                                ['address', __('Address'), 'text', false, 'street-address'],
                            ] as [$key, $label, $type, $required, $autocomplete])
                                <div>
                                    <label for="{{ $key }}" class="label-site">{{ $label }}@if ($required)<span class="text-red-600">*</span>@endif
                                        @if ($key === 'email')<span class="font-normal text-ink-3" x-text="payment === 'now' ? @js(' ('.__('needed for online payment').')') : ''"></span>@endif
                                    </label>
                                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ old($key) }}" autocomplete="{{ $autocomplete }}" @required($required) @if ($type === 'tel') inputmode="tel" @endif class="input-site" {!! $invalid($key) !!}>
                                    @error($key)<p id="{{ $key }}-error" class="error-site">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                            <div class="sm:col-span-2">
                                <label for="note" class="label-site">{{ __('Message') }} <span class="font-normal text-ink-3">({{ __('optional') }})</span></label>
                                <textarea id="note" name="note" rows="3" class="input-site" placeholder="{{ __('Names of the travelers, special needs, questions…') }}">{{ old('note') }}</textarea>
                                @error('note')<p class="error-site">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    {{-- 3. Documents --}}
                    <fieldset class="card-site p-6 sm:p-8">
                        <legend class="sr-only">{{ __('Documents') }}</legend>
                        <p class="flex items-center gap-3 font-display text-display-sm text-brand" aria-hidden="true"><span class="flex size-8 items-center justify-center rounded-full bg-gold text-[15px] font-bold text-brand-950">{{ Money::digits(3) }}</span>{{ __('Documents') }} <span class="font-sans text-[14px] font-normal text-ink-3">({{ __('optional') }})</span></p>
                        <p class="mt-2 text-[14px] text-ink-2">{{ __('You can upload passport and NID copies now or give them to us later. JPG, PNG or PDF, up to 5 MB each. Only our staff can see them.') }}</p>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            @foreach (['passport' => __('Passport copies'), 'nid' => __('NID copies')] as $key => $label)
                                <div>
                                    <label for="{{ $key }}" class="label-site">{{ $label }}</label>
                                    <input id="{{ $key }}" name="{{ $key }}[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           class="block w-full rounded-xl border border-dashed border-line bg-sand-50 p-3 text-[14px] text-ink-2 file:mr-3 file:rounded-full file:border-0 file:bg-brand file:px-4 file:py-2 file:text-[13px] file:font-semibold file:text-white hover:border-brand/50">
                                    @error($key.'.*')<p class="error-site">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- 4. Payment --}}
                    <fieldset class="card-site p-6 sm:p-8">
                        <legend class="sr-only">{{ __('Payment') }}</legend>
                        <p class="flex items-center gap-3 font-display text-display-sm text-brand" aria-hidden="true"><span class="flex size-8 items-center justify-center rounded-full bg-gold text-[15px] font-bold text-brand-950">{{ Money::digits(4) }}</span>{{ __('Payment') }}</p>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @if ($canPayOnline)
                                <label class="cursor-pointer rounded-2xl border-2 p-4 transition-colors" :class="payment === 'now' ? 'border-brand bg-brand-50/60' : 'border-line bg-white'">
                                    <input type="radio" name="payment" value="now" x-model="payment" class="sr-only">
                                    <span class="block font-semibold text-ink">{{ __('Pay the advance now') }}</span>
                                    <span class="mt-1 block text-[14px] text-ink-2">{{ __('Secure online payment by card or mobile banking through :gateway.', ['gateway' => $gateway->label()]) }}</span>
                                    <span class="mt-2 block font-display text-lg font-semibold text-brand" x-text="money(advanceAmount)"></span>
                                </label>
                            @endif
                            <label class="cursor-pointer rounded-2xl border-2 p-4 transition-colors" :class="payment === 'later' ? 'border-brand bg-brand-50/60' : 'border-line bg-white'">
                                <input type="radio" name="payment" value="later" x-model="payment" class="sr-only">
                                <span class="block font-semibold text-ink">{{ __('Book now, pay later') }}</span>
                                <span class="mt-1 block text-[14px] text-ink-2">{{ __('We reserve your place and call you to arrange the payment.') }}</span>
                            </label>
                        </div>

                        <label class="mt-6 flex items-start gap-3 text-[14px] text-ink-2">
                            <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-0.5 size-5 shrink-0 rounded accent-[var(--color-brand)]" {!! $invalid('agree') !!}>
                            <span>{{ __('I agree to the') }} <a href="{{ url('terms-and-conditions') }}" target="_blank" class="font-semibold text-brand underline">{{ __('booking terms and conditions') }}</a>{{ __(', including that prices may change with Saudi government or airline fees.') }}</span>
                        </label>
                        @error('agree')<p id="agree-error" class="error-site">{{ $message }}</p>@enderror
                    </fieldset>
                </div>

                {{-- Summary --}}
                <div class="lg:sticky lg:top-28">
                    <div class="overflow-hidden rounded-[1.5rem] bg-brand-950 text-white shadow-[var(--shadow-lift)]">
                        <div class="relative p-6">
                            <div class="pattern-star absolute inset-0 opacity-[0.05]" aria-hidden="true"></div>
                            <p class="relative text-[12px] font-semibold tracking-wider text-gold uppercase">{{ __('Your booking') }}</p>
                            <p class="relative mt-2 font-display text-[1.35rem] leading-snug">{{ $name }}</p>
                            <p class="relative mt-1 text-[14px] text-white/70" x-text="selected.type + ' · ' + selected.title"></p>
                        </div>
                        <dl class="space-y-3 border-t border-white/10 p-6 text-[15px]">
                            <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('Price') }}</dt><dd x-text="money(selected.price) + ' ' + selected.unit_label"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-white/65" x-text="selected.unit === 'room' ? @js(__('Rooms')) : @js(__('Travelers'))"></dt><dd x-text="digits(selected.unit === 'room' ? rooms : (selected.unit === 'group' ? 1 : travelers))"></dd></div>
                            <div class="flex justify-between gap-4 border-t border-white/10 pt-3 text-lg font-semibold"><dt>{{ __('Total') }}</dt><dd class="text-gold" x-text="money(total)"></dd></div>
                            <div x-show="advanceAmount > 0" class="flex justify-between gap-4 text-[14px]"><dt class="text-white/65">{{ __('Advance to confirm') }}</dt><dd x-text="money(advanceAmount)"></dd></div>
                        </dl>
                        <div class="p-6 pt-0">
                            <button type="submit" class="btn btn-gold btn-lg w-full" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                <span x-text="payment === 'now' ? @js(__('Book and pay advance')) : @js(__('Confirm booking'))">{{ __('Confirm booking') }}</span>
                            </button>
                            <p class="mt-3 text-center text-[12px] text-white/55">{{ __('Final amounts are confirmed by our team.') }}@if ($recaptcha) {{ __('Protected by reCAPTCHA.') }}@endif</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</x-site.layout>

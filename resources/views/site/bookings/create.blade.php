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
        'travelMonth' => (int) old('travel_month') ?: '',
        'travelYear' => (int) old('travel_year', $travel['years'][0] ?? 0) ?: '',
        'travelMode' => $travel['mode'],
        'monthNames' => $travel['months'],
        'yearLabels' => collect($travel['years'])->mapWithKeys(fn ($y) => [$y => Money::digits($y)])->all(),
        'payment' => old('payment', $canPayOnline ? 'now' : 'later'),
        'advance' => $advance,
        'recaptcha' => $recaptcha,
        'locale' => app()->getLocale(),
    ];
    $invalid = fn (string $name) => $errors->has($name) ? 'aria-invalid="true" aria-describedby="'.e($name).'-error"' : '';
@endphp
@inject('site', App\Support\SiteContext::class)
@php
    $phones = $site->phones();
    $steps = [
        ['id' => 'step-option', 'label' => __('Option')],
        ['id' => 'step-details', 'label' => __('Your details')],
        ['id' => 'step-documents', 'label' => __('Documents')],
        ['id' => 'step-payment', 'label' => __('Payment')],
    ];
    $pad = fn (int $n) => Money::digits(str_pad((string) $n, 2, '0', STR_PAD_LEFT));
@endphp
<x-site.layout>
    {{-- Title row: no hero, so the form starts near the top. --}}
    <div class="section-light bg-sand-50 pt-10 sm:pt-12">
        <div class="container-site">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between" data-reveal>
                <div class="flex items-center gap-5">
                    @if ($package->banner)
                        <img src="{{ $package->banner->url('sm') }}" alt="" width="96" height="96" class="hidden size-20 shrink-0 rounded-2xl object-cover shadow-[var(--shadow-soft)] sm:block">
                    @endif
                    <div>
                        <p class="eyebrow">{{ __('Book your journey') }}</p>
                        <h1 class="mt-2 font-display text-display-md text-brand">{{ $name }}</h1>
                    </div>
                </div>
                <a href="{{ $package->url() }}" class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-brand hover:text-gold-deep">
                    <x-site.icon name="chevron-left" class="size-4 rtl:rotate-180" />
                    {{ __('Back to the package') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Step bar: the step in view is marked; each step links to its card. --}}
    <div x-data="sectionNav" class="sticky top-20 z-20 mt-8 border-y border-line bg-white/95 backdrop-blur-md">
        <div class="container-site">
            <nav class="no-scrollbar -mx-1 flex gap-1 overflow-x-auto py-2.5" aria-label="{{ __('Booking steps') }}" x-ref="links">
                @foreach ($steps as $i => $step)
                    <a href="#{{ $step['id'] }}" data-target="{{ $step['id'] }}"
                       class="book-step section-nav-link flex shrink-0 items-center gap-2.5 rounded-full py-1.5 ps-1.5 pe-4 text-[14px] font-semibold whitespace-nowrap"
                       :class="active === '{{ $step['id'] }}' && 'is-active'"
                       :aria-current="active === '{{ $step['id'] }}' ? 'step' : null">
                        <span class="book-step-num flex size-7 items-center justify-center rounded-full text-[12.5px] font-bold tabular-nums">{{ Money::digits($i + 1) }}</span>
                        {{ $step['label'] }}
                    </a>
                    @if (! $loop->last)<span class="my-auto hidden h-px w-6 shrink-0 bg-line sm:block" aria-hidden="true"></span>@endif
                @endforeach
            </nav>
        </div>
    </div>

    <section class="section-light relative isolate bg-sand-50 pt-8 pb-14 sm:pt-10 sm:pb-20">
        <div class="pattern-star-ink pointer-events-none absolute inset-0 -z-10 opacity-[0.02]" aria-hidden="true"></div>
        <div class="container-site">
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
                        <div class="rounded-2xl bg-red-50 px-5 py-4 text-[14px] text-red-800 ring-1 ring-red-200" role="alert" tabindex="-1" x-init="$el.focus()">
                            <p class="font-semibold">{{ __('Please check the highlighted fields.') }}</p>
                            @foreach (['recaptcha', 'passport', 'nid'] as $key)
                                @error($key)<p class="mt-1">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    @endif

                    {{-- 1. Option --}}
                    <fieldset id="step-option" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Choose your package option') }}</legend>
                        <div class="book-card-head" data-reveal aria-hidden="true">
                            <span class="book-card-num">{{ $pad(1) }}</span>
                            <div>
                                <p class="font-display text-display-sm text-brand">{{ __('Choose your package option') }}</p>
                                <p class="mt-1 text-[14.5px] text-ink-2">{{ __('Pick a room option and how many people are travelling.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($options as $i => $option)
                                    <label class="book-choice relative flex cursor-pointer flex-col p-5" :class="option === {{ $option['id'] }} && 'is-selected'" data-reveal style="--reveal-delay: {{ ($i % 4) * 80 }}ms">
                                        <input type="radio" name="option" value="{{ $option['id'] }}" x-model.number="option" class="sr-only">
                                        <span class="text-[12px] font-semibold tracking-[0.12em] text-gold-deep uppercase">{{ $option['type'] }}</span>
                                        <span class="mt-1.5 pe-8 font-semibold text-ink">{{ $option['title'] }}</span>
                                        @if ($showPrice)
                                            <span class="mt-3 font-display text-[1.375rem] leading-none font-semibold text-brand">{{ Money::html($option['price']) }} <span class="font-sans text-[13px] font-normal text-ink-3">{{ $option['unit_label'] }}</span></span>
                                        @else
                                            <x-site.price-request class="mt-3 text-[14px] font-semibold text-brand" />
                                        @endif
                                        <span class="book-choice-check absolute top-4 end-4 flex size-6 items-center justify-center rounded-full" aria-hidden="true"><x-site.icon name="check" class="size-3.5" /></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('option')<p class="error-site">{{ $message }}</p>@enderror

                            <div class="mt-6 grid gap-5 sm:grid-cols-2" data-reveal style="--reveal-delay: 120ms">
                                <div>
                                    <label for="travelers" class="label-site">{{ __('Number of travelers') }}<span class="text-red-600">*</span></label>
                                    <div class="book-stepper flex items-stretch">
                                        <button type="button" @click="travelers = Math.max(1, travelers - 1)" aria-label="{{ __('Fewer travelers') }}">−</button>
                                        <input id="travelers" name="travelers" type="number" min="1" max="50" inputmode="numeric" x-model.number="travelers" class="input-site rounded-none border-x-0 text-center font-semibold" {!! $invalid('travelers') !!}>
                                        <button type="button" @click="travelers = Math.min(50, travelers + 1)" aria-label="{{ __('More travelers') }}">+</button>
                                    </div>
                                    @error('travelers')<p id="travelers-error" class="error-site">{{ $message }}</p>@enderror
                                </div>
                                <div x-show="selected.unit === 'room'" x-cloak>
                                    <label for="rooms" class="label-site">{{ __('Number of rooms') }}</label>
                                    <input id="rooms" name="rooms" type="number" min="1" max="50" inputmode="numeric" x-model.number="rooms" class="input-site" :disabled="selected.unit !== 'room'">
                                    @error('rooms')<p class="error-site">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            {{-- When they want to travel: month and year (Umrah) or the Hajj year. --}}
                            @if ($travel['mode'] !== 'none')
                                <div class="mt-5 grid gap-5 sm:grid-cols-2" data-reveal style="--reveal-delay: 160ms">
                                    @if ($travel['mode'] === 'month_year')
                                        <div>
                                            <label for="travel_month" class="label-site">{{ __('Travel month') }}<span class="text-red-600">*</span></label>
                                            <select id="travel_month" name="travel_month" x-model.number="travelMonth" required class="input-site"
                                                    x-effect="if (travelYear === {{ $travel['currentYear'] }} && travelMonth && travelMonth < {{ $travel['currentMonth'] }}) travelMonth = ''"
                                                    {!! $invalid('travel_month') !!}>
                                                <option value="">{{ __('Choose a month') }}</option>
                                                @foreach ($travel['months'] as $number => $month)
                                                    <option value="{{ $number }}" :disabled="travelYear === {{ $travel['currentYear'] }} && {{ $number }} < {{ $travel['currentMonth'] }}">{{ $month }}</option>
                                                @endforeach
                                            </select>
                                            @error('travel_month')<p id="travel_month-error" class="error-site">{{ $message }}</p>@enderror
                                        </div>
                                    @endif
                                    <div>
                                        <label for="travel_year" class="label-site">{{ $travel['mode'] === 'hajj_year' ? __('Hajj year') : __('Travel year') }}<span class="text-red-600">*</span></label>
                                        <select id="travel_year" name="travel_year" x-model.number="travelYear" required class="input-site" {!! $invalid('travel_year') !!}>
                                            @foreach ($travel['years'] as $year)
                                                <option value="{{ $year }}">{{ $travel['mode'] === 'hajj_year' ? __('Hajj :year', ['year' => Money::digits($year)]) : Money::digits($year) }}</option>
                                            @endforeach
                                        </select>
                                        @if ($travel['mode'] === 'hajj_year')
                                            <p class="mt-1.5 text-[13px] text-ink-3">{{ __('Registration for Hajj :year has closed; the next Hajj open for registration is :next.', ['year' => Money::digits($travel['years'][0] - 1), 'next' => Money::digits($travel['years'][0])]) }}</p>
                                        @endif
                                        @error('travel_year')<p id="travel_year-error" class="error-site">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    </fieldset>

                    {{-- 2. Contact --}}
                    <fieldset id="step-details" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Your details') }}</legend>
                        <div class="book-card-head" data-reveal aria-hidden="true">
                            <span class="book-card-num">{{ $pad(2) }}</span>
                            <div>
                                <p class="font-display text-display-sm text-brand">{{ __('Your details') }}</p>
                                <p class="mt-1 text-[14.5px] text-ink-2">{{ __('We call this number to confirm your booking.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body grid gap-5 sm:grid-cols-2">
                            @foreach ([
                                ['name', __('Full name'), 'text', true, 'name'],
                                ['phone', __('Mobile number'), 'tel', true, 'tel'],
                                ['email', __('Email'), 'email', false, 'email'],
                                ['address', __('Address'), 'text', false, 'street-address'],
                            ] as $i => [$key, $label, $type, $required, $autocomplete])
                                <div data-reveal style="--reveal-delay: {{ ($i % 2) * 80 }}ms">
                                    <label for="{{ $key }}" class="label-site">{{ $label }}@if ($required)<span class="text-red-600">*</span>@endif
                                        @if ($key === 'email')<span class="font-normal text-ink-3" x-text="payment === 'now' ? @js(' ('.__('needed for online payment').')') : ''"></span>@endif
                                    </label>
                                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ old($key) }}" autocomplete="{{ $autocomplete }}" @required($required) @if ($type === 'tel') inputmode="tel" @endif class="input-site" {!! $invalid($key) !!}>
                                    @error($key)<p id="{{ $key }}-error" class="error-site">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                            <div class="sm:col-span-2" data-reveal>
                                <label for="note" class="label-site">{{ __('Message') }} <span class="font-normal text-ink-3">({{ __('optional') }})</span></label>
                                <textarea id="note" name="note" rows="3" class="input-site" placeholder="{{ __('Names of the travelers, special needs, questions…') }}">{{ old('note') }}</textarea>
                                @error('note')<p class="error-site">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </fieldset>

                    {{-- 3. Documents --}}
                    <fieldset id="step-documents" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Documents') }}</legend>
                        <div class="book-card-head" data-reveal aria-hidden="true">
                            <span class="book-card-num">{{ $pad(3) }}</span>
                            <div>
                                <p class="font-display text-display-sm text-brand">{{ __('Documents') }} <span class="font-sans text-[14px] font-normal text-ink-3">({{ __('optional') }})</span></p>
                                <p class="mt-1 text-[14.5px] text-ink-2">{{ __('You can upload passport and NID copies now or give them to us later. JPG, PNG or PDF, up to 5 MB each. Only our staff can see them.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body grid gap-4 sm:grid-cols-2">
                            @foreach (['passport' => [__('Passport copies'), 'passport'], 'nid' => [__('NID copies'), 'document']] as $key => [$label, $icon])
                                <div x-data="{ files: [] }" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                                    <label for="{{ $key }}" class="book-drop group flex cursor-pointer flex-col items-center px-5 py-7 text-center">
                                        <span class="flex size-12 items-center justify-center rounded-2xl bg-brand text-gold transition-[scale] duration-500 group-hover:scale-105"><x-site.icon :name="$icon" class="size-6" /></span>
                                        <span class="mt-3 font-semibold text-brand">{{ $label }}</span>
                                        <span class="mt-1 text-[13px] text-ink-3" x-text="files.length ? files.join(', ') : @js(__('Choose files'))">{{ __('Choose files') }}</span>
                                    </label>
                                    <input id="{{ $key }}" name="{{ $key }}[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only"
                                           @change="files = [...$event.target.files].map((f) => f.name)">
                                    @error($key.'.*')<p class="error-site">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- 4. Payment --}}
                    <fieldset id="step-payment" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Payment') }}</legend>
                        <div class="book-card-head" data-reveal aria-hidden="true">
                            <span class="book-card-num">{{ $pad(4) }}</span>
                            <div>
                                <p class="font-display text-display-sm text-brand">{{ __('Payment') }}</p>
                                <p class="mt-1 text-[14.5px] text-ink-2">{{ __('Choose how you would like to pay.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="grid gap-3 sm:grid-cols-2">
                                @if ($canPayOnline)
                                    <label class="book-choice relative flex cursor-pointer gap-4 p-5" :class="payment === 'now' && 'is-selected'" data-reveal>
                                        <input type="radio" name="payment" value="now" x-model="payment" class="sr-only">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sand-100 text-gold-deep"><x-site.icon name="wallet" class="size-5" /></span>
                                        <span class="min-w-0 pe-6">
                                            <span class="block font-semibold text-ink">{{ __('Pay the advance now') }}</span>
                                            <span class="mt-1 block text-[14px] text-ink-2">{{ __('Secure online payment by card or mobile banking through :gateway.', ['gateway' => $gateway->label()]) }}</span>
                                            <span class="mt-2 block font-display text-lg font-semibold text-brand" x-text="money(advanceAmount)"></span>
                                        </span>
                                        <span class="book-choice-check absolute top-4 end-4 flex size-6 items-center justify-center rounded-full" aria-hidden="true"><x-site.icon name="check" class="size-3.5" /></span>
                                    </label>
                                @endif
                                <label class="book-choice relative flex cursor-pointer gap-4 p-5" :class="payment === 'later' && 'is-selected'" data-reveal style="--reveal-delay: 80ms">
                                    <input type="radio" name="payment" value="later" x-model="payment" class="sr-only">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sand-100 text-gold-deep"><x-site.icon name="headset" class="size-5" /></span>
                                    <span class="min-w-0 pe-6">
                                        <span class="block font-semibold text-ink">{{ __('Book now, pay later') }}</span>
                                        <span class="mt-1 block text-[14px] text-ink-2">{{ __('We reserve your place and call you to arrange the payment.') }}</span>
                                    </span>
                                    <span class="book-choice-check absolute top-4 end-4 flex size-6 items-center justify-center rounded-full" aria-hidden="true"><x-site.icon name="check" class="size-3.5" /></span>
                                </label>
                            </div>

                            <label class="mt-6 flex items-start gap-3 rounded-2xl bg-sand-50 p-4 text-[14px] text-ink-2 ring-1 ring-line" data-reveal>
                                <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-0.5 size-5 shrink-0 rounded accent-[var(--color-brand)]" {!! $invalid('agree') !!}>
                                <span>{{ __('I agree to the') }} <a href="{{ url('terms-and-conditions') }}" target="_blank" class="font-semibold text-brand underline">{{ __('booking terms and conditions') }}</a>{{ __(', including that prices may change with Saudi government or airline fees.') }}</span>
                            </label>
                            @error('agree')<p id="agree-error" class="error-site">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>
                </div>

                {{-- Summary --}}
                <div class="space-y-4 lg:sticky lg:top-40" data-reveal style="--reveal-delay: 120ms">
                    <div class="overflow-hidden rounded-[1.75rem] bg-brand-950 text-white shadow-[var(--shadow-lift)]">
                        <div class="relative isolate overflow-hidden p-6">
                            @if ($package->banner)
                                <img src="{{ $package->banner->url('sm') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-40">
                                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950 via-brand-950/70 to-brand-950/30" aria-hidden="true"></div>
                            @else
                                <div class="pattern-star absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true"></div>
                            @endif
                            <p class="text-[12px] font-semibold tracking-wider text-gold uppercase">{{ __('Your booking') }}</p>
                            <p class="mt-2 font-display text-[1.35rem] leading-snug">{{ $name }}</p>
                            <p class="mt-1 text-[14px] text-white/75" x-text="selected.type + ' · ' + selected.title"></p>
                        </div>
                        <dl class="space-y-3 border-t border-white/10 p-6 text-[15px]">
                            @if ($showPrice)
                            <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('Price') }}</dt><dd class="text-end" x-text="money(selected.price) + ' ' + selected.unit_label"></dd></div>
                            @endif
                            @if ($travel['mode'] !== 'none')
                            <div class="flex justify-between gap-4"><dt class="text-white/65">{{ $travel['mode'] === 'hajj_year' ? __('Hajj year') : __('Travel') }}</dt><dd class="text-end" x-text="travelDate || '—'"></dd></div>
                            @endif
                            <div class="flex justify-between gap-4"><dt class="text-white/65" x-text="selected.unit === 'room' ? @js(__('Rooms')) : @js(__('Travelers'))"></dt><dd x-text="digits(selected.unit === 'room' ? rooms : (selected.unit === 'group' ? 1 : travelers))"></dd></div>
                            @if ($showPrice)
                            <div class="flex items-baseline justify-between gap-4 border-t border-white/10 pt-4"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="text-[1.625rem] leading-none font-bold text-gold tabular-nums" x-text="money(total)"></dd></div>
                            <div x-show="advanceAmount > 0" class="flex justify-between gap-4 text-[14px]"><dt class="text-white/65">{{ __('Advance to confirm') }}</dt><dd x-text="money(advanceAmount)"></dd></div>
                            @else
                            <div class="flex gap-3 border-t border-white/10 pt-4 text-[14px] text-white/80">
                                <x-site.icon name="headset" class="mt-0.5 size-5 shrink-0 text-gold" />
                                <p>{{ __('Our team will call you to confirm the price and how to pay.') }}</p>
                            </div>
                            @endif
                        </dl>
                        <div class="p-6 pt-0">
                            <button type="submit" class="btn btn-gold btn-lg w-full" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                <span x-text="payment === 'now' ? @js(__('Book and pay advance')) : @js(__('Confirm booking'))">{{ __('Confirm booking') }}</span>
                            </button>
                            <p class="mt-3 text-center text-[12px] text-white/60">{{ __('Final amounts are confirmed by our team.') }}@if ($recaptcha) {{ __('Protected by reCAPTCHA.') }}@endif</p>
                        </div>
                    </div>

                    {{-- What happens next, and help. --}}
                    <div class="rounded-[1.75rem] border border-line bg-white p-6">
                        <ul class="space-y-3 text-[14px] text-ink-2">
                            <li class="flex gap-3"><x-site.icon name="headset" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Our team will call you shortly to confirm.') }}</li>
                            <li class="flex gap-3"><x-site.icon name="shield" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Your documents are seen only by our staff.') }}</li>
                            <li class="flex gap-3"><x-site.icon name="wallet" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Please pay only through our official channels.') }}</li>
                        </ul>
                        @if ($phones)
                            <a href="{{ $phones[0]['href'] }}" class="mt-5 flex items-center justify-between gap-3 rounded-2xl bg-sand-50 px-4 py-3 ring-1 ring-line transition-colors hover:bg-sand-100">
                                <span>
                                    <span class="block text-[12px] font-semibold tracking-wider text-ink-3 uppercase">{{ __('Need help?') }}</span>
                                    <span class="font-semibold text-brand" dir="ltr">{{ $phones[0]['display'] }}</span>
                                </span>
                                <span class="flex size-10 items-center justify-center rounded-full bg-brand text-white"><x-site.icon name="phone" class="size-4.5" /></span>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </section>
</x-site.layout>

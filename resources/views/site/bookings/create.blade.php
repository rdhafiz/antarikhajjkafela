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
        'messages' => [
            'required' => __('Please fill in this field.'),
            'choose' => __('Please choose one.'),
            'email' => __('Enter a valid email address.'),
            'invalid' => __('Please check this field.'),
        ],
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
        // Only when there is a choice to make: without online payment every booking is "pay later".
        ...($canPayOnline ? [['id' => 'step-payment', 'label' => __('Payment')]] : []),
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
                  x-data="bookingForm(@js($config))" @submit="submit($event)" @input="clearError($event.target)" @change="clearError($event.target)" @focusin.once="loadRecaptcha()"
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
                    <fieldset id="step-option" class="book-card book-card-option scroll-mt-40">
                        <legend class="sr-only">{{ __('Choose your package option') }}</legend>
                        <div class="book-card-head book-head-scene" data-reveal aria-hidden="true">
                            {{-- The Haram, fading in behind the heading. --}}
                            <img src="{{ asset('images/booking/haram-scene.webp') }}" alt="" width="760" height="430" class="book-scene" loading="lazy" decoding="async">
                            <span class="book-card-num">{{ $pad(1) }}</span>
                            <span class="book-head-rule" aria-hidden="true"></span>
                            <div class="relative min-w-0">
                                <p class="book-head-title">{{ __('Choose your package option') }}</p>
                                <p class="book-head-text">{{ __('Pick a room option and how many people are travelling.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="grid gap-4 lg:grid-cols-2" data-field>
                                @foreach ($options as $i => $option)
                                    <label class="book-option" :class="option === {{ $option['id'] }} && 'is-selected'" @class(['is-selected' => $option['id'] === $selected['id']]) data-reveal style="--reveal-delay: {{ ($i % 4) * 80 }}ms">
                                        <input type="radio" name="option" value="{{ $option['id'] }}" x-model.number="option" class="sr-only" required data-message="{{ __('Choose a package option.') }}" @checked($option['id'] === $selected['id'])>
                                        <span class="book-option-art" aria-hidden="true"><x-site.hotel-art :variant="$i" class="size-full" /></span>
                                        <span class="book-option-body min-w-0 flex-1">
                                            <span class="book-option-type">{{ $option['type'] }}</span>
                                            <span class="book-option-title">{{ $option['title'] }}</span>
                                            @if ($showPrice)
                                                <span class="book-option-price">{{ Money::html($option['price']) }} <span class="book-option-unit">{{ $option['unit_label'] }}</span></span>
                                            @else
                                                <x-site.price-request class="mt-2 block text-[15px] font-semibold text-brand" />
                                            @endif
                                        </span>
                                        <span class="book-option-check" aria-hidden="true"><x-site.icon name="check" class="size-4" stroke-width="3" /></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('option')<p class="error-site">{{ $message }}</p>@enderror

                            {{-- How many, and when. --}}
                            <div class="book-plan" data-reveal style="--reveal-delay: 120ms">
                                <div data-field>
                                    <label for="travelers" class="book-label">{{ __('Number of travelers') }} <span class="text-red-600">*</span></label>
                                    <div class="book-count">
                                        <div class="book-count-field">
                                            <button type="button" class="book-count-minus" @click="travelers = Math.max(1, travelers - 1)" :disabled="travelers <= 1" aria-label="{{ __('Fewer travelers') }}"><span aria-hidden="true"></span></button>
                                            <input id="travelers" name="travelers" type="number" min="1" max="50" inputmode="numeric" x-model.number="travelers" required {!! $invalid('travelers') !!}>
                                        </div>
                                        <button type="button" class="book-count-plus" @click="travelers = Math.min(50, travelers + 1)" :disabled="travelers >= 50" aria-label="{{ __('More travelers') }}"><span aria-hidden="true"></span></button>
                                    </div>
                                    @error('travelers')<p id="travelers-error" class="error-site">{{ $message }}</p>@enderror
                                </div>
                                <div x-show="selected.unit === 'room'" x-cloak data-field>
                                    <label for="rooms" class="book-label">{{ __('Number of rooms') }}</label>
                                    <input id="rooms" name="rooms" type="number" min="1" max="50" inputmode="numeric" x-model.number="rooms" class="book-select" :disabled="selected.unit !== 'room'" :required="selected.unit === 'room'">
                                    @error('rooms')<p class="error-site">{{ $message }}</p>@enderror
                                </div>

                                {{-- When they want to travel: the year first (it decides which months are open), then the month for Umrah. --}}
                                @if ($travel['mode'] !== 'none')
                                    <div data-field>
                                        <label for="travel_year" class="book-label">{{ $travel['mode'] === 'hajj_year' ? __('Hajj year') : __('Travel year') }} <span class="text-red-600">*</span></label>
                                        <div class="book-picker">
                                            <x-site.icon name="calendar" class="book-picker-icon" />
                                            <select id="travel_year" name="travel_year" x-model.number="travelYear" required class="book-select" {!! $invalid('travel_year') !!}>
                                                @foreach ($travel['years'] as $year)
                                                    <option value="{{ $year }}">{{ $travel['mode'] === 'hajj_year' ? __('Hajj :year', ['year' => Money::digits($year)]) : Money::digits($year) }}</option>
                                                @endforeach
                                            </select>
                                            <x-site.icon name="chevron-down" class="book-picker-chevron" />
                                        </div>
                                        @if ($travel['mode'] === 'hajj_year')
                                            <p class="mt-1.5 text-[13px] text-ink-3">{{ __('Registration for Hajj :year has closed; the next Hajj open for registration is :next.', ['year' => Money::digits($travel['years'][0] - 1), 'next' => Money::digits($travel['years'][0])]) }}</p>
                                        @endif
                                        @error('travel_year')<p id="travel_year-error" class="error-site">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                                @if ($travel['mode'] === 'month_year')
                                    <div data-field>
                                        <label for="travel_month" class="book-label">{{ __('Travel month') }} <span class="text-red-600">*</span></label>
                                        <div class="book-picker">
                                            <x-site.icon name="calendar" class="book-picker-icon" />
                                            <select id="travel_month" name="travel_month" x-model.number="travelMonth" required class="book-select"
                                                    x-effect="if (travelYear === {{ $travel['currentYear'] }} && travelMonth && travelMonth < {{ $travel['currentMonth'] }}) travelMonth = ''"
                                                    {!! $invalid('travel_month') !!}>
                                                <option value="">{{ __('Choose a month') }}</option>
                                                @foreach ($travel['months'] as $number => $month)
                                                    <option value="{{ $number }}" :disabled="travelYear === {{ $travel['currentYear'] }} && {{ $number }} < {{ $travel['currentMonth'] }}">{{ $month }}</option>
                                                @endforeach
                                            </select>
                                            <x-site.icon name="chevron-down" class="book-picker-chevron" />
                                        </div>
                                        @error('travel_month')<p id="travel_month-error" class="error-site">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    </fieldset>

                    {{-- 2. Contact --}}
                    <fieldset id="step-details" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Your details') }}</legend>
                        <div class="book-card-head book-head-scene" data-reveal aria-hidden="true">
                            {{-- The Prophet's Mosque, fading in behind the heading. --}}
                            <img src="{{ asset('images/booking/madinah-scene.webp') }}" alt="" width="760" height="430" class="book-scene" loading="lazy" decoding="async">
                            <span class="book-card-num">{{ $pad(2) }}</span>
                            <span class="book-head-rule" aria-hidden="true"></span>
                            <div class="relative min-w-0">
                                <p class="book-head-title">{{ __('Your details') }}</p>
                                <p class="book-head-text">{{ __('We call this number to confirm your booking.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="book-fields">
                                @foreach ([
                                    ['name', __('Full name'), 'text', true, 'name', 'user'],
                                    ['phone', __('Mobile number'), 'tel', true, 'tel', 'phone'],
                                    ['email', __('Email'), 'email', false, 'email', 'mail'],
                                    ['address', __('Address'), 'text', false, 'street-address', 'map-pin'],
                                ] as $i => [$key, $label, $type, $required, $autocomplete, $icon])
                                    <div data-field data-reveal style="--reveal-delay: {{ ($i % 2) * 80 }}ms">
                                        <label for="{{ $key }}" class="book-label">{{ $label }}@if ($required) <span class="text-red-600">*</span>@else <span class="font-normal text-ink-3">({{ __('optional') }})</span>@endif
                                            @if ($key === 'email')<span class="font-normal text-ink-3" x-text="payment === 'now' ? @js(' – '.__('needed for online payment')) : ''"></span>@endif
                                        </label>
                                        <div class="book-picker">
                                            <x-site.icon :name="$icon" class="book-picker-icon" />
                                            <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ old($key) }}" autocomplete="{{ $autocomplete }}" @required($required) @if ($type === 'tel') inputmode="tel" dir="ltr" placeholder="01XXXXXXXXX" pattern="[0-9+\-\s\(\)]{7,20}" data-message="{{ __('Enter a valid mobile number.') }}" @endif
                                                   @if ($key === 'email') :required="payment === 'now'" @endif class="book-select book-input" {!! $invalid($key) !!}>
                                        </div>
                                        @error($key)<p id="{{ $key }}-error" class="error-site">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                                <div class="sm:col-span-2" data-reveal>
                                    <label for="note" class="book-label">{{ __('Message') }} <span class="font-normal text-ink-3">({{ __('optional') }})</span></label>
                                    <div class="book-picker book-picker-top">
                                        <x-site.icon name="document" class="book-picker-icon" />
                                        <textarea id="note" name="note" rows="4" class="book-select book-input" placeholder="{{ __('Names of the travelers, special needs, questions…') }}">{{ old('note') }}</textarea>
                                    </div>
                                    @error('note')<p class="error-site">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    {{-- 3. Documents --}}
                    <fieldset id="step-documents" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Documents') }}</legend>
                        <div class="book-card-head book-head-scene" data-reveal aria-hidden="true">
                            {{-- The Green Dome, fading in behind the heading. --}}
                            <img src="{{ asset('images/booking/dome-scene.webp') }}" alt="" width="760" height="430" class="book-scene" loading="lazy" decoding="async">
                            <span class="book-card-num">{{ $pad(3) }}</span>
                            <span class="book-head-rule" aria-hidden="true"></span>
                            <div class="relative min-w-0">
                                <p class="book-head-title">{{ __('Documents') }} <span class="font-sans text-[15px] font-normal text-ink-3">({{ __('optional') }})</span></p>
                                <p class="book-head-text">{{ __('Upload passport and NID copies now, or give them to us later.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach (['passport' => [__('Passport copies'), 'passport'], 'nid' => [__('NID copies'), 'document']] as $key => [$label, $icon])
                                    {{-- Pick or drop files; the card turns gold once it holds some. --}}
                                    <div class="book-option book-doc"
                                         x-data="{
                                            files: [],
                                            dragging: false,
                                            pick(list) { this.files = [...list].map((f) => f.name) },
                                            drop(event) {
                                                this.dragging = false;
                                                if (!event.dataTransfer.files.length) return;
                                                this.$refs.input.files = event.dataTransfer.files;
                                                this.pick(event.dataTransfer.files);
                                            },
                                            clear() { this.$refs.input.value = ''; this.files = [] },
                                         }"
                                         :class="{ 'is-selected': files.length, 'is-dragging': dragging }"
                                         @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                                         data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                                        <span class="book-option-art book-doc-art" aria-hidden="true"><x-site.icon :name="$icon" class="size-9" /></span>
                                        <span class="book-option-body min-w-0 flex-1">
                                            <span class="book-option-title !mt-0">{{ $label }}</span>
                                            <span class="book-doc-hint">{{ __('JPG, PNG or PDF · up to 5 MB each') }}</span>
                                            <span class="book-doc-files" x-show="files.length" x-cloak>
                                                <template x-for="name in files" :key="name"><span class="book-doc-file" x-text="name"></span></template>
                                            </span>
                                            <span class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                                                <label for="{{ $key }}" class="book-doc-button">
                                                    <x-site.icon name="upload" class="size-4" />
                                                    <span x-text="files.length ? @js(__('Change files')) : @js(__('Choose files'))">{{ __('Choose files') }}</span>
                                                </label>
                                                <button type="button" class="book-doc-clear" x-show="files.length" x-cloak @click="clear()">{{ __('Remove') }}</button>
                                                <span class="book-doc-drop" x-show="!files.length">{{ __('or drop them here') }}</span>
                                            </span>
                                        </span>
                                        <span class="book-option-check" aria-hidden="true"><x-site.icon name="check" class="size-4" stroke-width="3" /></span>
                                        <input id="{{ $key }}" x-ref="input" name="{{ $key }}[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only" aria-label="{{ $label }}"
                                               @change="pick($event.target.files)">
                                    </div>
                                    @error($key.'.*')<p class="error-site lg:col-span-2">{{ $message }}</p>@enderror
                                @endforeach
                            </div>
                            <p class="mt-5 flex items-center gap-2 text-[14px] text-ink-2" data-reveal>
                                <x-site.icon name="shield" class="size-4.5 shrink-0 text-emerald" />{{ __('Your documents are seen only by our staff.') }}
                            </p>
                        </div>
                    </fieldset>

                    {{-- 4. Payment: only when the visitor can choose to pay online --}}
                    @if ($canPayOnline)
                    <fieldset id="step-payment" class="book-card scroll-mt-40">
                        <legend class="sr-only">{{ __('Payment') }}</legend>
                        <div class="book-card-head book-head-scene" data-reveal aria-hidden="true">
                            {{-- The Haram at dusk, fading in behind the heading. --}}
                            <img src="{{ asset('images/booking/haram-dusk.webp') }}" alt="" width="760" height="430" class="book-scene" loading="lazy" decoding="async">
                            <span class="book-card-num">{{ $pad(4) }}</span>
                            <span class="book-head-rule" aria-hidden="true"></span>
                            <div class="relative min-w-0">
                                <p class="book-head-title">{{ __('Payment') }}</p>
                                <p class="book-head-text">{{ __('Choose how you would like to pay.') }}</p>
                            </div>
                        </div>
                        <div class="book-card-body">
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ([
                                    ['now', 'wallet', __('Pay the advance now'), __('Secure online payment by card or mobile banking through :gateway.', ['gateway' => $gateway->label()])],
                                    ['later', 'headset', __('Book now, pay later'), __('We reserve your place and call you to arrange the payment.')],
                                ] as $i => [$value, $icon, $title, $text])
                                    <label class="book-option book-pay" :class="payment === '{{ $value }}' && 'is-selected'" @class(['is-selected' => $config['payment'] === $value]) data-reveal style="--reveal-delay: {{ $i * 80 }}ms">
                                        <input type="radio" name="payment" value="{{ $value }}" x-model="payment" class="sr-only" @checked($config['payment'] === $value)>
                                        <span class="book-option-art book-doc-art" aria-hidden="true"><x-site.icon :name="$icon" class="size-9" /></span>
                                        <span class="book-option-body min-w-0 flex-1">
                                            <span class="book-option-title !mt-0">{{ $title }}</span>
                                            <span class="book-pay-text">{{ $text }}</span>
                                            @if ($value === 'now')
                                                <span class="book-option-price"><span x-text="money(advanceAmount)"></span> <span class="book-option-unit">{{ __('advance') }}</span></span>
                                            @endif
                                        </span>
                                        <span class="book-option-check" aria-hidden="true"><x-site.icon name="check" class="size-4" stroke-width="3" /></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </fieldset>
                    @else
                        {{-- No online payment: every booking is "pay later". --}}
                        <input type="hidden" name="payment" value="later">
                    @endif
                </div>

                {{-- Summary --}}
                <div class="space-y-3 lg:sticky lg:top-40" data-reveal style="--reveal-delay: 120ms">
                    <div class="overflow-hidden rounded-[1.75rem] bg-brand-950 text-white shadow-[var(--shadow-lift)]">
                        <div class="relative isolate overflow-hidden px-5 pt-5 pb-4">
                            @if ($package->banner)
                                <img src="{{ $package->banner->url('sm') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover opacity-40">
                                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-950 via-brand-950/70 to-brand-950/30" aria-hidden="true"></div>
                            @else
                                <div class="pattern-star absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true"></div>
                            @endif
                            <p class="text-[12px] font-semibold tracking-wider text-gold uppercase">{{ __('Your booking') }}</p>
                            <p class="mt-1 font-display text-[1.25rem] leading-snug">{{ $name }}</p>
                            <p class="mt-0.5 text-[13.5px] text-white/75" x-text="selected.type + ' · ' + selected.title"></p>
                        </div>
                        <dl class="space-y-2 border-t border-white/10 px-5 py-4 text-[14.5px]">
                            @if ($showPrice)
                            <div class="flex justify-between gap-4"><dt class="text-white/65">{{ __('Price') }}</dt><dd class="text-end" x-text="money(selected.price) + ' ' + selected.unit_label"></dd></div>
                            @endif
                            @if ($travel['mode'] !== 'none')
                            <div class="flex justify-between gap-4"><dt class="text-white/65">{{ $travel['mode'] === 'hajj_year' ? __('Hajj year') : __('Travel') }}</dt><dd class="text-end" x-text="travelDate || '—'"></dd></div>
                            @endif
                            <div class="flex justify-between gap-4"><dt class="text-white/65" x-text="selected.unit === 'room' ? @js(__('Rooms')) : @js(__('Travelers'))"></dt><dd x-text="digits(selected.unit === 'room' ? rooms : (selected.unit === 'group' ? 1 : travelers))"></dd></div>
                            @if ($showPrice)
                            <div class="flex items-baseline justify-between gap-4 border-t border-white/10 pt-3"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="text-[1.5rem] leading-none font-bold text-gold tabular-nums" x-text="money(total)"></dd></div>
                            <div x-show="advanceAmount > 0" class="flex justify-between gap-4 text-[14px]"><dt class="text-white/65">{{ __('Advance to confirm') }}</dt><dd x-text="money(advanceAmount)"></dd></div>
                            @else
                            <div class="flex gap-3 border-t border-white/10 pt-3 text-[13.5px] text-white/80">
                                <x-site.icon name="headset" class="mt-0.5 size-5 shrink-0 text-gold" />
                                <p>{{ __('Our team will call you to confirm the price and how to pay.') }}</p>
                            </div>
                            @endif
                        </dl>
                        <div class="px-5 pb-5">
                            {{-- The terms, accepted right where the booking is confirmed. --}}
                            <div class="book-agree mb-3.5" data-field>
                                <label class="flex items-start gap-2.5 text-[13px] leading-normal text-white/80">
                                    <input type="checkbox" name="agree" value="1" @checked(old('agree')) required data-message="{{ __('Please accept the booking terms.') }}" class="mt-0.5 size-[1.125rem] shrink-0 cursor-pointer rounded accent-[var(--color-gold)]" {!! $invalid('agree') !!}>
                                    <span>{{ __('I agree to the') }} <a href="{{ url('terms-and-conditions') }}" target="_blank" class="font-semibold text-gold underline underline-offset-2 hover:text-white">{{ __('booking terms and conditions') }}</a>{{ __(', including that prices may change with Saudi government or airline fees.') }}</span>
                                </label>
                                @error('agree')<p id="agree-error" class="error-site">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn btn-gold min-h-12 w-full" :disabled="busy">
                                <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                <span x-text="payment === 'now' ? @js(__('Book and pay advance')) : @js(__('Confirm booking'))">{{ __('Confirm booking') }}</span>
                            </button>
                            <p class="mt-2 text-center text-[12px] text-white/60">{{ __('Final amounts are confirmed by our team.') }}@if ($recaptcha) {{ __('Protected by reCAPTCHA.') }}@endif</p>
                        </div>
                    </div>

                    {{-- What happens next, and help. --}}
                    <div class="rounded-[1.75rem] border border-line bg-white p-5">
                        <ul class="space-y-2 text-[13.5px] leading-snug text-ink-2">
                            <li class="flex gap-3"><x-site.icon name="headset" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Our team will call you shortly to confirm.') }}</li>
                            <li class="flex gap-3"><x-site.icon name="shield" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Your documents are seen only by our staff.') }}</li>
                            <li class="flex gap-3"><x-site.icon name="wallet" class="mt-0.5 size-4.5 shrink-0 text-gold-deep" />{{ __('Please pay only through our official channels.') }}</li>
                        </ul>
                        @if ($phones)
                            <a href="{{ $phones[0]['href'] }}" class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-sand-50 px-4 py-2.5 ring-1 ring-line transition-colors hover:bg-sand-100">
                                <span>
                                    <span class="block text-[12px] font-semibold tracking-wider text-ink-3 uppercase">{{ __('Need help?') }}</span>
                                    <span class="font-semibold text-brand" dir="ltr">{{ $phones[0]['display'] }}</span>
                                </span>
                                <span class="flex size-9 items-center justify-center rounded-full bg-brand text-white"><x-site.icon name="phone" class="size-4" /></span>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </section>
</x-site.layout>

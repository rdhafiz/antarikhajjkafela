@php
    $catalogue = App\Domain\Forms\LeadFields::all();
    $fields = array_values(array_filter($c['fields'] ?? [], fn ($f) => isset($catalogue[$f['field'] ?? ''])));
    $fields = collect($fields)->unique('field')->values()->all();
    $lines = fn ($text) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $text))));
    // Each select's choices: typed in the admin (package, country) or fixed and translated.
    $choices = fn (string $name) => match ($name) {
        'package' => $lines($c['package_options']),
        'visa_country' => $lines($c['country_options'] ?? ''),
        default => App\Domain\Forms\LeadFields::choices($name),
    };
    $bullets = array_filter($c['bullets'] ?? [], fn ($b) => filled($b['text']));
    $labels = [
        'name' => __('Full name'), 'phone' => __('Mobile number'), 'email' => __('Email'), 'district' => __('District'),
        'thana' => __('Thana / Upazila'), 'package' => __('Package of interest'), 'travelers' => __('Number of travelers'),
        'travel_month' => __('Preferred travel month'), 'message' => __('Message'),
    ] + array_map(fn ($field) => __($field['label']), $catalogue);
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="grid items-start gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
        <div class="lg:sticky lg:top-28">
            <x-site.block-heading :c="$c" align="left" />
            @if ($bullets)
                <ul class="mt-8 space-y-4">
                    @foreach ($bullets as $bullet)
                        <li class="flex gap-3" data-reveal="left" style="--reveal-delay: {{ 120 + $loop->index * 90 }}ms">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-3.5" /></span>
                            <span class="sec-text text-[1.0625rem]">{{ $bullet['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @php
            // Air ticket quotes: the trip type shows the return date (Return) or more flights (Multi-city).
            $keys = array_column($fields, 'field');
            $trip = in_array('trip_type', $keys, true);
            $legsAfter = $trip && in_array('from_city', $keys, true) && in_array('to_city', $keys, true)
                ? collect(['return_date', 'departure_date', 'to_city'])->first(fn ($k) => in_array($k, $keys, true))
                : null;
            $tripHooks = [
                'trip_type' => ['input' => 'x-model="tripType"'],
                'return_date' => ['wrapper' => 'x-show="tripType === \'Return\'"', 'input' => ':disabled="tripType !== \'Return\'"', 'required' => true],
            ];
        @endphp
        <x-site.form-shell :section="$section" :submit-label="$c['submit_label'] ?: __('Register now')" :success="$c['success'] ?: __('Thank you! Our team will call you soon, In Sha Allah.')">
            @if ($trip)<div class="contents" x-data="fareQuote({{ App\Domain\Forms\LeadFields::MAX_EXTRA_FLIGHTS }})">@endif
            @foreach ($fields as $field)
                @if ($legsAfter && $field['field'] === 'from_city')
                    <p class="text-[13px] font-bold tracking-[0.12em] text-gold-deep uppercase sm:col-span-2" x-show="tripType === 'Multi-city'" x-cloak>{{ __('Flight :number', ['number' => App\Support\Money::digits(1)]) }}</p>
                @endif
                @include('sections.partials.form-field', [
                    'sectionId' => $section->id,
                    'name' => $field['field'],
                    'label' => filled($field['label']) ? $field['label'] : $labels[$field['field']],
                    'type' => $catalogue[$field['field']]['type'],
                    // The return date is required whenever it shows (a Return trip), whatever the admin chose.
                    'required' => (bool) $field['required'] || ($trip && isset($tripHooks[$field['field']]['required'])),
                    'autocomplete' => $catalogue[$field['field']]['autocomplete'],
                    'options' => $choices($field['field']),
                    'min' => $catalogue[$field['field']]['min'] ?? null,
                    'default' => $catalogue[$field['field']]['default'] ?? null,
                    'wide' => count($fields) % 2 === 1 && $loop->last,
                    'wrapperAttrs' => $trip ? ($tripHooks[$field['field']]['wrapper'] ?? null) : null,
                    'inputAttrs' => $trip ? ($tripHooks[$field['field']]['input'] ?? null) : null,
                ])
                @if ($legsAfter === $field['field'])
                    @include('sections.partials.fare-legs', ['sectionId' => $section->id, 'max' => App\Domain\Forms\LeadFields::MAX_EXTRA_FLIGHTS])
                @endif
            @endforeach
            @if ($trip)</div>@endif
        </x-site.form-shell>
    </div>
</x-site.block>

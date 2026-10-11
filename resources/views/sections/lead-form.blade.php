@php
    $catalogue = App\Domain\Forms\LeadFields::all();
    $fields = array_values(array_filter($c['fields'] ?? [], fn ($f) => isset($catalogue[$f['field'] ?? ''])));
    $fields = collect($fields)->unique('field')->values()->all();
    $packages = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $c['package_options']))));
    $bullets = array_filter($c['bullets'] ?? [], fn ($b) => filled($b['text']));
    $labels = [
        'name' => __('Full name'), 'phone' => __('Mobile number'), 'email' => __('Email'), 'district' => __('District'),
        'thana' => __('Thana / Upazila'), 'package' => __('Package of interest'), 'travelers' => __('Number of travelers'),
        'travel_month' => __('Preferred travel month'), 'message' => __('Message'),
    ];
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="grid items-start gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
        <div class="lg:sticky lg:top-28">
            <x-site.block-heading :c="$c" align="left" />
            @if ($bullets)
                <ul class="mt-8 space-y-4">
                    @foreach ($bullets as $bullet)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-3.5" /></span>
                            <span class="sec-text text-[1.0625rem]">{{ $bullet['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-site.form-shell :section="$section" :submit-label="$c['submit_label'] ?: __('Register now')" :success="$c['success'] ?: __('Thank you! Our team will call you soon, In Sha Allah.')">
            @foreach ($fields as $field)
                @include('sections.partials.form-field', [
                    'sectionId' => $section->id,
                    'name' => $field['field'],
                    'label' => filled($field['label']) ? $field['label'] : $labels[$field['field']],
                    'type' => $catalogue[$field['field']]['type'],
                    'required' => (bool) $field['required'],
                    'autocomplete' => $catalogue[$field['field']]['autocomplete'],
                    'options' => $packages,
                    'wide' => count($fields) % 2 === 1 && $loop->last,
                ])
            @endforeach
        </x-site.form-shell>
    </div>
</x-site.block>

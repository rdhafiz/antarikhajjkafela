{{--
    The flights after the first of a multi-city fare quote (Flight 2 … Flight 1 + $max).
    All rows are in the page so the date pickers start with it; Alpine (fareQuote) shows as many
    as the visitor added, and inputs of hidden rows are disabled so they are not sent.
    Expects $sectionId and $max.
--}}
@for ($i = 0; $i < $max; $i++)
    @php
        $base = 'f-'.$sectionId.'-legs-'.$i;
        $after = $i === 0 ? 'departure_date' : 'legs['.($i - 1).'][date]';
    @endphp
    <fieldset class="m-0 min-w-0 border-0 p-0 sm:col-span-2" x-show="tripType === 'Multi-city' && legs > {{ $i }}" x-cloak>
        <legend class="sr-only">{{ __('Flight :number', ['number' => App\Support\Money::digits($i + 2)]) }}</legend>
        <div class="rounded-2xl border border-line bg-sand-50/60 p-4 sm:p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-[13px] font-bold tracking-[0.12em] text-gold-deep uppercase" aria-hidden="true">{{ __('Flight :number', ['number' => App\Support\Money::digits($i + 2)]) }}</p>
                <button type="button" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-[13px] font-semibold text-ink-2 hover:bg-white hover:text-red-600"
                        x-show="legs === {{ $i + 1 }}" @click="remove()" aria-label="{{ __('Remove flight :number', ['number' => App\Support\Money::digits($i + 2)]) }}">
                    <x-site.icon name="x" class="size-3.5" />{{ __('Remove') }}
                </button>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['from' => __('Flying from'), 'to' => __('Flying to'), 'date' => __('Flight date')] as $part => $label)
                    @php($key = "legs.{$i}.{$part}")
                    <div>
                        <label for="{{ $base }}-{{ $part }}" class="mb-1.5 block text-[14px] font-semibold text-ink">{{ $label }}<span class="ml-0.5 text-red-600" aria-hidden="true">*</span></label>
                        <input id="{{ $base }}-{{ $part }}" name="legs[{{ $i }}][{{ $part }}]" required
                               type="{{ $part === 'date' ? 'date' : 'text' }}"
                               :disabled="tripType !== 'Multi-city' || legs <= {{ $i }}"
                               @if ($part === 'date') min="{{ now()->toDateString() }}" data-datepicker data-placeholder="{{ __('Choose a date') }}" data-min-from="{{ $after }}" @endif
                               :aria-invalid="errors['{{ $key }}'] ? 'true' : null" :aria-describedby="errors['{{ $key }}'] ? '{{ $base }}-{{ $part }}-error' : null"
                               class="block h-12 w-full rounded-xl border border-line bg-white px-4 text-[15px] text-ink placeholder:text-ink-3 focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
                               :class="errors['{{ $key }}'] && '!border-red-500'">
                        <p id="{{ $base }}-{{ $part }}-error" x-show="errors['{{ $key }}']" x-text="errors['{{ $key }}']?.[0]" x-cloak class="mt-1.5 text-[13px] font-medium text-red-600" role="alert"></p>
                    </div>
                @endforeach
            </div>
        </div>
    </fieldset>
@endfor
<div class="sm:col-span-2" x-show="tripType === 'Multi-city'" x-cloak>
    <button type="button" class="inline-flex items-center gap-2 rounded-full border border-dashed border-brand/40 px-4 py-2.5 text-[14px] font-semibold text-brand transition-colors hover:border-brand hover:bg-brand/5"
            x-show="legs < {{ $max }}" @click="add()">
        <x-site.icon name="plane" class="size-4" />{{ __('Add another flight') }}
    </button>
    <p x-show="errors.legs" x-text="errors.legs?.[0]" x-cloak class="mt-1.5 text-[13px] font-medium text-red-600" role="alert"></p>
    <p x-show="legs >= {{ $max }}" class="text-[13px] text-ink-3">{{ __('A multi-city trip can have up to :count flights.', ['count' => App\Support\Money::digits($max + 1)]) }}</p>
</div>

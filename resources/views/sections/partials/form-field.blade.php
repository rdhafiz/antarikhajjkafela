{{--
    One form input. Expects: $name, $label, $type, $required, $autocomplete, $options (for select), $min and $default (optional).
    Message boxes take $max (characters, default 2000): the box stops there and a counter shows how much is left.
    Optional Alpine hooks: $wrapperAttrs (on the field, e.g. x-show) and $inputAttrs (on the input, e.g. x-model or :disabled).
--}}
@php($id = 'f-'.$sectionId.'-'.$name)
<div @class(['sm:col-span-2' => in_array($type, ['textarea'], true) || ($wide ?? false)]) {!! $wrapperAttrs ?? '' !!}>
    <label for="{{ $id }}" class="mb-1.5 block text-[14px] font-semibold text-ink">
        {{ $label }}@if ($required)<span class="ml-0.5 text-red-600" aria-hidden="true">*</span>@endif
    </label>
    @if ($type === 'textarea')
        @php($max = $max ?? 2000)
        <div x-data="charCount({{ $max }})">
            <textarea id="{{ $id }}" name="{{ $name }}" rows="4" maxlength="{{ $max }}" @required($required)
                      :aria-invalid="errors.{{ $name }} ? 'true' : null" :aria-describedby="errors.{{ $name }} ? '{{ $id }}-error {{ $id }}-limit' : '{{ $id }}-limit'"
                      class="block w-full rounded-xl border border-line bg-white px-4 py-3 text-[15px] text-ink placeholder:text-ink-3 focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
                      :class="errors.{{ $name }} && '!border-red-500'"></textarea>
            <p id="{{ $id }}-limit" class="mt-1.5 text-right text-[12.5px] tabular-nums" :class="tone"><span class="sr-only">{{ __('Up to :count characters', ['count' => App\Support\Money::digits($max)]) }}: </span><span x-text="label" aria-hidden="true"></span></p>
        </div>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required) {!! $inputAttrs ?? '' !!}
                :aria-invalid="errors.{{ $name }} ? 'true' : null"
                class="block h-12 w-full rounded-xl border border-line bg-white px-4 text-[15px] text-ink focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
                :class="errors.{{ $name }} && '!border-red-500'">
            <option value="">{{ __('Please choose') }}</option>
            {{-- A plain list (value = label) or value => label pairs. --}}
            @foreach (array_is_list($options) ? array_combine($options, $options) : $options as $value => $text)
                <option value="{{ $value }}" @selected((string) $value === (string) ($default ?? ''))>{{ $text }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @required($required) {!! $inputAttrs ?? '' !!}
               @if ($autocomplete ?? null) autocomplete="{{ $autocomplete }}" @endif
               @if ($type === 'number') min="{{ $min ?? 1 }}" max="50" inputmode="numeric" @endif
               @if (isset($default)) value="{{ $default }}" @endif
               @if ($type === 'date') min="{{ now()->toDateString() }}" data-datepicker data-placeholder="{{ __('Choose a date') }}" @endif
               @if ($type === 'tel') inputmode="tel" @endif
               :aria-invalid="errors.{{ $name }} ? 'true' : null" :aria-describedby="errors.{{ $name }} ? '{{ $id }}-error' : null"
               class="block h-12 w-full rounded-xl border border-line bg-white px-4 text-[15px] text-ink placeholder:text-ink-3 focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
               :class="errors.{{ $name }} && '!border-red-500'">
    @endif
    <p id="{{ $id }}-error" x-show="errors.{{ $name }}" x-text="errors.{{ $name }}?.[0]" x-cloak class="mt-1.5 text-[13px] font-medium text-red-600" role="alert"></p>
</div>

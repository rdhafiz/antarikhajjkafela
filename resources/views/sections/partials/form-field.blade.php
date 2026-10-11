{{--
    One form input. Expects: $name, $label, $type, $required, $autocomplete (optional), $options (for select).
--}}
@php($id = 'f-'.$sectionId.'-'.$name)
<div @class(['sm:col-span-2' => in_array($type, ['textarea'], true) || ($wide ?? false)])>
    <label for="{{ $id }}" class="mb-1.5 block text-[14px] font-semibold text-ink">
        {{ $label }}@if ($required)<span class="ml-0.5 text-red-600" aria-hidden="true">*</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="4" @required($required)
                  :aria-invalid="errors.{{ $name }} ? 'true' : null" :aria-describedby="errors.{{ $name }} ? '{{ $id }}-error' : null"
                  class="block w-full rounded-xl border border-line bg-white px-4 py-3 text-[15px] text-ink placeholder:text-ink-3 focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
                  :class="errors.{{ $name }} && '!border-red-500'"></textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required)
                :aria-invalid="errors.{{ $name }} ? 'true' : null"
                class="block h-12 w-full rounded-xl border border-line bg-white px-4 text-[15px] text-ink focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
                :class="errors.{{ $name }} && '!border-red-500'">
            <option value="">{{ __('Please choose') }}</option>
            @foreach ($options as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @required($required)
               @if ($autocomplete ?? null) autocomplete="{{ $autocomplete }}" @endif
               @if ($type === 'number') min="1" max="50" inputmode="numeric" @endif
               @if ($type === 'tel') inputmode="tel" @endif
               :aria-invalid="errors.{{ $name }} ? 'true' : null" :aria-describedby="errors.{{ $name }} ? '{{ $id }}-error' : null"
               class="block h-12 w-full rounded-xl border border-line bg-white px-4 text-[15px] text-ink placeholder:text-ink-3 focus:border-brand focus:ring-3 focus:ring-brand/15 focus:outline-none"
               :class="errors.{{ $name }} && '!border-red-500'">
    @endif
    <p id="{{ $id }}-error" x-show="errors.{{ $name }}" x-text="errors.{{ $name }}?.[0]" x-cloak class="mt-1.5 text-[13px] font-medium text-red-600" role="alert"></p>
</div>

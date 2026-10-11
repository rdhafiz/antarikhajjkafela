@props([
    'eyebrow' => null,
    'title',
    'intro' => null,
    'align' => 'center',    // center | left
    'light' => false,       // on dark backgrounds
    'as' => 'h2',
])
<div {{ $attributes->class([
    'max-w-2xl',
    'mx-auto text-center' => $align === 'center',
]) }} data-reveal>
    @if ($eyebrow)
        <p @class(['eyebrow', 'eyebrow-light' => $light, 'justify-center' => $align === 'center'])>{{ $eyebrow }}</p>
    @endif
    <{{ $as }} @class([
        'text-display-lg',
        'mt-4' => $eyebrow,
        'text-white' => $light,
        'text-brand' => ! $light,
    ])>{{ $title }}</{{ $as }}>
    @if ($intro)
        <p @class(['mt-5 text-[1.0625rem] leading-relaxed', 'text-white/70' => $light, 'text-ink-2' => ! $light])>{{ $intro }}</p>
    @endif
    {{ $slot }}
</div>

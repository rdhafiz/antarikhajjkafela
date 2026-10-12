@php
    // "staggered" (kept as the stored value): the cards sit over a background that moves on its own.
    $staggered = ($c['layout'] ?? 'grid') === 'staggered';
@endphp
@if ($packages->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
        @if ($staggered)
            <div class="pointer-events-none absolute -top-40 -left-40 -z-10 size-[560px] rounded-full bg-gold/10 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>
            <div class="pattern-star pointer-events-none absolute -inset-y-[20%] inset-x-0 -z-10 opacity-[0.05]" data-parallax="0.2" aria-hidden="true"></div>
        @endif
        <x-site.block-heading :c="$c" class="mb-14" />
        <div @class([
            'grid gap-6',
            'sm:grid-cols-2 lg:grid-cols-3' => $packages->count() !== 2 && $packages->count() !== 4,
            'mx-auto max-w-4xl sm:grid-cols-2' => $packages->count() === 2,
            'sm:grid-cols-2 xl:grid-cols-4' => $packages->count() === 4,
        ])>
            @foreach ($packages as $index => $package)
                <x-site.package-card :package="$package" data-reveal :style="'--reveal-delay: '.(($index % 3) * 100).'ms'" />
            @endforeach
        </div>
        @if ($c['cta'])
            <div class="mt-12 text-center" data-reveal>
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

@if ($packages->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
        <x-site.block-heading :c="$c" class="mb-14" />
        <div @class([
            'grid gap-6',
            'sm:grid-cols-2 lg:grid-cols-3' => $packages->count() !== 2 && $packages->count() !== 4,
            'mx-auto max-w-4xl sm:grid-cols-2' => $packages->count() === 2,
            'sm:grid-cols-2 xl:grid-cols-4' => $packages->count() === 4,
        ])>
            @foreach ($packages as $index => $package)
                <x-site.package-card :package="$package" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
            @endforeach
        </div>
        @if ($c['cta'])
            <div class="mt-12 text-center">
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

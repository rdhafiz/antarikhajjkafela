@php($magazine = ($c['layout'] ?? 'grid') === 'magazine' && $posts->count() >= 2)
@if ($posts->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
        @if ($magazine)
            {{-- Heading on the left, the lead story large, the rest beneath it. --}}
            <div class="mb-12 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <x-site.block-heading :c="$c" align="left" />
                @if ($c['cta'])
                    <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'outline'" icon-right="arrow-right" class="hidden shrink-0 lg:inline-flex" data-reveal="right" />
                @endif
            </div>
            <x-site.post-card :post="$posts->first()" lead data-reveal="wipe" />
            <div @class(['mt-6 grid gap-6', 'sm:grid-cols-2' => $posts->count() > 2, 'lg:grid-cols-3' => $posts->count() > 3])>
                @foreach ($posts->skip(1)->values() as $index => $post)
                    <x-site.post-card :post="$post" data-reveal="{{ $index % 2 ? 'right' : 'left' }}" :style="'--reveal-delay: '.(($index % 3) * 100).'ms'" />
                @endforeach
            </div>
        @else
            <x-site.block-heading :c="$c" class="mb-14" />
            <div @class([
                'grid gap-6',
                'sm:grid-cols-2 lg:grid-cols-3' => $posts->count() >= 3,
                'mx-auto max-w-4xl sm:grid-cols-2' => $posts->count() === 2,
                'mx-auto max-w-md' => $posts->count() === 1,
            ])>
                @foreach ($posts as $index => $post)
                    <x-site.post-card :post="$post" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                @endforeach
            </div>
        @endif
        @if ($c['cta'])
            <div @class(['mt-12 text-center', 'lg:hidden' => $magazine])>
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

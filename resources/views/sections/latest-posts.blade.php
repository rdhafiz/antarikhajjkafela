@if ($posts->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
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
        @if ($c['cta'])
            <div class="mt-12 text-center">
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

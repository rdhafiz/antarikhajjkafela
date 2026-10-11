@if ($reviews->isNotEmpty())
    <x-site.block :style="$style" :section="$section">
        <x-site.block-heading :c="$c" class="mb-14" />
        {{-- A swipeable row on phones, a grid from tablets up. --}}
        <div @class([
            '-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:items-start sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0',
            'sm:grid-cols-2 lg:grid-cols-3' => $reviews->count() >= 3,
            'mx-auto max-w-4xl sm:grid-cols-2' => $reviews->count() === 2,
            'mx-auto max-w-2xl' => $reviews->count() === 1,
        ])>
            @foreach ($reviews as $index => $review)
                <x-site.review-card :review="$review" class="w-[85%] shrink-0 snap-center sm:w-auto" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
            @endforeach
        </div>
        @if ($c['cta'])
            <div class="mt-12 text-center">
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

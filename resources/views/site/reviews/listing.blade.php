{{-- Reviews page listing (page_listing section). Data from App\Domain\Reviews\ReviewListing. --}}
@php use App\Support\Money; @endphp
<x-site.block :style="$style" :section="$section">
    @if ($reviews->isEmpty())
        <div class="mx-auto max-w-md py-10 text-center">
            <span class="sec-icon mx-auto"><x-site.icon name="quote" class="size-6" /></span>
            <p class="sec-heading mt-5 font-display text-display-sm">{{ __('Reviews coming soon') }}</p>
        </div>
    @else
        @if ($average)
            <div class="mx-auto mb-14 flex max-w-xl flex-col items-center gap-3 text-center sm:flex-row sm:justify-center sm:gap-6 sm:text-left" data-reveal>
                <p class="sec-heading font-display text-[3.5rem] leading-none font-semibold">{{ Money::digits(number_format($average, 1)) }}</p>
                <div>
                    <x-site.stars :rating="(int) round($average)" class="[&_svg]:size-5" />
                    <p class="sec-text mt-1.5 text-[15px]">{{ trans_choice('Average of :count review|Average of :count reviews', $ratedCount, ['count' => Money::digits($ratedCount)]) }}</p>
                </div>
            </div>
        @endif

        @if ($videoReviews->isNotEmpty())
            <h2 class="sr-only">{{ __('Video reviews') }}</h2>
            <div @class(['mb-12 grid gap-6', 'sm:grid-cols-2' => $videoReviews->count() > 1, 'lg:grid-cols-3' => $videoReviews->count() > 2, 'mx-auto max-w-3xl' => $videoReviews->count() === 1])>
                @foreach ($videoReviews as $index => $review)
                    <x-site.review-card :review="$review" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                @endforeach
            </div>
        @endif

        @php($written = $textReviews->reject(fn ($r) => $videoReviews->contains($r)))
        @if ($written->isNotEmpty())
            <h2 class="sr-only">{{ __('Written reviews') }}</h2>
            {{-- Masonry columns keep reviews of different lengths tidy. --}}
            <div class="gap-6 sm:columns-2 lg:columns-3">
                @foreach ($written as $index => $review)
                    <x-site.review-card :review="$review" :with-video="false" class="mb-6 break-inside-avoid" data-reveal />
                @endforeach
            </div>
        @endif
    @endif
</x-site.block>

{{-- Gallery page listing (page_listing section). Data from App\Domain\Gallery\GalleryListing. --}}
<x-site.block :style="$style" :section="$section">
    @if ($albums->isEmpty())
        <div class="mx-auto max-w-md py-10 text-center">
            <span class="sec-icon mx-auto"><x-site.icon name="images" class="size-6" /></span>
            <p class="sec-heading mt-5 font-display text-display-sm">{{ __('Photos coming soon') }}</p>
        </div>
    @else
        <h2 class="sr-only">{{ __('Albums') }}</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($albums as $index => $album)
                <x-site.album-card :album="$album" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
            @endforeach
        </div>
        <x-site.pagination :paginator="$albums" class="mt-14" />
    @endif
</x-site.block>

{{-- Blog page listing (page_listing section). Data from App\Domain\Blog\BlogListing. --}}
@php
    $locale = app()->getLocale();
    $rest = $posts->getCollection()->when($lead, fn ($c) => $c->reject(fn ($p) => $p->is($lead)))->values();
@endphp
<x-site.block :style="$style" :section="$section">
    @if ($categories->count() > 1 || $tag)
        <nav class="-mx-4 mb-10 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:justify-center sm:px-0" aria-label="{{ __('Categories') }}">
            <a href="{{ $blogUrl }}" @class(['shrink-0 rounded-full px-4.5 py-2 text-[14px] font-semibold transition-colors', 'bg-brand text-white' => ! $category && ! $tag, 'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => $category || $tag]) @if (! $category && ! $tag) aria-current="page" @endif>{{ __('All') }}</a>
            @foreach ($categories as $item)
                <a href="{{ $item->url() }}" @class(['shrink-0 rounded-full px-4.5 py-2 text-[14px] font-semibold transition-colors', 'bg-brand text-white' => $category?->is($item), 'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => ! $category?->is($item)]) @if ($category?->is($item)) aria-current="page" @endif>{{ $item->getTranslation('name', $locale) }}</a>
            @endforeach
            @if ($tag)
                <a href="{{ $blogUrl }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gold px-4 py-2 text-[14px] font-semibold text-brand-950" aria-label="{{ __('Remove filter') }}: #{{ $tag }}">
                    #{{ $tag }} <x-site.icon name="x" class="size-4" />
                </a>
            @endif
        </nav>
    @endif

    @if ($posts->isEmpty())
        <div class="mx-auto max-w-md py-10 text-center">
            <span class="sec-icon mx-auto"><x-site.icon name="book" class="size-6" /></span>
            <p class="sec-heading mt-5 font-display text-display-sm">{{ __('No articles yet') }}</p>
            <p class="sec-text mt-2">{{ __('New guides and news will appear here soon.') }}</p>
            @if ($category || $tag)
                <a href="{{ $blogUrl }}" class="mt-5 inline-flex font-semibold text-brand hover:underline">{{ __('See all articles') }}</a>
            @endif
        </div>
    @else
        @if ($lead)
            <x-site.post-card :post="$lead" lead heading-tag="h2" class="mb-8" data-reveal />
        @endif

        @if ($rest->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rest as $index => $post)
                    <x-site.post-card :post="$post" :heading-tag="$lead ? 'h3' : 'h2'" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                @endforeach
            </div>
        @endif

        <x-site.pagination :paginator="$posts" class="mt-14" />
    @endif
</x-site.block>

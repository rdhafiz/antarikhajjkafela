@props(['paginator'])
{{-- Numbered pagination for public listings. --}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    @endphp
    <nav {{ $attributes->class('flex items-center justify-center gap-1.5') }} aria-label="{{ __('Pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="flex size-11 items-center justify-center rounded-full text-ink-3/50" aria-hidden="true"><x-site.icon name="chevron-left" class="size-5 rtl:rotate-180" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex size-11 items-center justify-center rounded-full text-brand transition-colors hover:bg-white" aria-label="{{ __('Previous page') }}"><x-site.icon name="chevron-left" class="size-5 rtl:rotate-180" /></a>
        @endif

        @foreach ($pages as $index => $page)
            @if ($index > 0 && $page - $pages[$index - 1] > 1)
                <span class="px-1 text-ink-3" aria-hidden="true">…</span>
            @endif
            @if ($page === $current)
                <span class="flex size-11 items-center justify-center rounded-full bg-brand text-[15px] font-semibold text-white" aria-current="page">{{ App\Support\Money::digits($page) }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="flex size-11 items-center justify-center rounded-full text-[15px] font-semibold text-ink-2 transition-colors hover:bg-white hover:text-brand" aria-label="{{ __('Page :number', ['number' => $page]) }}">{{ App\Support\Money::digits($page) }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex size-11 items-center justify-center rounded-full text-brand transition-colors hover:bg-white" aria-label="{{ __('Next page') }}"><x-site.icon name="chevron-right" class="size-5 rtl:rotate-180" /></a>
        @else
            <span class="flex size-11 items-center justify-center rounded-full text-ink-3/50" aria-hidden="true"><x-site.icon name="chevron-right" class="size-5 rtl:rotate-180" /></span>
        @endif
    </nav>
@endif

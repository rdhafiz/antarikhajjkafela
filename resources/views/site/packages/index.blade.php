@php
    $locale = app()->getLocale();
    $title = $service?->getTranslation('name', $locale) ?? __('Hajj & Umrah packages');
    $intro = $service?->getTranslation('summary', $locale) ?? __('Compare our packages: hotels near the Haram, flights, durations and prices, all guided by experienced scholars.');
@endphp
<x-site.layout header="overlay">
    <x-site.page-header
        :eyebrow="$service ? __('Packages') : __('Our packages')"
        :title="$title"
        :intro="$intro"
        :image="$service?->image"
    />

    <x-site.section tone="sand" spacing="md">
        @if ($services->count() > 1)
            <nav class="mb-10 flex flex-wrap gap-2" aria-label="{{ __('Filter packages') }}">
                <a href="{{ route('packages.index') }}" @class([
                    'rounded-full px-5 py-2 text-[15px] font-semibold transition-colors',
                    'bg-brand text-white' => ! $current,
                    'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => $current,
                ]) @if (! $current) aria-current="page" @endif>{{ __('All') }}</a>
                @foreach ($services as $item)
                    <a href="{{ $item->url() }}" @class([
                        'rounded-full px-5 py-2 text-[15px] font-semibold transition-colors',
                        'bg-brand text-white' => $current?->is($item),
                        'bg-white text-ink-2 ring-1 ring-line hover:text-brand' => ! $current?->is($item),
                    ]) @if ($current?->is($item)) aria-current="page" @endif>{{ $item->getTranslation('name', $locale) }}</a>
                @endforeach
            </nav>
        @endif

        @if ($service && ($description = $service->getTranslation('description', $locale)))
            <div class="mb-12 max-w-3xl text-[1.0625rem] leading-relaxed whitespace-pre-line text-ink-2">{{ $description }}</div>
        @endif

        @if ($packages->isEmpty())
            <div class="card-site flex flex-col items-center px-6 py-16 text-center">
                <x-site.icon name="calendar" class="size-10 text-gold-deep" />
                <h2 class="mt-5 font-display text-display-sm text-brand">{{ __('New packages are being prepared') }}</h2>
                <p class="mt-3 max-w-md text-ink-2">{{ __('Call or message us and we will share the latest packages and prices with you.') }}</p>
            </div>
        @else
            <h2 class="sr-only">{{ __('Packages') }}</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($packages as $index => $package)
                    <x-site.package-card :package="$package" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                @endforeach
            </div>
        @endif
    </x-site.section>
</x-site.layout>

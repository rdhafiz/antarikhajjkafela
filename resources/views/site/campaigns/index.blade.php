@php
    $intro = __('Limited-time Hajj, Umrah and travel offers from Antarik.');
@endphp
<x-site.layout header="overlay">
    <x-site.page-header :eyebrow="__('Campaigns')" :title="__('Special offers')" :intro="$intro" />

    <x-site.section tone="sand" spacing="md">
        @if ($campaigns->isEmpty())
            <div class="card-site flex flex-col items-center px-6 py-16 text-center">
                <x-site.icon name="calendar" class="size-10 text-gold-deep" />
                <h2 class="mt-5 font-display text-display-sm text-brand">{{ __('No offers running right now') }}</h2>
                <p class="mt-3 max-w-md text-ink-2">{{ __('Call or message us and we will share the latest offers with you.') }}</p>
            </div>
        @else
            <div class="mx-auto grid max-w-6xl gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($campaigns as $page)
                    <x-site.campaign-card :page="$page" :image="$images[$page->seo['image'] ?? 0] ?? null" />
                @endforeach
            </div>
        @endif
    </x-site.section>
</x-site.layout>

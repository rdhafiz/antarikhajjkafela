@php
    use App\Support\Money;

    $locale = app()->getLocale();
    $slides = $items->map(fn ($item) => $item->type === 'video'
        ? ['type' => 'video', 'id' => $item->youtubeId(), 'caption' => $item->getTranslation('caption', $locale)]
        : ['type' => 'image', 'src' => $item->media->url('lg'), 'caption' => $item->getTranslation('caption', $locale) ?: $item->media->getTranslation('alt', $locale)]
    )->values();
    $photoCount = $items->where('type', 'image')->count();
    $videoCount = $items->where('type', 'video')->count();
@endphp
<x-site.layout header="overlay">
    <x-site.page-header :title="$title" :image="$cover" :intro="$album->getTranslation('description', $locale)" :eyebrow="App\Support\Dates::format($album->event_date, 'j F Y', $locale)">
        <p class="mt-6 flex flex-wrap gap-5 text-[14px] font-medium text-white/75">
            @if ($photoCount)
                <span class="inline-flex items-center gap-1.5"><x-site.icon name="image" class="size-4 text-gold" />{{ trans_choice(':count photo|:count photos', $photoCount, ['count' => Money::digits($photoCount)]) }}</span>
            @endif
            @if ($videoCount)
                <span class="inline-flex items-center gap-1.5"><x-site.icon name="play" class="size-4 text-gold" />{{ trans_choice(':count video|:count videos', $videoCount, ['count' => Money::digits($videoCount)]) }}</span>
            @endif
        </p>
    </x-site.page-header>

    <section class="section-light bg-sand-50 py-12 sm:py-16" x-data="lightbox(@js($slides))" @keydown.window="key($event)">
        <div class="container-site">
            <h2 class="sr-only">{{ __('Photos and videos') }}</h2>
            <ul class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
                @foreach ($items as $index => $item)
                    <li @class(['sm:col-span-2 sm:row-span-2' => $index === 0 && $items->count() >= 5])>
                        <button type="button" class="group relative block aspect-square w-full overflow-hidden rounded-xl bg-brand-950 focus-visible:ring-4 focus-visible:ring-gold/60 focus-visible:outline-none" @click="open({{ $index }})"
                                aria-label="{{ $item->type === 'video' ? __('Play video') : __('Open photo') }} {{ Money::digits($index + 1) }}{{ ($cap = $item->getTranslation('caption', $locale)) ? ': '.$cap : '' }}">
                            @if ($item->type === 'video')
                                <img src="https://i.ytimg.com/vi/{{ $item->youtubeId() }}/hqdefault.jpg" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full scale-[1.34] object-cover opacity-85 transition-transform duration-500 group-hover:scale-[1.4]">
                                <span class="absolute top-1/2 left-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-gold text-brand-950 shadow-xl transition-transform group-hover:scale-110"><x-site.icon name="play" class="ml-0.5 size-6 fill-current" /></span>
                            @else
                                <img
                                    src="{{ $item->media->url($index === 0 && $items->count() >= 5 ? 'md' : 'thumb') }}"
                                    @if ($index === 0 && $items->count() >= 5) srcset="{{ $item->media->srcset() }}" sizes="(min-width: 1024px) 50vw, (min-width: 640px) 66vw, 100vw" @endif
                                    alt=""
                                    @if ($index > 3) loading="lazy" @endif
                                    decoding="async"
                                    class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                >
                                <span class="absolute inset-0 flex items-center justify-center bg-brand-950/0 transition-colors group-hover:bg-brand-950/30" aria-hidden="true">
                                    <x-site.icon name="expand" class="size-7 text-white opacity-0 transition-opacity group-hover:opacity-100" />
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Lightbox. (Video thumbnails above are scaled to hide YouTube's letterbox bars.) --}}
        <template x-teleport="body">
            <div x-show="current !== null" x-cloak x-transition.opacity.duration.200ms x-trap.noscroll.inert="current !== null"
                 class="fixed inset-0 z-[100] flex flex-col bg-brand-950/97 text-white"
                 role="dialog" aria-modal="true" aria-label="{{ $title }}"
                 @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <p class="text-[14px] font-semibold text-white/70" aria-live="polite"><span x-text="position"></span></p>
                    <button type="button" class="flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" @click="close()" aria-label="{{ __('Close') }}"><x-site.icon name="x" class="size-5" /></button>
                </div>

                <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-20">
                    <template x-if="slide && slide.type === 'image'">
                        <img :src="slide.src" :alt="slide.caption || ''" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl">
                    </template>
                    <template x-if="slide && slide.type === 'video'">
                        <div class="aspect-video w-full max-w-5xl overflow-hidden rounded-xl bg-black">
                            <iframe class="size-full" :src="`https://www.youtube-nocookie.com/embed/${slide.id}?autoplay=1&rel=0`" :title="slide.caption || @js(__('Video'))" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    </template>

                    <button type="button" x-show="slides.length > 1" class="absolute top-1/2 left-2 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 sm:flex" @click="prev()" aria-label="{{ __('Previous') }}"><x-site.icon name="chevron-left" class="size-6 rtl:rotate-180" /></button>
                    <button type="button" x-show="slides.length > 1" class="absolute top-1/2 right-2 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 sm:flex" @click="next()" aria-label="{{ __('Next') }}"><x-site.icon name="chevron-right" class="size-6 rtl:rotate-180" /></button>
                </div>

                <div class="flex min-h-16 items-center justify-center gap-4 px-4 py-4 text-center">
                    <button type="button" x-show="slides.length > 1" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 sm:hidden" @click="prev()" aria-label="{{ __('Previous') }}"><x-site.icon name="chevron-left" class="size-5 rtl:rotate-180" /></button>
                    <p class="max-w-2xl flex-1 text-[15px] text-white/85" x-text="slide?.caption || ''"></p>
                    <button type="button" x-show="slides.length > 1" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 sm:hidden" @click="next()" aria-label="{{ __('Next') }}"><x-site.icon name="chevron-right" class="size-5 rtl:rotate-180" /></button>
                </div>
            </div>
        </template>
    </section>

    @if ($more->isNotEmpty())
        <section class="section-light py-16 sm:py-20" aria-labelledby="more-albums">
            <div class="container-site">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <h2 id="more-albums" class="sec-heading text-display-lg">{{ __('More albums') }}</h2>
                    <a href="{{ $galleryUrl }}" class="inline-flex items-center gap-1.5 font-semibold text-brand hover:underline">{{ __('Full gallery') }} <x-site.icon name="arrow-right" class="size-4 rtl:rotate-180" /></a>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($more as $item)
                        <x-site.album-card :album="$item" data-reveal />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-site.layout>

@php
    $count = $photos->count();
    // Five photos make a mosaic: one large tile beside four small ones.
    $mosaic = $count === 5;
@endphp
@if ($count)
    <x-site.block :style="$style" :section="$section">
        <x-site.block-heading :c="$c" class="mb-14" />
        <ul @class([
            'grid grid-cols-2 gap-2.5 sm:gap-3',
            'sm:grid-cols-3' => $count <= 3,
            'sm:grid-cols-4' => $count > 3 && $count !== 10,
            'sm:grid-cols-5' => $count === 10,
        ])>
            @foreach ($photos as $index => $photo)
                @php($big = $mosaic && $index === 0)
                <li @class(['col-span-2 row-span-2' => $big]) data-reveal style="--reveal-delay: {{ ($index % 5) * 60 }}ms">
                    <a href="{{ $photo->album->url() }}" class="group relative block aspect-square overflow-hidden rounded-xl bg-brand-950 focus-visible:ring-4 focus-visible:ring-gold/60 focus-visible:outline-none sm:h-full">
                        <img
                            src="{{ $photo->media->url($big ? 'md' : 'thumb') }}"
                            @if ($big) srcset="{{ $photo->media->srcset() }}" sizes="(min-width: 640px) 50vw, 100vw" @endif
                            alt="{{ $photo->getTranslation('caption', app()->getLocale()) ?: $photo->media->getTranslation('alt', app()->getLocale()) }}"
                            loading="lazy"
                            decoding="async"
                            class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-105"
                        >
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/85 to-transparent p-3 pt-10 text-[13px] font-semibold text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                            {{ $photo->album->getTranslation('title', app()->getLocale()) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($c['cta'])
            <div class="mt-12 text-center">
                <x-site.link-button :link="$c['cta']" :variant="$style['tone'] === 'dark' ? 'gold' : 'primary'" icon-right="arrow-right" />
            </div>
        @endif
    </x-site.block>
@endif

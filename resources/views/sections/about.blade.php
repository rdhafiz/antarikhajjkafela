@php($dark = $style['tone'] === 'dark')
<x-site.block :style="$style" :section="$section">
    <div class="grid items-center gap-14 lg:grid-cols-[0.95fr_1.05fr] lg:gap-20">
        {{-- Image collage --}}
        <div class="relative mx-auto w-full max-w-lg lg:max-w-none" data-reveal>
            @if ($c['image'])
                <img
                    src="{{ $c['image']->url('md') }}"
                    srcset="{{ $c['image']->srcset() }}"
                    sizes="(min-width: 1024px) 40vw, 90vw"
                    alt="{{ $c['image']->getTranslation('alt', app()->getLocale()) }}"
                    loading="lazy"
                    decoding="async"
                    class="aspect-[4/5] w-[82%] rounded-t-[999px] rounded-b-[1.75rem] object-cover shadow-[var(--shadow-lift)]"
                >
            @else
                <div class="relative flex aspect-[4/5] w-[82%] items-center justify-center overflow-hidden rounded-t-[999px] rounded-b-[1.75rem] bg-gradient-to-b from-brand to-brand-950" aria-hidden="true">
                    <div class="pattern-star absolute inset-0 opacity-[0.08]"></div>
                    <img src="{{ app(App\Support\Branding::class)->iconUrl(512) }}" alt="" width="512" height="512" loading="lazy" class="relative w-1/2 drop-shadow-2xl">
                </div>
            @endif

            @if ($c['image_secondary'])
                <img
                    src="{{ $c['image_secondary']->url('md') }}"
                    srcset="{{ $c['image_secondary']->srcset() }}"
                    sizes="(min-width: 1024px) 18vw, 42vw"
                    alt="{{ $c['image_secondary']->getTranslation('alt', app()->getLocale()) }}"
                    loading="lazy"
                    decoding="async"
                    class="absolute right-0 bottom-[-6%] aspect-square w-[46%] rounded-[1.5rem] border-[6px] border-white object-cover shadow-[var(--shadow-lift)]"
                >
            @endif

            @if (filled($c['years_value']))
                <div class="absolute top-[12%] right-[4%] rounded-2xl bg-brand px-5 py-4 text-center text-white shadow-[var(--shadow-lift)]">
                    <p class="font-display text-4xl leading-none font-semibold text-gold">{{ $c['years_value'] }}</p>
                    @if (filled($c['years_label']))<p class="mt-1.5 max-w-24 text-[12px] leading-tight text-white/75">{{ $c['years_label'] }}</p>@endif
                </div>
            @endif
        </div>

        <div data-reveal style="--reveal-delay: 100ms">
            <x-site.block-heading :c="$c" align="left" />
            @if (filled($c['body']))
                <div class="prose-site mt-6">{!! $c['body'] !!}</div>
            @endif
            @if (filled($c['quote']))
                <figure class="sec-card mt-8 p-6">
                    <x-site.icon name="quote" class="size-7 text-gold" />
                    <blockquote class="sec-heading mt-3 font-display text-[1.15rem] leading-relaxed">{{ $c['quote'] }}</blockquote>
                    @if (filled($c['quote_author']))<figcaption class="sec-muted mt-3 text-[14px] font-semibold">— {{ $c['quote_author'] }}</figcaption>@endif
                </figure>
            @endif
            <x-site.link-button :link="$c['link']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" class="mt-9" />
        </div>
    </div>
</x-site.block>

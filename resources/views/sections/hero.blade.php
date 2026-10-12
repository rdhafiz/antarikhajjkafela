@php
    $variant = $c['variant'] ?? 'split';
    $dark = $style['tone'] === 'dark';
    $slides = array_values(array_filter($c['slides'] ?? [], fn ($s) => $s['image']));
    $highlights = array_values(array_filter($c['highlights'] ?? [], fn ($h) => filled($h['value'])));
    // At the top of a page the hero fills the screen (home) or half of it, with parallax.
    $height = $first ? ($page->isHome() ? 'hero-full' : 'hero-half') : null;
@endphp

<x-site.block :style="$style" :section="$section" :container="false" :data-hero="$first ? true : null" @class([$first ? 'pt-20' : '', $height, 'flex flex-col' => $height, 'bg-gradient-to-br from-brand via-brand-900 to-brand-950' => ! $style['image'] && str_contains($style['class'], 'bg-brand-950')])>
    @unless ($style['image'] || ($variant === 'centered' && $c['image']) || $variant === 'slider')
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        <div class="absolute top-1/4 -right-48 -z-10 size-[620px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-56 -left-40 -z-10 size-[520px] rounded-full bg-emerald/20 blur-3xl" aria-hidden="true"></div>
    @endunless

    @if ($variant === 'slider' && $slides)
        <div
            x-data="{ i: 0, n: {{ count($slides) }}, timer: null,
                go(k) { this.i = (k + this.n) % this.n },
                start() { if (this.n > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => this.go(this.i + 1), 6500) },
                stop() { clearInterval(this.timer) } }"
            x-init="start()"
            @mouseenter="stop()" @mouseleave="start()" @focusin="stop()"
            @class(['relative min-h-[560px] sm:min-h-[620px]', 'flex-1' => $height, 'lg:min-h-[680px]' => ! $height])
            role="region" aria-roledescription="carousel" aria-label="{{ __('Highlights') }}"
        >
            @foreach ($slides as $index => $slide)
                <div
                    class="absolute inset-0 flex items-center transition-opacity duration-1000"
                    :class="i === {{ $index }} ? 'opacity-100 z-10' : 'opacity-0 pointer-events-none'"
                    @if ($index > 0) style="opacity:0" @endif
                    role="group" aria-roledescription="slide" aria-label="{{ $index + 1 }} / {{ count($slides) }}"
                    :aria-hidden="(i !== {{ $index }}).toString()"
                >
                    <img
                        src="{{ $slide['image']->url('lg') }}"
                        srcset="{{ $slide['image']->srcset() }}"
                        sizes="100vw"
                        alt="{{ $slide['image']->getTranslation('alt', app()->getLocale()) }}"
                        class="absolute inset-0 -z-20 size-full object-cover"
                        @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif
                    >
                    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-950/90 via-brand-950/65 to-brand-950/20" aria-hidden="true"></div>
                    <div class="container-site py-24 text-white">
                        <div class="max-w-2xl">
                            @if (filled($slide['eyebrow']))<p class="eyebrow eyebrow-light">{{ $slide['eyebrow'] }}</p>@endif
                            <{{ $index === 0 ? 'h1' : 'h2' }} @class(['text-display-xl text-white', 'mt-5' => filled($slide['eyebrow'])])>{{ $slide['title'] }}</{{ $index === 0 ? 'h1' : 'h2' }}>
                            @if (filled($slide['text']))<p class="mt-6 max-w-xl text-lg leading-relaxed text-white/80">{{ $slide['text'] }}</p>@endif
                            <x-site.link-button :link="$slide['link']" variant="gold" size="lg" icon-right="arrow-right" class="mt-9" />
                        </div>
                    </div>
                </div>
            @endforeach

            @if (count($slides) > 1)
                <div class="absolute inset-x-0 bottom-8 z-20">
                    <div class="container-site flex items-center gap-4">
                        <div class="flex gap-2">
                            @foreach ($slides as $index => $slide)
                                <button type="button" @click="go({{ $index }})" class="h-1.5 rounded-full transition-all duration-300"
                                        :class="i === {{ $index }} ? 'w-10 bg-gold' : 'w-5 bg-white/40 hover:bg-white/70'"
                                        aria-label="{{ __('Show slide :n', ['n' => $index + 1]) }}"></button>
                            @endforeach
                        </div>
                        <div class="ml-auto flex gap-2">
                            <button type="button" @click="go(i - 1)" class="flex size-11 items-center justify-center rounded-full border border-white/25 text-white hover:bg-white/10" aria-label="{{ __('Previous slide') }}"><x-site.icon name="chevron-left" /></button>
                            <button type="button" @click="go(i + 1)" class="flex size-11 items-center justify-center rounded-full border border-white/25 text-white hover:bg-white/10" aria-label="{{ __('Next slide') }}"><x-site.icon name="chevron-right" /></button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    @elseif ($variant === 'centered')
        @if ($c['image'])
            <div class="hero-media" data-parallax-bg aria-hidden="true">
                <img src="{{ $c['image']->url('lg') }}" srcset="{{ $c['image']->srcset() }}" sizes="100vw" alt="" fetchpriority="high">
            </div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/80 via-brand-950/70 to-brand-950/90" aria-hidden="true"></div>
        @endif
        <div @class(['container-site flex flex-col items-center justify-center py-24 text-center', 'flex-1' => $height, 'min-h-[560px] sm:min-h-[620px]' => ! $height])>
            <div class="hero-reveal max-w-3xl" data-parallax-content>
                @if (filled($c['eyebrow']))<p class="eyebrow eyebrow-light justify-center">{{ $c['eyebrow'] }}</p>@endif
                <h1 @class(['text-display-xl text-white', 'mt-5' => filled($c['eyebrow'])])>
                    {{ $c['title'] }}
                    @if (filled($c['highlight']))<span class="hero-line text-gold italic">{{ $c['highlight'] }}</span>@endif
                </h1>
                @if (filled($c['text']))<p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/80">{{ $c['text'] }}</p>@endif
                <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                    <x-site.link-button :link="$c['primary']" variant="gold" size="lg" icon-right="arrow-right" />
                    <x-site.link-button :link="$c['secondary']" variant="outline-light" size="lg" />
                </div>
            </div>
        </div>

    @else
        <div @class(['container-site grid items-center gap-14 py-20 sm:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:py-28', 'flex-1' => $height])>
            <div class="hero-reveal" data-parallax-content>
                @if (filled($c['eyebrow']))<p class="eyebrow">{{ $c['eyebrow'] }}</p>@endif
                <h1 @class(['sec-heading text-display-xl', 'mt-5' => filled($c['eyebrow'])])>
                    {{ $c['title'] }}
                    @if (filled($c['highlight']))<span @class(['hero-line italic', 'text-gold' => $dark, 'text-gold-deep' => ! $dark])>{{ $c['highlight'] }}</span>@endif
                </h1>
                @if (filled($c['text']))<p class="sec-text mt-6 max-w-xl text-lg leading-relaxed">{{ $c['text'] }}</p>@endif
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <x-site.link-button :link="$c['primary']" :variant="$dark ? 'gold' : 'primary'" size="lg" icon-right="arrow-right" />
                    <x-site.link-button :link="$c['secondary']" :variant="$dark ? 'outline-light' : 'outline'" size="lg" />
                </div>
            </div>

            <div class="hero-reveal relative mx-auto w-full max-w-md lg:max-w-none" data-parallax-bg>
                @if ($c['image'])
                    {{-- Arch-framed image, echoing the doorways of the Haram. --}}
                    <div class="relative">
                        <div class="absolute -inset-3 rounded-t-[999px] rounded-b-[2rem] border border-gold/40" aria-hidden="true"></div>
                        <img
                            src="{{ $c['image']->url('md') }}"
                            srcset="{{ $c['image']->srcset() }}"
                            sizes="(min-width: 1024px) 40vw, 90vw"
                            alt="{{ $c['image']->getTranslation('alt', app()->getLocale()) }}"
                            width="{{ $c['image']->variants['md']['width'] ?? 1024 }}"
                            height="{{ $c['image']->variants['md']['height'] ?? 1280 }}"
                            fetchpriority="high"
                            class="aspect-[4/5] w-full rounded-t-[999px] rounded-b-[1.75rem] object-cover shadow-[var(--shadow-lift)]"
                        >
                    </div>
                @else
                    <div class="relative mx-auto hidden aspect-square w-full max-w-md lg:block">
                        <div class="absolute inset-0 rotate-45 rounded-[3rem] border border-gold/30"></div>
                        <div class="absolute inset-8 rounded-[2.5rem] border border-white/10 bg-white/[0.04] backdrop-blur-sm"></div>
                        <img src="{{ app(App\Support\Branding::class)->iconUrl(512) }}" alt="" width="512" height="512" loading="lazy" class="absolute inset-[22%] size-[56%] object-contain drop-shadow-2xl">
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($highlights)
        <div class="relative z-10 border-t border-white/10 bg-brand-950/85 backdrop-blur-md">
            <dl @class(['container-site grid grid-cols-2 gap-y-6 py-8', ['sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-4'][min(4, count($highlights)) - 1]])>
                @foreach ($highlights as $item)
                    <div class="flex flex-col-reverse items-center text-center sm:border-l sm:border-white/10 sm:first:border-l-0">
                        <dt class="mt-1 text-[13px] tracking-wide text-white/60">{{ $item['label'] }}</dt>
                        <dd class="font-display text-3xl font-semibold text-gold">{{ App\Support\Money::digits($item['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</x-site.block>

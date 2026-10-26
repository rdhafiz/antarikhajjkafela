@php
    // "slider" was an older layout; its slides now follow the centered hero.
    $variant = ($c['variant'] ?? 'split') === 'slider' ? 'centered' : ($c['variant'] ?? 'split');
    $dark = $style['tone'] === 'dark';
    $highlights = array_values(array_filter($c['highlights'] ?? [], fn ($h) => filled($h['value'])));
    // At the top of a page the hero fills the screen (home) or three quarters of it (like other pages), with parallax.
    $height = $first ? ($page->isHome() ? 'hero-full' : 'hero-75') : null;

    // The centered hero is the first slide; extra slides make it a slider.
    $slides = [];
    if ($variant === 'centered') {
        $slides[] = ['type' => 'text', 'link' => null, ...Illuminate\Support\Arr::only($c, ['image', 'eyebrow', 'title', 'highlight', 'text', 'primary', 'secondary'])];
        foreach ($c['slides'] ?? [] as $slide) {
            // Either image stands in for a missing other one; an image-only slide without
            // any image is skipped, a text slide without one shows the brand background.
            $wide = $slide['image'] ?? null;
            $tall = $slide['image_mobile'] ?? null;
            $only = ($slide['type'] ?? 'text') === 'image';
            if ($wide || $tall || (! $only && filled($slide['title'] ?? null))) {
                $slides[] = [...$slide, 'type' => $only ? 'image' : 'text', 'image' => $wide ?? $tall, 'image_mobile' => $only && $wide && $tall ? $tall : null];
            }
        }
    }
    $isSlider = count($slides) > 1;
@endphp

<x-site.block :style="$style" :section="$section" :container="false" :data-hero="$first ? true : null" @class([$first ? 'pt-20' : '', $height, 'flex flex-col' => $height, 'bg-gradient-to-br from-brand via-brand-900 to-brand-950' => ! $style['image'] && str_contains($style['class'], 'bg-brand-950')])>
    @unless ($style['image'] || ($variant === 'centered' && $c['image']) || $isSlider)
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
        <div class="absolute top-1/4 -right-48 -z-10 size-[620px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-56 -left-40 -z-10 size-[520px] rounded-full bg-emerald/20 blur-3xl" aria-hidden="true"></div>
    @endunless

    @if ($isSlider)
        <div
            x-data="heroSlider({{ count($slides) }})"
            @class(['hs relative min-h-[560px] sm:min-h-[620px]', '-mt-20' => $first, 'flex-1' => $height, 'lg:min-h-[680px]' => ! $height])
            role="region" aria-roledescription="carousel" aria-label="{{ __('Highlights') }}"
            @pointerenter="$event.pointerType === 'mouse' && hold('hover', true)" @pointerleave="hold('hover', false)"
            @focusin="hold('focus', $event.target.matches(':focus-visible'))" @focusout="hold('focus', $el.contains($event.relatedTarget) && $event.relatedTarget.matches(':focus-visible'))"
            @keydown.left="step(-1)" @keydown.right="step(1)"
            @touchstart.passive="swipeStart($event)" @touchend.passive="swipeEnd($event)"
        >
            @foreach ($slides as $index => $slide)
                @php
                    $image = $slide['image'];
                    $alt = $image?->getTranslation('alt', app()->getLocale());
                    $heading = $index === 0 ? 'h1' : 'h2';
                @endphp
                <div
                    @class(['hs-slide', 'is-active' => $index === 0])
                    :class="{ 'is-active': i === {{ $index }}, 'is-leaving': leaving === {{ $index }} }"
                    x-effect="$el.inert = i !== {{ $index }}"
                    @if ($index > 0) inert @endif
                    role="group" aria-roledescription="slide" aria-label="{{ __(':n of :total', ['n' => $index + 1, 'total' => count($slides)]) }}"
                >
                    @if ($slide['type'] === 'image' || ! $image)
                        {{-- Same backdrop as the footer, so space around a full image looks intended. --}}
                        <div class="absolute inset-0 -z-20 bg-gradient-to-b from-brand-900 to-brand-950" aria-hidden="true">
                            <div class="pattern-star absolute inset-0 opacity-[0.045]"></div>
                            <div class="absolute -top-40 right-[-10%] size-[520px] rounded-full bg-gold/10 blur-3xl"></div>
                        </div>
                    @endif

                    @if ($slide['type'] === 'image')
                        {{-- Image only: shown whole, without parallax. --}}
                        @php $tag = $slide['link'] ? 'a' : 'div'; @endphp
                        <{{ $tag }}
                            @if ($slide['link']) href="{{ $slide['link']['url'] }}" @if ($slide['link']['external']) target="_blank" rel="noopener" @endif aria-label="{{ $slide['link']['label'] ?: ($alt ?: __('Open')) }}" @endif
                            @class(['hs-full absolute inset-0 flex items-center justify-center px-4 pb-24 sm:px-10 sm:pb-28', 'pt-24' => $first, 'pt-8' => ! $first])
                        >
                            {{-- Phones get the vertical image when there is one. --}}
                            <picture class="contents">
                                @if ($slide['image_mobile'])
                                    <source media="(max-width: 767px)" srcset="{{ $slide['image_mobile']->srcset() ?: $slide['image_mobile']->url() }}" sizes="100vw">
                                @endif
                                <img
                                    src="{{ $image->url('lg') }}"
                                    srcset="{{ $image->srcset() }}"
                                    sizes="100vw"
                                    alt="{{ $slide['link'] ? '' : $alt }}"
                                    class="size-full object-contain"
                                    @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif
                                >
                            </picture>
                        </{{ $tag }}>
                    @else
                        @if ($image)
                            <div class="hero-media" data-parallax-bg aria-hidden="true">
                                <img src="{{ $image->url('lg') }}" srcset="{{ $image->srcset() }}" sizes="100vw" alt="" @if ($index === 0) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                            </div>
                            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/80 via-brand-950/70 to-brand-950/90" aria-hidden="true"></div>
                        @endif
                        <div @class(['container-site flex h-full flex-col items-center justify-center pb-28 text-center', 'pt-32' => $first, 'pt-16' => ! $first])>
                            <div class="max-w-3xl" data-parallax-content>
                                @if (filled($slide['eyebrow']))<p class="hs-line eyebrow eyebrow-light justify-center" style="--i: 0">{{ $slide['eyebrow'] }}</p>@endif
                                @if (filled($slide['title']) || filled($slide['highlight']))
                                    <{{ $heading }} @class(['text-display-xl text-white', 'mt-5' => filled($slide['eyebrow'])])>
                                        @if (filled($slide['title']))<span class="hs-line block" style="--i: 1">{{ $slide['title'] }}</span>@endif
                                        @if (filled($slide['highlight']))<span class="hs-line block text-gold italic" style="--i: 2">{{ $slide['highlight'] }}</span>@endif
                                    </{{ $heading }}>
                                @endif
                                @if (filled($slide['text']))<p class="hs-line mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/80" style="--i: 3">{{ $slide['text'] }}</p>@endif
                                @if ($slide['primary'] || $slide['secondary'])
                                    <div class="hs-line mt-9 flex flex-col justify-center gap-3 sm:flex-row" style="--i: 4">
                                        <x-site.link-button :link="$slide['primary']" variant="gold" size="lg" icon-right="arrow-right" />
                                        <x-site.link-button :link="$slide['secondary']" variant="outline-light" size="lg" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Controls: dots showing the time left on the current slide, pause, previous and next. --}}
            <div class="absolute inset-x-0 bottom-6 z-10 sm:bottom-8">
                <div class="container-site flex items-center gap-3">
                    <div class="flex items-center">
                        @foreach ($slides as $index => $slide)
                            <button type="button" @click="go({{ $index }})" @class(['hs-dot', 'is-current' => $index === 0]) :class="{ 'is-current': i === {{ $index }} }" @if ($index === 0) aria-current="true" @endif :aria-current="i === {{ $index }} ? 'true' : null" aria-label="{{ __('Show slide :n', ['n' => $index + 1]) }}">
                                <span class="hs-dot-track"><span class="hs-dot-fill"></span></span>
                            </button>
                        @endforeach
                    </div>
                    <div class="ml-auto flex gap-2">
                        <button type="button" x-show="auto" x-cloak @click="toggle()" class="hs-btn" :aria-label="paused ? @js(__('Play slideshow')) : @js(__('Pause slideshow'))">
                            <span x-show="!paused"><x-site.icon name="pause" class="size-4" /></span>
                            <span x-show="paused" x-cloak><x-site.icon name="play" class="size-4" /></span>
                        </button>
                        <button type="button" @click="step(-1)" class="hs-btn" aria-label="{{ __('Previous slide') }}"><x-site.icon name="chevron-left" /></button>
                        <button type="button" @click="step(1)" class="hs-btn" aria-label="{{ __('Next slide') }}"><x-site.icon name="chevron-right" /></button>
                    </div>
                </div>
            </div>
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
                        <dd class="font-display text-3xl font-semibold text-gold" data-count>{{ App\Support\Money::digits($item['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</x-site.block>

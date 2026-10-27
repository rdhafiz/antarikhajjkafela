{{-- One custom section of a package page. Expects $s (localized section) and $index. --}}
@php
    $twoColumn = ($s['layout'] ?? 'full') === 'two_column';
    $image = $s['image'];
    $bg = $s['background_color'];
    $text = $s['text_color'];
    $hasBgImage = ! $twoColumn && $image;
    $hasHeading = filled($s['badge']) || filled($s['heading']) || filled($s['subheading']);
    // Full-width sections without an image: heading on the left, content across the rest.
    $split = ! $twoColumn && $hasHeading && $s['blocks'];

    // Dark when there is a background image (overlay), or a dark background color.
    $isDark = $hasBgImage || ($bg && (function ($hex) {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150;
    })($bg));
    $palette = $text ? "--sec-heading: {$text}; --sec-text: {$text}; --sec-muted: {$text};" : '';
    $style = trim(($bg && ! $hasBgImage ? "background-color: {$bg}; " : '').$palette);
    // Without a colour or image of its own, each section takes the next soft shade and floating shapes.
    $shade = ! $bg && ! $hasBgImage ? ['bg-mint-soft', 'bg-white', 'bg-peach-soft', 'bg-lilac-soft'][$index % 4] : null;
@endphp
<section
    @if (filled($s['heading'])) id="details-{{ $index + 1 }}" @endif
    @class(['relative isolate scroll-mt-36 overflow-clip py-16 sm:py-20 lg:py-24', 'section-dark' => $isDark, 'section-light' => ! $isDark, $shade => $shade !== null])
    @if ($style) style="{{ $style }}" @endif
>
    @if ($hasBgImage)
        <img src="{{ $image->url('lg') }}" srcset="{{ $image->srcset() }}" sizes="100vw" alt="" loading="lazy" decoding="async" class="absolute inset-0 -z-20 size-full object-cover">
        <div class="absolute inset-0 -z-10" style="background-color: {{ $bg ?: '#06182e' }}; opacity: .82" aria-hidden="true"></div>
    @endif

    @if ($shade)
        <x-site.decor />
    @endif

    <div class="container-site">
        <div @class([
            'grid items-center gap-12 lg:grid-cols-2 lg:gap-16' => $twoColumn && $image,
            'grid gap-8 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)] lg:gap-16' => $split,
            'mx-auto max-w-4xl' => ! ($twoColumn && $image) && ! $split,
        ])>
            @if ($split)
                <div class="lg:sticky lg:top-40 lg:self-start" data-reveal>
                    @if (filled($s['badge']))
                        <span class="inline-flex rounded-full bg-gold px-3.5 py-1 text-[12px] font-bold tracking-wide text-brand-950 uppercase">{{ $s['badge'] }}</span>
                    @endif
                    @if (filled($s['heading']))
                        <h2 @class(['sec-heading text-display-md', 'mt-4' => filled($s['badge'])])>{{ $s['heading'] }}</h2>
                    @endif
                    @if (filled($s['subheading']))
                        <p class="sec-text mt-3 text-[1.0625rem] leading-relaxed">{{ $s['subheading'] }}</p>
                    @endif
                    @if ($s['cta'])
                        <x-site.link-button :link="$s['cta']" :variant="$isDark ? 'gold' : 'primary'" icon-right="arrow-right" class="mt-8" />
                    @endif
                </div>
                <div class="pkg-blocks min-w-0" data-reveal-items="li, .prose-site > *, figure, iframe">
                    @include('partials.content-blocks', ['blocks' => $s['blocks']])
                </div>
            @else
            <div @class(['lg:order-2' => $twoColumn && ($s['image_position'] ?? 'right') === 'left'])>
                <div data-reveal>
                    @if (filled($s['badge']))
                        <span class="inline-flex rounded-full bg-gold px-3.5 py-1 text-[12px] font-bold tracking-wide text-brand-950 uppercase">{{ $s['badge'] }}</span>
                    @endif
                    @if (filled($s['heading']))
                        <h2 @class(['sec-heading text-display-md', 'mt-4' => filled($s['badge'])])>{{ $s['heading'] }}</h2>
                    @endif
                    @if (filled($s['subheading']))
                        <p class="sec-text mt-3 text-[1.0625rem] leading-relaxed">{{ $s['subheading'] }}</p>
                    @endif
                </div>
                @if ($s['blocks'])
                    <div @class(['mt-8' => filled($s['heading']) || filled($s['subheading'])]) data-reveal-items="li, .prose-site > *, figure, iframe">
                        @include('partials.content-blocks', ['blocks' => $s['blocks']])
                    </div>
                @endif
                @if ($s['cta'])
                    <x-site.link-button :link="$s['cta']" :variant="$isDark ? 'gold' : 'primary'" icon-right="arrow-right" class="mt-8" />
                @endif
            </div>

            @endif

            @if ($twoColumn && $image)
                <div @class(['lg:order-1' => ($s['image_position'] ?? 'right') === 'left'])>
                    <img
                        src="{{ $image->url('md') }}"
                        srcset="{{ $image->srcset() }}"
                        sizes="(min-width: 1024px) 45vw, 100vw"
                        alt="{{ $image->getTranslation('alt', app()->getLocale()) }}"
                        loading="lazy"
                        decoding="async"
                        class="aspect-[4/3] w-full rounded-[1.75rem] object-cover shadow-[var(--shadow-lift)]"
                    >
                </div>
            @endif
        </div>
    </div>
</section>

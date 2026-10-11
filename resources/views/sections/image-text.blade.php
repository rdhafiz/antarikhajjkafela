@php
    $dark = $style['tone'] === 'dark';
    $imageLeft = ($c['image_position'] ?? 'right') === 'left';
    $bullets = array_filter($c['bullets'] ?? [], fn ($b) => filled($b['text']));
@endphp
<x-site.block :style="$style" :section="$section">
    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div @class(['lg:order-2' => $imageLeft]) data-reveal>
            <x-site.block-heading :c="$c" align="left" />
            @if (filled($c['body']))
                <div class="prose-site mt-6">{!! $c['body'] !!}</div>
            @endif
            @if ($bullets)
                <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                    @foreach ($bullets as $bullet)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-3.5" /></span>
                            <span class="sec-text">{{ $bullet['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-9 flex flex-col gap-3 empty:hidden sm:flex-row">
                <x-site.link-button :link="$c['primary']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
                <x-site.link-button :link="$c['secondary']" :variant="$dark ? 'outline-light' : 'outline'" />
            </div>
        </div>

        @if ($c['image'])
            <div @class(['relative', 'lg:order-1' => $imageLeft]) data-reveal style="--reveal-delay: 100ms">
                <div @class(['absolute -bottom-4 size-40 rounded-[2rem] bg-gold/25 -z-10', '-left-4' => ! $imageLeft, '-right-4' => $imageLeft]) aria-hidden="true"></div>
                <img
                    src="{{ $c['image']->url('md') }}"
                    srcset="{{ $c['image']->srcset() }}"
                    sizes="(min-width: 1024px) 45vw, 100vw"
                    alt="{{ $c['image']->getTranslation('alt', app()->getLocale()) }}"
                    loading="lazy"
                    decoding="async"
                    class="aspect-[4/3] w-full rounded-[1.75rem] object-cover shadow-[var(--shadow-lift)]"
                >
                @if (filled($c['badge']))
                    <span @class(['absolute top-5 rounded-full bg-brand px-4 py-2 text-[13px] font-semibold text-white shadow-lg', 'right-5' => ! $imageLeft, 'left-5' => $imageLeft])>{{ $c['badge'] }}</span>
                @endif
            </div>
        @endif
    </div>
</x-site.block>

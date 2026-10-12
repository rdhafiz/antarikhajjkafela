@php
    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => filled($i['title'])));
    $layout = $c['layout'] ?? 'cards';
    $columns = (string) ($c['columns'] ?? '3');
    $dark = $style['tone'] === 'dark';
    $sticky = $layout === 'sticky';
    $grid = $sticky
        ? 'sm:grid-cols-2'
        : (['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][$columns] ?? 'sm:grid-cols-2 lg:grid-cols-3');
    $cards = in_array($layout, ['cards', 'sticky'], true);
@endphp
<x-site.block :style="$style" :section="$section">
    @if ($layout === 'numbered')
        {{-- Editorial rows: a large outlined number, the title, then the text. --}}
        <x-site.block-heading :c="$c" class="mb-14" />
        <ol class="border-b border-[var(--sec-border)]">
            @foreach ($items as $index => $item)
                <li class="group grid gap-x-10 gap-y-3 border-t border-[var(--sec-border)] py-8 sm:py-10 md:grid-cols-[8rem_minmax(0,0.9fr)_minmax(0,1.1fr)] md:items-baseline" data-reveal="left" style="--reveal-delay: {{ $index * 90 }}ms">
                    <span class="numeral-outline font-display text-[3.5rem] leading-none font-semibold sm:text-[4.5rem]" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="sec-heading text-display-sm">{{ $item['title'] }}</h3>
                    @if (filled($item['text']))<p class="sec-text leading-relaxed">{{ $item['text'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    @else
        <div @class(['grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16' => $sticky])>
            <div @class(['lg:sticky lg:top-28 lg:self-start' => $sticky])>
                <x-site.block-heading :c="$c" :align="$sticky ? 'left' : 'center'" @class(['mb-14' => ! $sticky]) />
                @if ($sticky && $c['cta'])
                    <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" class="mt-9 hidden lg:inline-flex" />
                @endif
            </div>

            <div @class(['grid gap-5', $grid, 'gap-x-10 gap-y-9' => ! $cards])>
                @foreach ($items as $index => $item)
                    @php($tag = $item['link'] ? 'a' : 'div')
                    <{{ $tag }}
                        @if ($item['link']) href="{{ $item['link']['url'] }}" @if ($item['link']['external']) target="_blank" rel="noopener" @endif @endif
                        @class([
                            'group relative block',
                            'sec-card p-7 transition-[box-shadow,border-color] duration-300' => $cards,
                            'hover:shadow-[var(--shadow-lift)]' => $cards && $item['link'],
                            'flex gap-5' => $layout === 'list',
                        ])
                        @if ($sticky) data-reveal="{{ $index % 2 ? 'right' : 'up' }}" @else data-reveal @endif
                        style="--reveal-delay: {{ ($index % ($sticky ? 2 : 4)) * 90 }}ms"
                    >
                        @if ($item['icon'])
                            <span @class(['sec-icon shrink-0 transition-transform duration-500 group-hover:-rotate-6', 'size-14' => $cards, 'size-12' => $layout === 'list'])>
                                <x-site.icon :name="$item['icon']" class="size-6" />
                            </span>
                        @endif

                        <div @class(['min-w-0', 'mt-6' => $layout !== 'list' && $item['icon']])>
                            <h3 class="sec-heading text-display-sm">{{ $item['title'] }}</h3>
                            @if (filled($item['text']))<p class="sec-text mt-3 leading-relaxed">{{ $item['text'] }}</p>@endif
                            @if ($item['link'] && filled($item['link']['label']))
                                <span @class(['mt-5 inline-flex items-center gap-1.5 text-[15px] font-semibold', 'text-gold' => $dark, 'text-brand' => ! $dark])>
                                    {{ $item['link']['label'] }}
                                    <x-site.icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1" />
                                </span>
                            @endif
                        </div>
                    </{{ $tag }}>
                @endforeach
            </div>
        </div>
    @endif

    @if ($c['cta'])
        <div @class(['mt-12 text-center', 'lg:hidden' => $sticky])>
            <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
        </div>
    @endif
</x-site.block>

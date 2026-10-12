@php
    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => filled($i['title'])));
    $layout = $c['layout'] ?? 'cards';
    $columns = (string) ($c['columns'] ?? '3');
    $dark = $style['tone'] === 'dark';
    $sticky = $layout === 'sticky';
    $grid = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][$columns] ?? 'sm:grid-cols-2 lg:grid-cols-3';
    $cards = $layout === 'cards';
@endphp
<x-site.block :style="$style" :section="$section">
    @if ($layout === 'numbered')
        {{-- Editorial rows: a large outlined number, the title, then the text. --}}
        <x-site.block-heading :c="$c" class="mb-14" />
        <ol class="border-b border-[var(--sec-border)]">
            @foreach ($items as $index => $item)
                <li class="numbered-row group relative grid gap-x-10 gap-y-3 border-t border-[var(--sec-border)] px-4 py-8 sm:px-8 sm:py-10 md:grid-cols-[8rem_minmax(0,0.9fr)_minmax(0,1.1fr)] md:items-baseline" data-reveal="left" style="--reveal-delay: {{ $index * 90 }}ms">
                    <span class="numeral-outline font-display text-[3.5rem] leading-none font-semibold sm:text-[4.5rem]" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="numbered-row-title sec-heading text-display-sm">{{ $item['title'] }}</h3>
                    @if (filled($item['text']))<p class="numbered-row-text sec-text leading-relaxed">{{ $item['text'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    @elseif ($sticky)
        {{-- Split heading across the full width, then one connected grid of compact cells. --}}
        <div class="grid gap-x-16 gap-y-6 border-b border-[var(--sec-border)] pb-10 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:items-end" data-reveal>
            <div>
                @if (filled($c['eyebrow']))<p class="eyebrow">{{ $c['eyebrow'] }}</p>@endif
                @if (filled($c['title']))
                    <h2 @class(['sec-heading text-display-lg max-w-3xl text-balance', 'mt-4' => filled($c['eyebrow'])])>{{ $c['title'] }}</h2>
                @endif
            </div>
            @if (filled($c['intro']) || $c['cta'])
                <div class="flex flex-col items-start gap-6 lg:pb-1.5">
                    @if (filled($c['intro']))<p class="sec-text text-[1.0625rem] leading-relaxed">{{ $c['intro'] }}</p>@endif
                    @if ($c['cta'])
                        <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-10 overflow-hidden rounded-[var(--radius-card)] border border-[var(--sec-border)] bg-[var(--sec-surface)]" data-reveal="scale">
            <ul class="-mr-px -mb-px grid grid-cols-1 sm:grid-cols-2 {{ $columns === '3' ? 'lg:grid-cols-3' : ($columns === '2' ? '' : 'lg:grid-cols-4') }}">
                @foreach ($items as $index => $item)
                    <li class="feature-cell group relative flex items-start gap-4 border-r border-b border-[var(--sec-border)] p-5 sm:flex-col sm:items-stretch sm:gap-0 sm:p-6 lg:p-7">
                        <div class="flex shrink-0 items-center justify-between gap-4">
                            @if ($item['icon'])
                                <span class="sec-icon size-11 shrink-0 rounded-xl transition-[rotate] duration-500 group-hover:-rotate-6">
                                    <x-site.icon :name="$item['icon']" class="size-5" />
                                </span>
                            @endif
                            <span class="sec-muted hidden font-display text-[15px] font-semibold tabular-nums sm:inline" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <h3 class="sec-heading font-display text-[1.125rem] leading-snug font-semibold sm:mt-5 sm:text-[1.25rem]">
                                @if ($item['link'])
                                    <a href="{{ $item['link']['url'] }}" @if ($item['link']['external']) target="_blank" rel="noopener" @endif class="after:absolute after:inset-0 focus-visible:outline-none">{{ $item['title'] }}</a>
                                @else
                                    {{ $item['title'] }}
                                @endif
                            </h3>
                            @if (filled($item['text']))<p class="sec-text mt-1.5 text-[15px] leading-relaxed sm:mt-2">{{ $item['text'] }}</p>@endif
                            @if ($item['link'] && filled($item['link']['label']))
                                <span @class(['mt-auto inline-flex items-center gap-1.5 pt-4 text-[14px] font-semibold', 'text-gold' => $dark, 'text-brand' => ! $dark])>
                                    {{ $item['link']['label'] }}
                                    <x-site.icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1 rtl:rotate-180" />
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div>
            <x-site.block-heading :c="$c" class="mb-14" />
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
                        data-reveal
                        style="--reveal-delay: {{ ($index % 4) * 90 }}ms"
                    >
                        @if ($item['icon'])
                            <span @class(['sec-icon shrink-0 transition-[rotate] duration-500 group-hover:-rotate-6', 'size-14' => $cards, 'size-12' => $layout === 'list'])>
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

    @if ($c['cta'] && ! $sticky)
        <div class="mt-12 text-center">
            <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
        </div>
    @endif
</x-site.block>

@php
    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => filled($i['title'])));
    $layout = $c['layout'] ?? 'cards';
    $columns = (string) ($c['columns'] ?? '3');
    $dark = $style['tone'] === 'dark';
    $grid = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][$columns] ?? 'sm:grid-cols-2 lg:grid-cols-3';
@endphp
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-14" />

    <div @class(['grid gap-5', $grid, 'gap-x-10 gap-y-9' => $layout !== 'cards'])>
        @foreach ($items as $index => $item)
            @php($tag = $item['link'] ? 'a' : 'div')
            <{{ $tag }}
                @if ($item['link']) href="{{ $item['link']['url'] }}" @if ($item['link']['external']) target="_blank" rel="noopener" @endif @endif
                @class([
                    'group relative block',
                    'sec-card p-7 transition-[transform,box-shadow,border-color] duration-300' => $layout === 'cards',
                    'hover:-translate-y-1 hover:shadow-[var(--shadow-lift)]' => $layout === 'cards' && $item['link'],
                    'flex gap-5' => $layout === 'list',
                ])
                data-reveal style="--reveal-delay: {{ ($index % 4) * 70 }}ms"
            >
                @if ($layout === 'numbered')
                    <span class="font-display text-[2.75rem] leading-none font-semibold text-gold/80">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                @elseif ($item['icon'])
                    <span @class(['sec-icon shrink-0', 'size-14' => $layout === 'cards', 'size-12' => $layout === 'list'])>
                        <x-site.icon :name="$item['icon']" class="size-6" />
                    </span>
                @endif

                <div @class(['min-w-0', 'mt-6' => $layout !== 'list' && ($item['icon'] || $layout === 'numbered')])>
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

    @if ($c['cta'])
        <div class="mt-12 text-center">
            <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
        </div>
    @endif
</x-site.block>

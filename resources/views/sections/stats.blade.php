@php
    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => $i['value'] !== null));
    $locale = app()->getLocale();
@endphp
<x-site.block :style="$style" :section="$section">
    @unless ($style['image'])
        <div class="pattern-star pointer-events-none absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true"></div>
    @endunless
    <x-site.block-heading :c="$c" class="mb-12" />
    <dl @class(['grid gap-6 sm:gap-8', 'grid-cols-2' => count($items) !== 1, 'lg:grid-cols-3' => count($items) === 3 || count($items) === 6, 'lg:grid-cols-4' => count($items) === 4, 'lg:grid-cols-5' => count($items) === 5])>
        @foreach ($items as $index => $item)
            <div class="sec-card flex flex-col-reverse items-center px-4 py-7 text-center" data-reveal style="--reveal-delay: {{ $index * 80 }}ms">
                <dt class="sec-muted mt-2 text-[14px] leading-snug font-medium">{{ $item['label'] }}</dt>
                <dd class="font-display text-[2.6rem] leading-none font-semibold text-gold">
                    <span
                        x-data="{ shown: 0, target: {{ (int) $item['value'] }}, locale: @js($locale === 'bn' ? 'bn-BD' : 'en-US') }"
                        x-intersect.once="
                            if (matchMedia('(prefers-reduced-motion: reduce)').matches) { shown = target; return }
                            const t0 = performance.now(), d = 1400;
                            const tick = (t) => { const p = Math.min(1, (t - t0) / d); shown = Math.round(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick) };
                            requestAnimationFrame(tick)"
                        x-text="shown.toLocaleString(locale)"
                    >{{ number_format((int) $item['value']) }}</span>{{ $item['suffix'] }}
                </dd>
                @if ($item['icon'])
                    <x-site.icon :name="$item['icon']" class="mb-4 size-7 text-gold" />
                @endif
            </div>
        @endforeach
    </dl>
</x-site.block>

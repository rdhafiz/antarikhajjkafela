@php($steps = array_values(array_filter($c['steps'] ?? [], fn ($s) => filled($s['title']))))
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-16" />

    <ol data-scroll-progress @class(['relative grid gap-10', 'md:grid-cols-2' => count($steps) >= 2, 'lg:grid-cols-4' => count($steps) === 4 || count($steps) >= 7, 'lg:grid-cols-3' => in_array(count($steps), [3, 5, 6], true)])>
        {{-- Connecting line on wide screens --}}
        <div class="absolute top-8 right-[12%] left-[12%] hidden h-0.5 rounded-full bg-[var(--sec-border)] lg:block" aria-hidden="true">
            <div class="h-full origin-left rounded-full bg-gold" style="transform: scaleX(var(--progress, 1))"></div>
        </div>

        @foreach ($steps as $index => $step)
            {{-- Each step fills with gold as the line reaches it (--at: its place along the line). --}}
            <li class="relative text-center" data-reveal="scale" style="--reveal-delay: {{ $index * 140 }}ms; --at: {{ count($steps) > 1 ? round($index / (count($steps) - 1) * 0.9, 3) : 0 }}">
                <div class="relative mx-auto flex size-16 items-center justify-center">
                    <span class="absolute inset-0 rotate-45 rounded-2xl border border-gold/50 bg-[var(--sec-surface)]"></span>
                    <span class="absolute inset-0 rotate-45 rounded-2xl bg-gold transition-opacity duration-300" style="opacity: clamp(0, calc((var(--progress, 1) - var(--at)) * 10 + 1), 1)" aria-hidden="true"></span>
                    @if ($step['icon'])
                        <x-site.icon :name="$step['icon']" class="sec-heading relative size-7" />
                    @else
                        <span class="sec-heading relative font-display text-2xl font-semibold">{{ $index + 1 }}</span>
                    @endif
                    <span class="absolute -top-2 -right-2 flex size-7 items-center justify-center rounded-full bg-gold text-[12px] font-bold text-brand-950">{{ $index + 1 }}</span>
                </div>
                <h3 class="sec-heading mt-7 text-display-sm">{{ $step['title'] }}</h3>
                @if (filled($step['text']))<p class="sec-text mx-auto mt-3 max-w-xs leading-relaxed">{{ $step['text'] }}</p>@endif
            </li>
        @endforeach
    </ol>
</x-site.block>

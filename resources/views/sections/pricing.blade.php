@php($plans = array_values(array_filter($c['plans'] ?? [], fn ($p) => filled($p['name']))))
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-14" />

    <div @class(['mx-auto grid items-stretch gap-6', 'max-w-md' => count($plans) === 1, 'max-w-4xl md:grid-cols-2' => count($plans) === 2, 'md:grid-cols-2 lg:grid-cols-3' => count($plans) === 3, 'md:grid-cols-2 xl:grid-cols-4' => count($plans) >= 4])>
        @foreach ($plans as $index => $plan)
            <div @class([
                'relative flex flex-col rounded-[1.5rem] p-7 sm:p-8',
                'bg-brand text-white shadow-[var(--shadow-lift)] ring-2 ring-gold lg:-translate-y-3' => $plan['featured'],
                'sec-card' => ! $plan['featured'],
            ]) data-reveal style="--reveal-delay: {{ $index * 80 }}ms">
                @if (filled($plan['badge']))
                    <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gold px-4 py-1 text-[12px] font-bold tracking-wide whitespace-nowrap text-brand-950 uppercase">{{ $plan['badge'] }}</span>
                @endif
                <h3 @class(['font-display text-[1.4rem] font-semibold', 'text-white' => $plan['featured'], 'sec-heading' => ! $plan['featured']])>{{ $plan['name'] }}</h3>
                @if (filled($plan['price']))
                    <p class="mt-5 flex items-baseline gap-2">
                        <span @class(['font-display text-[2.4rem] leading-none font-semibold', 'text-gold' => $plan['featured'], 'sec-heading' => ! $plan['featured']])>{{ $plan['price'] }}</span>
                        @if (filled($plan['note']))<span @class(['text-[14px]', 'text-white/65' => $plan['featured'], 'sec-muted' => ! $plan['featured']])>{{ $plan['note'] }}</span>@endif
                    </p>
                @endif
                @if (filled($plan['features']))
                    <ul class="mt-7 flex-1 space-y-3 text-[15px]">
                        @foreach (preg_split('/\r?\n/', $plan['features']) as $line)
                            @if (trim($line) !== '')
                                <li class="flex gap-3">
                                    <x-site.icon name="check" @class(['mt-1 size-4 shrink-0', 'text-gold' => $plan['featured'], 'text-emerald' => ! $plan['featured']]) />
                                    <span @class(['text-white/85' => $plan['featured'], 'sec-text' => ! $plan['featured']])>{{ trim($line) }}</span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
                <x-site.link-button :link="$plan['link']" :variant="$plan['featured'] ? 'gold' : 'primary'" class="mt-8 w-full" />
            </div>
        @endforeach
    </div>

    @if (filled($c['footnote']))
        <p class="sec-muted mx-auto mt-10 max-w-2xl text-center text-[14px]">{{ $c['footnote'] }}</p>
    @endif
</x-site.block>

@php
    $members = array_values(array_filter($c['members'] ?? [], fn ($m) => filled($m['name'])));
    // Members per row on wide screens: all in one row up to five, then rows of four (three for six or nine).
    $perRow = count($members) <= 5 ? max(2, count($members)) : (count($members) % 3 === 0 && count($members) % 4 !== 0 ? 3 : 4);
    $width = [
        2 => 'lg:w-[calc(50%-0.75rem)]',
        3 => 'lg:w-[calc(33.333%-1rem)]',
        4 => 'lg:w-[calc(25%-1.125rem)]',
        5 => 'lg:w-[calc(20%-1.2rem)]',
    ][$perRow];
@endphp
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-14" />

    <ul class="flex flex-wrap justify-center gap-6">
        @foreach ($members as $index => $member)
            <li class="w-full sm:w-[calc(50%-0.75rem)] {{ $width }}" data-reveal style="--reveal-delay: {{ ($index % $perRow) * 80 }}ms">
                <div class="sec-card group h-full overflow-hidden text-center transition-[translate,box-shadow] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-1.5 hover:shadow-[var(--shadow-lift)]">
                <div class="relative bg-[var(--sec-surface-2)] px-8 pt-8">
                    @if ($member['photo'])
                        <img
                            src="{{ $member['photo']->url('md') }}"
                            alt="{{ $member['name'] }}"
                            loading="lazy"
                            decoding="async"
                            width="320"
                            height="320"
                            class="mx-auto aspect-square w-full max-w-52 rounded-t-[999px] object-cover transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.04]"
                        >
                    @else
                        {{-- No photo yet: a vector portrait, a different look for each card. --}}
                        <div class="mx-auto aspect-square w-full max-w-52 overflow-hidden rounded-t-[999px]">
                            <x-site.avatar-illustration :variant="$index" class="block size-full transition-[scale] duration-[1200ms] ease-[cubic-bezier(0.22,1,0.36,1)] group-hover:scale-[1.04]" />
                        </div>
                    @endif
                </div>
                <div class="px-6 py-6">
                    <h3 class="sec-heading font-display text-[1.2rem] font-semibold">{{ $member['name'] }}</h3>
                    @if (filled($member['role']))<p class="mt-1 text-[14px] font-semibold text-gold-deep">{{ $member['role'] }}</p>@endif
                    @if (filled($member['bio']))<p class="sec-text mt-3 text-[14px] leading-relaxed">{{ $member['bio'] }}</p>@endif
                </div>
                </div>
            </li>
        @endforeach
    </ul>
</x-site.block>

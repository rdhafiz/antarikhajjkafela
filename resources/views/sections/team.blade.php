@php($members = array_values(array_filter($c['members'] ?? [], fn ($m) => filled($m['name']))))
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-14" />

    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($members as $index => $member)
            <li class="sec-card group overflow-hidden text-center" data-reveal style="--reveal-delay: {{ ($index % 4) * 70 }}ms">
                <div class="relative bg-[var(--sec-surface-2)] px-8 pt-8">
                    @if ($member['photo'])
                        <img
                            src="{{ $member['photo']->url('md') }}"
                            alt="{{ $member['name'] }}"
                            loading="lazy"
                            decoding="async"
                            width="320"
                            height="320"
                            class="mx-auto aspect-square w-full max-w-52 rounded-t-[999px] object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                        >
                    @else
                        <div class="mx-auto flex aspect-square w-full max-w-52 items-center justify-center rounded-t-[999px] bg-brand-100 font-display text-5xl text-brand" aria-hidden="true">
                            {{ mb_substr($member['name'], 0, 1) }}
                        </div>
                    @endif
                </div>
                <div class="px-6 py-6">
                    <h3 class="sec-heading font-display text-[1.2rem] font-semibold">{{ $member['name'] }}</h3>
                    @if (filled($member['role']))<p class="mt-1 text-[14px] font-semibold text-gold-deep">{{ $member['role'] }}</p>@endif
                    @if (filled($member['bio']))<p class="sec-text mt-3 text-[14px] leading-relaxed">{{ $member['bio'] }}</p>@endif
                </div>
            </li>
        @endforeach
    </ul>
</x-site.block>

@php($dark = $style['tone'] === 'dark')
@if ($target)
    <x-site.block :style="$style" :section="$section">
        @unless ($style['image'])
            <div class="pattern-star pointer-events-none absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true"></div>
        @endunless
        <div
            class="mx-auto max-w-3xl text-center"
            x-data="{
                end: {{ $target->getTimestampMs() }}, left: {{ max(0, $target->getTimestampMs() - now()->getTimestampMs()) }},
                init() { const t = setInterval(() => { this.left = Math.max(0, this.end - Date.now()); if (!this.left) clearInterval(t) }, 1000) },
                part(ms) { return String(Math.floor(ms)).padStart(2, '0') },
            }"
        >
            @if (filled($c['eyebrow']))<p class="eyebrow justify-center">{{ $c['eyebrow'] }}</p>@endif
            @if (filled($c['title']))<h2 @class(['sec-heading text-display-lg', 'mt-4' => filled($c['eyebrow'])])>{{ $c['title'] }}</h2>@endif

            <div x-show="left > 0" class="mt-10 grid grid-cols-4 gap-3 sm:gap-5" role="timer" aria-live="off">
                @foreach (['days' => [86400000, null], 'hours' => [3600000, 24], 'minutes' => [60000, 60], 'seconds' => [1000, 60]] as $unit => [$ms, $mod])
                    <div class="sec-card px-2 py-5 sm:py-7">
                        <p class="font-display text-[2rem] leading-none font-semibold text-gold tabular-nums sm:text-[3rem]"
                           x-text="part({{ $mod ? "(left / $ms) % $mod" : "left / $ms" }})">--</p>
                        <p class="sec-muted mt-2 text-[12px] font-semibold tracking-wider uppercase sm:text-[13px]">{{ __(ucfirst($unit)) }}</p>
                    </div>
                @endforeach
            </div>
            <p x-show="left <= 0" x-cloak class="sec-heading mt-8 text-display-sm">{{ $c['expired_text'] ?: __('This offer has ended.') }}</p>

            <x-site.link-button :link="$c['link']" :variant="$dark ? 'gold' : 'primary'" size="lg" icon-right="arrow-right" class="mt-10" />
        </div>
    </x-site.block>
@endif

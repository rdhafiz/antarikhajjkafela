@php
    use App\Support\Money;

    $steps = array_values(array_filter($c['steps'] ?? [], fn ($s) => filled($s['title'])));
    $count = count($steps);
    $dark = $style['tone'] === 'dark';
    $uid = 'journey-'.$section->id;
    $latin = app()->getLocale() !== 'bn';
    $pad = fn (int $n) => Money::digits(str_pad((string) $n, 2, '0', STR_PAD_LEFT));
@endphp
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-12 lg:mb-14" />

    {{--
        A journey stepper: the steps on one side, a stage showing the current step on the other.
        It advances on its own while in view (paused on hover, focus, reduced motion or a click),
        and every step's text is in the page for search engines and screen readers.
    --}}
    <div
        x-data="journey({{ $count }})"
        @mouseenter="hold(true)" @mouseleave="hold(false)" @focusin="hold(true)" @focusout="hold(false)"
        class="journey grid gap-5 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-8"
    >
        {{-- The step list and the stage reveal in turn (not the tabs themselves: their own transitions would cancel it). --}}
        <div data-reveal="left" role="tablist" aria-label="{{ $c['title'] ?: __('Steps') }}" class="grid gap-2 lg:flex lg:flex-col lg:gap-0" style="grid-template-columns: repeat({{ $count }}, minmax(0, 1fr))" @keydown="keys($event)">
            @foreach ($steps as $index => $step)
                <button
                    type="button" role="tab" id="{{ $uid }}-tab-{{ $index }}" aria-controls="{{ $uid }}-panel-{{ $index }}"
                    @if ($index === 0) aria-selected="true" @else aria-selected="false" tabindex="-1" @endif
                    :aria-selected="active === {{ $index }}" :tabindex="active === {{ $index }} ? 0 : -1"
                    @click="select({{ $index }})"
                    @class(['journey-tab group relative flex flex-col gap-1 pt-4 text-start lg:flex-1 lg:flex-row lg:items-center lg:gap-5 lg:py-5 lg:ps-7', 'is-active' => $index === 0])
                    :class="{ 'is-active': active === {{ $index }}, 'is-past': active > {{ $index }} }"
                >
                    <span class="journey-rail" aria-hidden="true"><span></span></span>
                    <span class="journey-num shrink-0 font-display text-[1.375rem] lg:w-12 leading-none font-semibold tabular-nums lg:text-[2rem]">{{ $pad($index + 1) }}</span>
                    <span class="min-w-0 max-md:sr-only">
                        <span class="journey-title sec-heading block font-display text-[1rem] lg:text-[1.1875rem] leading-snug font-semibold">{{ $step['title'] }}</span>
                    </span>
                    <x-site.icon name="arrow-right" class="journey-arrow ms-auto hidden size-5 shrink-0 lg:block rtl:rotate-180" />
                </button>
            @endforeach
        </div>

        <div data-reveal="scale" style="--reveal-delay: 150ms" @class([
            'journey-stage relative isolate grid min-h-[22rem] overflow-hidden rounded-[2rem] text-white lg:min-h-[26rem]',
            'bg-brand-950' => ! $dark,
            'border border-white/10 bg-white/[0.06]' => $dark,
        ]) :style="{ '--step': active }">
            <div class="pattern-star absolute inset-0 -z-10 opacity-[0.07]" aria-hidden="true"></div>
            <div class="journey-glow absolute -z-10 size-[28rem] rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>

            @foreach ($steps as $index => $step)
                <div
                    role="tabpanel" id="{{ $uid }}-panel-{{ $index }}" aria-labelledby="{{ $uid }}-tab-{{ $index }}"
                    @class(['journey-panel relative flex flex-col p-7 sm:p-10 lg:p-12', 'is-active' => $index === 0])
                    :class="{ 'is-active': active === {{ $index }} }"
                    @if ($index > 0) inert @endif
                    :inert="active !== {{ $index }}"
                >
                    <span class="journey-bignum font-display font-semibold tabular-nums" aria-hidden="true">{{ $pad($index + 1) }}</span>

                    <div class="flex items-center gap-4">
                        <span class="jp-in relative flex size-16 shrink-0 items-center justify-center rounded-full bg-gold text-brand-950" style="--i: 0">
                            <span class="journey-ring" aria-hidden="true"></span>
                            <x-site.icon :name="$step['icon'] ?: 'check-circle'" class="size-7" />
                        </span>
                        <p @class(["jp-in text-[13px] font-semibold text-gold", "tracking-[0.18em] uppercase" => $latin]) style="--i: 1">
                            {{ __('Step :current of :total', ['current' => $pad($index + 1), 'total' => $pad($count)]) }}
                        </p>
                    </div>

                    <h3 class="jp-in mt-8 font-display text-display-md text-white sm:mt-10" style="--i: 2">{{ $step['title'] }}</h3>
                    @if (filled($step['text']))
                        <p class="jp-in mt-4 max-w-md text-[1.0625rem] leading-relaxed text-white/75" style="--i: 3">{{ $step['text'] }}</p>
                    @endif

                    @if ($count > 1)
                        <div class="jp-in mt-auto flex items-center gap-3 pt-10" style="--i: 4">
                            <button type="button" class="journey-nav" @click="select({{ ($index - 1 + $count) % $count }})" aria-label="{{ __('Previous step') }}">
                                <x-site.icon name="chevron-left" class="size-5 rtl:rotate-180" />
                            </button>
                            <button type="button" class="journey-nav" @click="select({{ ($index + 1) % $count }})" aria-label="{{ __('Next step') }}">
                                <x-site.icon name="chevron-right" class="size-5 rtl:rotate-180" />
                            </button>
                            @if ($index + 1 < $count)
                                <p class="ms-2 min-w-0 truncate text-[14px] text-white/60">
                                    {{ __('Next') }}: <span class="font-semibold text-white/90">{{ $steps[$index + 1]['title'] }}</span>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-site.block>

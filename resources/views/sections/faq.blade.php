@inject('site', App\Support\SiteContext::class)
@php
    use App\Support\Money;

    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => filled($i['question'])));
    $split = ($c['layout'] ?? 'split') === 'split';
    $dark = $style['tone'] === 'dark';
    $phone = $site->phones()[0] ?? null;
    $whatsapp = $site->whatsappUrl();
    $uid = 'faq-'.$section->id;
@endphp
<x-site.block :style="$style" :section="$section">
    <div @class(['grid items-start gap-10', 'lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)] lg:gap-16' => $split])>
        <div @class(['lg:sticky lg:top-28' => $split])>
            <x-site.block-heading :c="$c" :align="$split ? 'left' : 'center'" />

            {{-- Help card: the next step for anyone whose question isn't listed. --}}
            @if ($split && ($phone || $whatsapp || $c['cta']))
                <div @class([
                    'relative isolate mt-8 hidden overflow-hidden rounded-[1.75rem] p-7 text-white lg:block',
                    'bg-brand-950' => ! $dark,
                    'border border-white/10 bg-white/[0.06]' => $dark,
                ]) data-reveal>
                    <div class="pattern-star absolute inset-0 -z-10 opacity-[0.07]" aria-hidden="true"></div>
                    <div class="absolute -top-24 -right-24 -z-10 size-64 rounded-full bg-gold/15 blur-3xl" aria-hidden="true"></div>
                    <p class="font-display text-[1.375rem] leading-snug font-semibold">{{ __('Still have a question?') }}</p>
                    <p class="mt-2 text-[15px] leading-relaxed text-white/70">{{ __('Talk to our team by phone or WhatsApp.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($phone)
                            <a href="{{ $phone['href'] }}" class="btn btn-outline-light">
                                <x-site.icon name="phone" />
                                <span dir="ltr">{{ $phone['display'] }}</span>
                            </a>
                        @endif
                        @if ($whatsapp)
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-whatsapp">
                                <x-site.icon name="whatsapp" />
                                WhatsApp
                            </a>
                        @endif
                    </div>
                    @if ($c['cta'])
                        <a href="{{ $c['cta']['url'] }}" @if ($c['cta']['external']) target="_blank" rel="noopener" @endif class="group mt-6 inline-flex items-center gap-1.5 text-[14px] font-semibold text-gold">
                            {{ $c['cta']['label'] }}
                            <x-site.icon name="arrow-right" class="size-4 transition-[translate] duration-500 group-hover:translate-x-1 rtl:rotate-180" />
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div @class(['grid gap-3', 'mx-auto w-full max-w-3xl' => ! $split]) x-data="{ open: 0 }">
            @foreach ($items as $index => $item)
                <div data-reveal style="--reveal-delay: {{ ($index % 6) * 70 }}ms">
                <div @class(['faq-item', 'is-open' => $index === 0]) :class="{ 'is-open': open === {{ $index }} }">
                    <h3>
                        <button
                            type="button"
                            id="{{ $uid }}-q-{{ $index }}"
                            class="faq-question grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 p-4 text-start sm:gap-4 sm:p-5 sm:px-6"
                            @click="open = open === {{ $index }} ? null : {{ $index }}"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            :aria-expanded="(open === {{ $index }}).toString()"
                            aria-controls="{{ $uid }}-a-{{ $index }}"
                        >
                            <span class="faq-num w-6 font-display sm:w-7 text-[15px] font-semibold tabular-nums" aria-hidden="true">{{ Money::digits(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                            <span class="sec-heading font-sans text-[1rem] leading-snug sm:text-[1.0625rem] font-semibold text-pretty">{{ $item['question'] }}</span>
                            <span class="faq-toggle" aria-hidden="true"><span></span><span></span></span>
                        </button>
                    </h3>
                    <div id="{{ $uid }}-a-{{ $index }}" role="region" aria-labelledby="{{ $uid }}-q-{{ $index }}" x-show="open === {{ $index }}" x-collapse @if ($index !== 0) x-cloak @endif>
                        <div class="faq-answer prose-site px-4 pb-5 sm:px-5 sm:pb-6 text-[1rem] sm:ps-[4.25rem] sm:pe-16">{!! $item['answer'] !!}</div>
                    </div>
                </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($c['cta'])
        <div @class(['mt-10 text-center', 'lg:hidden' => $split])>
            <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" />
        </div>
    @endif
</x-site.block>

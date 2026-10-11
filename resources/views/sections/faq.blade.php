@php
    $items = array_values(array_filter($c['items'] ?? [], fn ($i) => filled($i['question'])));
    $split = ($c['layout'] ?? 'split') === 'split';
    $dark = $style['tone'] === 'dark';
@endphp
<x-site.block :style="$style" :section="$section">
    <div @class(['grid gap-12', 'lg:grid-cols-[0.8fr_1.2fr] lg:gap-16' => $split])>
        <div @class(['lg:sticky lg:top-28 lg:self-start' => $split])>
            <x-site.block-heading :c="$c" :align="$split ? 'left' : 'center'" />
            @if ($c['cta'])
                <x-site.link-button :link="$c['cta']" :variant="$dark ? 'gold' : 'primary'" icon-right="arrow-right" @class(['mt-8', 'hidden lg:inline-flex' => ! $split]) />
            @endif
        </div>

        <div @class(['mx-auto w-full max-w-3xl' => ! $split]) x-data="{ open: 0 }">
            @foreach ($items as $index => $item)
                <div class="border-b border-[var(--sec-border)] first:border-t">
                    <h3>
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-6 py-5 text-left"
                            @click="open = open === {{ $index }} ? null : {{ $index }}"
                            :aria-expanded="(open === {{ $index }}).toString()"
                            aria-controls="faq-{{ $section->id }}-{{ $index }}"
                        >
                            <span class="sec-heading font-sans text-[1.05rem] leading-snug font-semibold">{{ $item['question'] }}</span>
                            <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full border border-[var(--sec-border)] transition-[transform,background-color] duration-300"
                                  :class="open === {{ $index }} ? 'rotate-45 bg-gold border-gold text-brand-950' : 'sec-heading'">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            </span>
                        </button>
                    </h3>
                    <div id="faq-{{ $section->id }}-{{ $index }}" x-show="open === {{ $index }}" x-collapse @if ($index !== 0) x-cloak @endif>
                        <div class="prose-site pr-10 pb-6 text-[1rem]">{!! $item['answer'] !!}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.block>

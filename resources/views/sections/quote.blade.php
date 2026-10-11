<x-site.block :style="$style" :section="$section">
    @unless ($style['image'])
        <div class="pattern-star pointer-events-none absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true"></div>
    @endunless
    <figure class="mx-auto max-w-3xl text-center" data-reveal>
        <x-site.icon name="moon" class="mx-auto size-8 text-gold" />
        @if (filled($c['arabic']))
            <p lang="ar" dir="rtl" class="sec-heading mt-7 font-serif text-[1.9rem] leading-[1.9]">{{ $c['arabic'] }}</p>
        @endif
        <blockquote class="sec-heading mt-6 font-display text-display-md italic">“{{ $c['text'] }}”</blockquote>
        @if (filled($c['source']))
            <figcaption class="mt-6 inline-flex items-center gap-3 text-[14px] font-semibold tracking-wide text-gold uppercase">
                <span class="h-px w-8 bg-gold/60"></span>{{ $c['source'] }}<span class="h-px w-8 bg-gold/60"></span>
            </figcaption>
        @endif
    </figure>
</x-site.block>

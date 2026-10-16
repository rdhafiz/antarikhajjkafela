{{-- "Free Booking Now" inside a Hajj or Umrah blog post. Expects $cta = ['kind' => 'hajj'|'umrah', 'url' => ...]. --}}
@inject('site', App\Support\SiteContext::class)
@php
    $hajj = $cta['kind'] === 'hajj';
    $whatsapp = $site->whatsappUrl(__($hajj ? 'Assalamu alaikum, I would like to register for Hajj.' : 'Assalamu alaikum, I would like to book an Umrah package.'));
@endphp
<aside class="post-cta relative isolate my-10 overflow-hidden rounded-[1.75rem] bg-brand-950 p-6 text-white shadow-[var(--shadow-lift)] sm:p-8" aria-label="{{ __('Free Booking Now') }}" data-reveal>
    <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
    <div class="absolute -top-24 -right-20 -z-10 size-72 rounded-full bg-gold/15 blur-3xl" aria-hidden="true"></div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-[12.5px] font-bold tracking-[0.14em] text-gold uppercase">
                <x-site.icon name="kaaba" class="size-4" />{{ __('Free booking') }}
            </p>
            <p class="mt-2 font-display text-[1.45rem] leading-snug font-semibold text-white sm:text-[1.6rem]">
                {{ $hajj ? __('Begin your Hajj journey with us') : __('Ready for your Umrah?') }}
            </p>
            <p class="mt-2 max-w-md text-[15px] leading-relaxed text-white/75">{{ __('Book in a few minutes with no payment now. Our team calls you to confirm every detail.') }}</p>
        </div>
        <div class="flex shrink-0 flex-col gap-2.5 sm:items-stretch">
            <x-site.button :href="$cta['url']" variant="gold" icon-right="arrow-right">{{ __('Free Booking Now') }}</x-site.button>
            @if ($whatsapp)
                <x-site.button :href="$whatsapp" variant="outline-light" icon="whatsapp" size="sm" external>{{ __('Ask on WhatsApp') }}</x-site.button>
            @endif
        </div>
    </div>
</aside>

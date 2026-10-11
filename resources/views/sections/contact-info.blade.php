@inject('site', App\Support\SiteContext::class)
@php
    $phones = $site->phones();
    $emails = $site->emails();
    $whatsapp = $site->whatsappUrl();
    $map = $c['show_map'] ? $site->mapEmbedUrl() : null;
@endphp
<x-site.block :style="$style" :section="$section">
    <div @class(['grid gap-12', 'lg:grid-cols-[0.9fr_1.1fr] lg:gap-16' => $map])>
        <div>
            <x-site.block-heading :c="$c" align="left" />
            <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                @if ($address = $site->address())
                    <li class="sec-card flex gap-4 p-5 sm:col-span-2 lg:col-span-1 xl:col-span-2">
                        <span class="sec-icon size-12 shrink-0"><x-site.icon name="map-pin" class="size-5" /></span>
                        <div>
                            <p class="sec-muted text-[13px] font-semibold tracking-wide uppercase">{{ __('Office') }}</p>
                            <address class="sec-heading mt-1 not-italic">{{ $address }}</address>
                        </div>
                    </li>
                @endif
                @if ($phones)
                    <li class="sec-card flex gap-4 p-5">
                        <span class="sec-icon size-12 shrink-0"><x-site.icon name="phone" class="size-5" /></span>
                        <div>
                            <p class="sec-muted text-[13px] font-semibold tracking-wide uppercase">{{ __('Phone') }}</p>
                            @foreach ($phones as $phone)
                                <a href="{{ $phone['href'] }}" class="sec-heading mt-1 block font-semibold hover:underline">{{ $phone['display'] }}</a>
                            @endforeach
                        </div>
                    </li>
                @endif
                @if ($emails)
                    <li class="sec-card flex gap-4 p-5">
                        <span class="sec-icon size-12 shrink-0"><x-site.icon name="mail" class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="sec-muted text-[13px] font-semibold tracking-wide uppercase">{{ __('Email') }}</p>
                            @foreach ($emails as $email)
                                <a href="mailto:{{ $email }}" class="sec-heading mt-1 block font-semibold break-all hover:underline">{{ $email }}</a>
                            @endforeach
                        </div>
                    </li>
                @endif
                @if (filled($c['hours']))
                    <li class="sec-card flex gap-4 p-5">
                        <span class="sec-icon size-12 shrink-0"><x-site.icon name="clock" class="size-5" /></span>
                        <div>
                            <p class="sec-muted text-[13px] font-semibold tracking-wide uppercase">{{ __('Office hours') }}</p>
                            <p class="sec-heading mt-1 whitespace-pre-line">{{ $c['hours'] }}</p>
                        </div>
                    </li>
                @endif
                @if ($whatsapp)
                    <li class="flex items-center">
                        <x-site.button :href="$whatsapp" variant="whatsapp" icon="whatsapp" external>{{ __('Chat on WhatsApp') }}</x-site.button>
                    </li>
                @endif
            </ul>
        </div>

        @if ($map)
            <div class="min-h-[360px] overflow-hidden rounded-[1.75rem] shadow-[var(--shadow-lift)]" data-reveal>
                <iframe
                    src="{{ $map }}"
                    title="{{ __('Office location on Google Maps') }}"
                    class="size-full min-h-[360px]"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        @endif
    </div>
</x-site.block>

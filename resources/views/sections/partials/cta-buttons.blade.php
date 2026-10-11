@inject('site', App\Support\SiteContext::class)
@php
    $phones = $site->phones();
    $whatsapp = $site->whatsappUrl();
@endphp
<div class="flex flex-col flex-wrap gap-3 sm:flex-row">
    <x-site.link-button :link="$c['primary'] ?? null" :variant="$onDark ? 'gold' : 'primary'" size="lg" icon-right="arrow-right" />
    <x-site.link-button :link="$c['secondary'] ?? null" :variant="$onDark ? 'outline-light' : 'outline'" size="lg" />
    @if ($c['contact_buttons'] ?? false)
        @if ($phones)
            <x-site.button :href="$phones[0]['href']" :variant="($c['primary'] ?? null) ? ($onDark ? 'outline-light' : 'outline') : 'gold'" size="lg" icon="phone">{{ $phones[0]['display'] }}</x-site.button>
        @endif
        @if ($whatsapp)
            <x-site.button :href="$whatsapp" variant="whatsapp" size="lg" icon="whatsapp" external>WhatsApp</x-site.button>
        @endif
    @endif
</div>

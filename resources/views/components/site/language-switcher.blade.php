@props(['languages', 'tone' => 'light'])
{{--
    Segmented control listing every language; the page URL stays the same.
    tone: "light" (on white), "dark" (on navy) or "header": follows the site
    header, dark while it is transparent over a hero and light once it turns
    white (driven by the header's data-transparent attribute).
--}}
<div
    role="group"
    aria-label="{{ __('Language') }}"
    @class([
        'inline-flex shrink-0 items-center rounded-full p-0.5 ring-1 transition-colors',
        'bg-white/8 ring-white/15' => $tone === 'dark',
        'bg-sand-100 ring-line' => in_array($tone, ['light', 'header'], true),
        'group-data-[transparent=true]/header:bg-white/10 group-data-[transparent=true]/header:ring-white/20' => $tone === 'header',
    ])
>
    @foreach ($languages as $language)
        <a
            href="{{ $language['url'] }}"
            rel="nofollow"
            hreflang="{{ $language['code'] }}"
            lang="{{ $language['code'] }}"
            @if ($language['active']) aria-current="true" @endif
            title="{{ $language['name'] }}"
            @class([
                'rounded-full px-3 py-1 text-[13px] leading-5 font-semibold transition-colors',
                'bg-gold text-brand-950' => $language['active'],
                'text-white/75 hover:text-white' => ! $language['active'] && $tone === 'dark',
                'text-ink-2 hover:text-brand' => ! $language['active'] && in_array($tone, ['light', 'header'], true),
                'group-data-[transparent=true]/header:text-white/80 group-data-[transparent=true]/header:hover:text-white' => ! $language['active'] && $tone === 'header',
            ])
            {{-- System font, so showing "বাংলা" on English pages doesn't download a Bangla web font. --}}
            style="font-family: system-ui, sans-serif"
        >{{ $language['code'] === 'en' ? 'EN' : $language['native'] }}</a>
    @endforeach
</div>

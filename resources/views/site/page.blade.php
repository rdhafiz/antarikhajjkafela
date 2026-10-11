<x-site.layout
    :header="$darkTop ? 'overlay' : 'solid'"
    :focused="$page->isCampaign() && $page->focused_layout"
    :tracking="$page->isCampaign() ? ($page->tracking ?? []) : []"
    :footer-cta="$footerCta"
>
    @if ($preview)
        <div class="sticky top-0 z-50 flex items-center justify-center gap-3 bg-gold px-4 py-2 text-center text-[13px] font-semibold text-brand-950">
            {{ __('Preview') }} · {{ $page->isPublished() ? __('Published') : __('Draft, not visible to visitors') }}
            @if ($page->isCampaign() && $page->campaignState() !== 'live' && $page->isPublished())
                · {{ ucfirst($page->campaignState()) }}
            @endif
        </div>
    @endif

    {!! $html !!}
</x-site.layout>

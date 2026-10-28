<x-site.layout
    :header="$darkTop ? 'overlay' : 'solid'"
    :focused="$page->isCampaign() && $page->focused_layout"
    :tracking="$page->isCampaign() ? ($page->tracking ?? []) : []"
    :footer-cta="$footerCta"
>
    @if ($preview)
        @php
            $state = $page->isCampaign() ? $page->campaignState() : ($page->isPublished() ? 'published' : 'draft');
        @endphp
        <x-site.preview-bar
            :state="$state"
            :label="match ($state) { 'draft' => __('Draft, not visible to visitors'), 'published', 'live' => __('Published'), default => __('Published').' · '.ucfirst($state) }"
            :edit-url="admin_url('pages/'.$page->id)"
        />
    @endif

    {!! $html !!}
</x-site.layout>

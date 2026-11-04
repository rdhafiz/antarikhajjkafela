@props(['button', 'dark' => false])
{{-- The button at the bottom of a campaign page section (left, center or right). --}}
<div
    @class([
        'relative mt-12 flex sm:mt-14',
        'justify-start' => $button['align'] === 'left',
        'justify-end' => $button['align'] === 'right',
        'justify-center' => ! in_array($button['align'], ['left', 'right'], true),
    ])
    data-reveal
>
    <x-site.link-button :link="$button['link']" :variant="$dark ? 'gold' : 'primary'" size="lg" icon-right="arrow-right" />
</div>

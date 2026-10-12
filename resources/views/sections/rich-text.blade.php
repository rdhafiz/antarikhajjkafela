<x-site.block :style="$style" :section="$section">
    <div @class(['mx-auto', 'max-w-3xl' => ($c['width'] ?? 'narrow') === 'narrow', 'max-w-5xl' => ($c['width'] ?? 'narrow') === 'wide'])>
        <x-site.block-heading :c="$c" align="left" class="mb-10" />
        @if (filled($c['body']))
            <div class="prose-site" data-reveal-children>{!! $c['body'] !!}</div>
        @endif
    </div>
</x-site.block>

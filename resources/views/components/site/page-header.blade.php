@props([
    'title',
    'intro' => null,
    'eyebrow' => null,
    'highlight' => null,
    'image' => null,        // optional App\Models\Media background (falls back to the default banner)
    'stats' => [],
])
{{-- Top of inner pages: the shared hero (see x-site.hero). --}}
<x-site.hero :title="$title" :intro="$intro" :eyebrow="$eyebrow" :highlight="$highlight" :image="$image" :stats="$stats">{{ $slot }}</x-site.hero>

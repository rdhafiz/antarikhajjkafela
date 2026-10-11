@inject('structuredData', App\Support\Seo\StructuredData::class)
@inject('branding', App\Support\Branding::class)
@php
    $seo = seo();
    $title = $seo->resolvedTitle();
    $description = $seo->resolvedDescription();
    $image = $seo->image
        ?? (($og = settings('seo.og_image')) ? App\Models\Media::find($og)?->url('og') : null)
        ?? $branding->logoUrl();
    $locale = app()->getLocale();
    $ogLocale = ['en' => 'en_US', 'bn' => 'bn_BD'][$locale] ?? $locale;
@endphp
<title>{{ $title }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
<link rel="canonical" href="{{ $seo->resolvedCanonical() }}">
<meta name="robots" content="{{ $seo->noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
@if ($verification = settings('tracking.search_console_verification'))
    <meta name="google-site-verification" content="{{ $verification }}">
@endif

<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:site_name" content="{{ setting_t('branding.site_name') }}">
<meta property="og:title" content="{{ $title }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $seo->resolvedCanonical() }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta name="twitter:card" content="summary_large_image">

<script type="application/ld+json">{!! $structuredData->toJson($seo) !!}</script>

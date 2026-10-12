@inject('structuredData', App\Support\Seo\StructuredData::class)
@inject('branding', App\Support\Branding::class)
@php
    $seo = seo();
    $title = $seo->resolvedTitle();
    $description = $seo->resolvedDescription();
    $image = $seo->resolvedImage();
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
@if ($image && str_contains($image, '/og.'))
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endif
<meta property="og:image:alt" content="{{ $title }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
@if ($description)
    <meta name="twitter:description" content="{{ $description }}">
@endif
<meta name="twitter:image" content="{{ $image }}">

<script type="application/ld+json">{!! $structuredData->toJson($seo) !!}</script>

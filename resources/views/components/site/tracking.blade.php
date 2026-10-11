@props(['position' => 'head', 'extra' => []])
{{--
    Analytics and advertising tags from the tracking settings, plus any extra
    IDs set on a campaign page. Only output in production so development and
    test visits don't pollute the data. IDs are validated when saved.
--}}
@if (app()->isProduction())
    @php
        $gtmIds = array_values(array_unique(array_filter([settings('tracking.gtm_id'), $extra['gtm_id'] ?? null])));
        $ga = settings('tracking.ga_measurement_id');
        $pixelIds = array_values(array_unique(array_filter([settings('tracking.facebook_pixel_id'), $extra['facebook_pixel_id'] ?? null])));
    @endphp

    @if ($position === 'head')
        @foreach ($gtmIds as $gtm)
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
        @endforeach
        @if ($ga)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga));</script>
        @endif
        @if ($pixelIds)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');@foreach ($pixelIds as $pixel)fbq('init',@json($pixel));@endforeach fbq('track','PageView');</script>
        @endif
    @else
        @foreach ($gtmIds as $gtm)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
        @endforeach
    @endif
@endif

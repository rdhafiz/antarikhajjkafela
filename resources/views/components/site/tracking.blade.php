@props(['position' => 'head'])
{{--
    Analytics and advertising tags from the tracking settings. They are only
    output in production so development and test visits don't pollute the data.
    IDs are validated against strict patterns when saved.
--}}
@if (app()->isProduction())
    @php
        $gtm = settings('tracking.gtm_id');
        $ga = settings('tracking.ga_measurement_id');
        $pixel = settings('tracking.facebook_pixel_id');
    @endphp

    @if ($position === 'head')
        @if ($gtm)
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
        @endif
        @if ($ga)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga));</script>
        @endif
        @if ($pixel)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($pixel));fbq('track','PageView');</script>
        @endif
    @elseif ($gtm)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    @endif
@endif

{{--
    The site's own error page when the site can render (database, settings),
    otherwise the standalone page. Expects $code, $title, $text.
--}}
@php
    try {
        $page = view('errors.site-error', compact('code', 'title', 'text'))->render();
    } catch (Throwable $e) {
        $page = null;
    }
@endphp
@if ($page)
    {!! $page !!}
@else
    @include('errors.minimal-layout')
@endif

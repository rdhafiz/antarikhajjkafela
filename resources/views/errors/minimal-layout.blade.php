{{--
    Standalone error page: no database, settings or built assets, so it still
    renders when the application itself is failing. Expects $code, $title, $text.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:#f7f2e9;color:#0d1b2e;font-family:system-ui,-apple-system,'Segoe UI',Roboto,'Noto Sans Bengali',sans-serif}
        main{max-width:520px;text-align:center}
        img{width:160px;height:auto}
        .code{margin:28px 0 0;font:600 64px/1 Georgia,serif;color:#846500}
        h1{margin:12px 0 0;font:600 28px/1.25 Georgia,serif;color:#012a51}
        p{margin:14px 0 0;line-height:1.6;color:#3d4b5f}
        a{display:inline-block;margin-top:28px;padding:12px 26px;border-radius:999px;background:#012a51;color:#fff;font-weight:600;text-decoration:none}
        a:focus-visible{outline:3px solid #fdc802;outline-offset:3px}
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('images/brand/logo-360.webp') }}" alt="{{ config('app.name') }}" width="160" height="50">
        <p class="code" aria-hidden="true">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $text }}</p>
        @if ($code !== 503)
            <a href="{{ url('/') }}">{{ __('Back to home') }}</a>
        @endif
    </main>
</body>
</html>

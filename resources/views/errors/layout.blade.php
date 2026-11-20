<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#7A1CAC">
    <title>@yield('title', 'ARSIP-TA')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset_version('css/vendor/poppins.css') }}">
    <link rel="stylesheet" href="{{ asset_version('css/errors.css') }}">
</head>
<body>
    <main class="scene scene-@yield('code')">
        <div class="stage">
            <p class="watermark" aria-hidden="true">@yield('code')</p>
            @yield('robot')
        </div>
        <section class="panel">
            <p class="brand">ARSIP-TA</p>
            <p class="code"><span>@yield('code')</span> · @yield('label')</p>
            <h1>@yield('heading')</h1>
            <blockquote class="speech">
                <p class="voice">@yield('voice', 'Robot arsip')</p>
                <p>{{ $message ?? trim($__env->yieldContent('defaultMessage')) }}</p>
            </blockquote>
            @hasSection('damage')
                <ul class="damage">
                    @yield('damage')
                </ul>
            @endif
            @hasSection('hint')
                <p class="hint">@yield('hint')</p>
            @endif
            <div class="actions">
                @yield('actions')
            </div>
        </section>
    </main>
    <script src="{{ asset_version('js/errors.js') }}" defer></script>
</body>
</html>

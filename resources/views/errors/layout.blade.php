<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'ARSIP-TA')</title>
    <link rel="stylesheet" href="{{ asset_version('css/errors.css') }}">
</head>
<body>
    <main class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>{{ $message ?? trim($__env->yieldContent('defaultMessage')) }}</p>
        <a href="{{ url('/') }}">Kembali ke beranda</a>
    </main>
</body>
</html>

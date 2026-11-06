<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#7A1CAC">
@php
    $isAuthGate = request()->routeIs('login', 'register');
@endphp

<title>@yield('title', 'ARSIP TA FMIPA UNJ')</title>

<!-- PWA -->
<link rel="manifest" href="{{ asset_version('manifest.json') }}">
<link rel="icon" type="image/png" href="{{ asset('images/icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192.png') }}">

@if ($isAuthGate)
<link rel="preload" href="{{ asset('images/building.webp') }}" as="image" type="image/webp" fetchpriority="high">
<link rel="preload" href="{{ asset('images/logo-256.webp') }}" as="image" type="image/webp">
<link rel="stylesheet" href="{{ asset_version('css/vendor/poppins-auth.css') }}">
@else
<link rel="stylesheet" href="{{ asset_version('css/vendor/poppins.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/vendor/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/overlays.css') }}">
@endif
<link rel="stylesheet" href="{{ asset_version('css/auth-login.css') }}">
@stack('styles')
@unless ($isAuthGate)
<link rel="stylesheet" href="{{ asset_version('css/student-shell.css') }}">
@endunless
</head>
<body class="auth-login @yield('body-class'){{ $isAuthGate ? ' is-auth-gate' : '' }}{{ ! $isAuthGate && Auth::guard('student')->check() ? ' has-student-nav' : '' }}">

@include('module.loader.loaders')

<div class="offline-banner" id="offlinebanner">
    @include('auth.partials.icon', ['name' => 'wifi'])
    <span>Koneksi terputus — perubahan disimpan di perangkat dan akan disinkronkan otomatis.</span>
</div>

@unless ($isAuthGate)
    @include('student.partials.session-bar')
@endunless

<div class="wrapper">

    @hasSection('hero')
        @yield('hero')
    @elseif (! View::hasSection('standalone'))
        @include('auth.partials.hero')
    @endif

    @hasSection('auth-card')
        <aside class="login-side">
            <div class="login-card">
                @yield('auth-card')
            </div>
        </aside>
    @endif

    @yield('standalone')

</div>

<div id="pwa-sync-slot" class="pwa-sync-toast" aria-live="polite"></div>

@if ($isAuthGate)
<script src="{{ asset_version('js/dom-elements.js') }}" defer></script>
<script src="{{ asset_version('js/pwa-register.js') }}" defer></script>
<script src="{{ asset_version('js/auth-loader.js') }}" defer></script>
<script src="{{ asset_version('js/auth-login.js') }}" defer></script>
@else
<script src="{{ asset_version('js/dom-elements.js') }}" defer></script>
<script src="{{ asset_version('js/offline-database.js') }}" defer></script>
<script src="{{ asset_version('js/pwa-register.js') }}" defer></script>
<script src="{{ asset_version('js/auth-loader.js') }}" defer></script>
<script src="{{ asset_version('js/auth-login.js') }}" defer></script>
@endif
@stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
@php($authUser = auth()->user())

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="user-role" content="{{ $authUser?->role }}">
<meta name="user-id" content="{{ $authUser?->id }}">
<meta name="user-name" content="{{ $authUser?->name }}">

<title>ARSIP-TA</title>
@include('module.setting.styles')
</head>
<body @class(['role-coordinator' => $authUser?->isCoordinator()]) data-user-role="{{ $authUser?->role }}" data-user-id="{{ $authUser?->id }}" data-user-name="{{ $authUser?->name }}">

@include('module.loader.loaders')

<!-- Offline indicator for every PWA module. Groq import analysis still requires a network. -->
<div class="offline-banner" id="offlinebanner">
<i class="fas fa-wifi"></i>
<span>Koneksi terputus. Perubahan disimpan di perangkat dan akan disinkronkan secara otomatis. Analisis impor kecerdasan buatan tidak tersedia saat luring.</span>
</div>

<div class="wrapper">

<!-- SIDEBAR -->

<aside class="sidebar" id="sidebar">

<div class="brand">
<div class="logo-wrapper">
<img src="{{ asset_version('images/logo.png') }}" alt="Logo UNJ" width="100" height="99" decoding="async">
</div>
<h2>ARSIP TA</h2>
<p>Fakultas Matematika dan Ilmu Pengetahuan Alam</p>
</div>

@include('admin.navbar.sidebars')

</aside>

<div class="sidebar-backdrop" id="sidebarbackdrop" aria-hidden="true"></div>

<!-- MAIN -->

<main class="main" id="main">

<div class="topbar">

<div class="toggle-btn" id="toggleSidebar">
    <span></span>
    <span></span>
</div>

<div class="top-right">

@include('admin.partials.role-sessions')

<div class="profile" id="profilebtn">

<div class="profile-info">
<div>
<div class="profile-name">{{ $authUser?->name }}</div>
<div class="profile-role">{{ $authUser?->role }}</div>
</div>

<div class="avatar">
<i class="fas fa-user"></i>
</div>
</div>

<div class="dropdown" id="dropdown">

<div class="profile-dropdown">
<div class="profile-name">{{ $authUser?->name }}</div>
<div class="profile-role">{{ $authUser?->formattedIdentityNumber() }}</div>
</div>

<a href="{{ route('page-profile') }}">
<i class="fas fa-user"></i>
Profil
</a>

<form action="{{ route('logout') }}" method="POST" class="logout-form">
@csrf
<input type="hidden" name="guard" value="{{ auth()->getDefaultDriver() }}">
<button type="submit" class="logout-btn">
<i class="fas fa-right-from-bracket"></i>
Keluar
</button>
</form>

</div>

</div>

</div>

</div>

@include('admin.navbar.topbars')

<div class="content">

<div class="hero">
<h1 id="headershero">@yield('hero-title')</h1>
<p id="titleshero">@yield('hero-text')</p>
</div>

@yield('content')

<div class="footer">
© 2026 FMIPA Universitas Negeri Jakarta
</div>

</div>

</main>

</div>

@include('module.setting.scripts')

</body>
</html>

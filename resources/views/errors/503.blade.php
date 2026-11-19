@extends('errors.layout')

@section('title', 'Layanan tidak tersedia — ARSIP-TA')
@section('code', '503')
@section('label', 'Sedang diservis')
@section('heading', 'Layanan sedang tidak tersedia')
@section('voice', 'Robot perawatan')
@section('defaultMessage')
Sakelar dayanya dicabut. Robot ini tidur dengan pita servis di badan, dan laci arsip ditutup sementara.
@endsection
@section('damage')
    <li>Daya dicabut</li>
    <li>Pita servis</li>
    <li>Tidur sementara</li>
@endsection
@section('hint')
Sistem sedang pemeliharaan atau istirahat. Silakan kembali lagi nanti.
@endsection
@section('actions')
    <a class="btn" href="{{ url('/') }}">Coba lagi nanti</a>
@endsection

@section('robot')
<svg class="bot bot-503" viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <ellipse cx="180" cy="392" rx="108" ry="12" fill="#99f6e4" opacity=".55"/>
    <g class="zzz">
        <text x="250" y="78" fill="#0f766e" font-size="22" font-weight="700" font-family="Poppins, sans-serif">z</text>
        <text x="270" y="56" fill="#0f766e" font-size="16" font-weight="700" font-family="Poppins, sans-serif">z</text>
        <text x="286" y="40" fill="#0f766e" font-size="12" font-weight="700" font-family="Poppins, sans-serif">z</text>
    </g>
    <path d="M132 78h96l-8 14H140z" fill="#115e59"/>
    <rect x="150" y="58" width="60" height="22" rx="8" fill="#0f766e"/>
    <rect x="128" y="86" width="104" height="12" rx="4" fill="#0f766e"/>
    <circle cx="108" cy="150" r="9" fill="#ccfbf1" stroke="#115e59" stroke-width="3"/>
    <circle cx="252" cy="150" r="9" fill="#ccfbf1" stroke="#115e59" stroke-width="3"/>
    <rect x="118" y="96" width="124" height="104" rx="32" fill="#0f766e"/>
    <rect x="126" y="104" width="108" height="88" rx="26" fill="#115e59"/>
    <rect x="136" y="118" width="88" height="54" rx="16" fill="#042f2e"/>
    <path d="M154 142q10 10 20 0" stroke="#99f6e4" stroke-width="3" fill="none" stroke-linecap="round"/>
    <path d="M192 142q10 10 20 0" stroke="#99f6e4" stroke-width="3" fill="none" stroke-linecap="round"/>
    <path d="M166 156q14 10 28 0" stroke="#99f6e4" stroke-width="3" fill="none" stroke-linecap="round"/>
    <rect x="166" y="198" width="28" height="16" rx="6" fill="#134e4a"/>
    <path d="M118 250c-24 20-18 54-2 70" stroke="#0f766e" stroke-width="16" stroke-linecap="round" fill="none"/>
    <path d="M242 250c24 20 18 54 2 70" stroke="#0f766e" stroke-width="16" stroke-linecap="round" fill="none"/>
    <rect x="78" y="236" width="20" height="26" rx="4" fill="#134e4a"/>
    <circle cx="88" cy="246" r="2.5" fill="#99f6e4"/>
    <circle cx="88" cy="256" r="2.5" fill="#99f6e4"/>
    <path d="M78 250C40 250 36 318 70 336" stroke="#334155" stroke-width="4" fill="none"/>
    <rect x="58" y="330" width="30" height="18" rx="4" fill="#334155"/>
    <rect x="64" y="348" width="5" height="12" rx="1" fill="#94a3b8"/>
    <rect x="76" y="348" width="5" height="12" rx="1" fill="#94a3b8"/>
    <rect x="102" y="210" width="156" height="118" rx="28" fill="#0f766e"/>
    <rect x="120" y="228" width="120" height="76" rx="16" fill="#f0fdfa" stroke="#99f6e4"/>
    <g transform="rotate(-8 180 262)">
        <rect x="126" y="250" width="132" height="24" fill="#facc15"/>
        <path d="M126 250h16v24h-16zM158 250h16v24h-16zM190 250h16v24h-16zM222 250h16v24h-16z" fill="#0f766e" opacity=".85"/>
    </g>
    <text x="180" y="246" text-anchor="middle" fill="#115e59" font-size="11" font-weight="700" font-family="Poppins, sans-serif">SERVIS</text>
    <g transform="rotate(22 304 300)">
        <rect x="300" y="236" width="8" height="78" rx="3" fill="#64748b"/>
        <path d="M292 236a12 12 0 1 1 24 0" fill="none" stroke="#475569" stroke-width="5"/>
    </g>
    <rect x="124" y="336" width="40" height="22" rx="10" fill="#134e4a"/>
    <rect x="196" y="336" width="40" height="22" rx="10" fill="#134e4a"/>
</svg>
@endsection

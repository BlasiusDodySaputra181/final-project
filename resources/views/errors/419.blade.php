@extends('errors.layout')

@section('title', 'Sesi berakhir — ARSIP-TA')
@section('code', '419')
@section('label', 'Sesi mati')
@section('heading', 'Sesi telah berakhir')
@section('voice', 'Robot penjaga')
@section('defaultMessage')
Baterai sesinya habis. Mata robot meredup, jam di dadanya retak, dan formulir ini tidak lagi ia kenali.
@endsection
@section('damage')
    <li>Baterai kosong</li>
    <li>Mata meredup</li>
    <li>Jam retak</li>
@endsection
@section('hint')
Muat ulang halaman supaya sesi baru menyala, lalu kirim ulang.
@endsection
@section('actions')
    <button class="btn" type="button" onclick="location.reload()">Muat ulang halaman</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
<svg class="bot bot-419" viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <ellipse cx="180" cy="392" rx="104" ry="12" fill="#c7d2fe" opacity=".7"/>
    <path d="M248 86l26-20M248 86h30M248 86l18 18M258 74q12 8 2 18M266 78q8 8 0 14" stroke="#c4b5fd" stroke-width="1.6" fill="none"/>
    <path d="M150 74V48" stroke="#312e81" stroke-width="6" stroke-linecap="round"/>
    <circle cx="150" cy="40" r="8" fill="#a5b4fc"/>
    <path d="M210 74V52" stroke="#312e81" stroke-width="6" stroke-linecap="round"/>
    <circle cx="210" cy="44" r="8" fill="#6366f1" opacity=".45"/>
    <circle cx="108" cy="138" r="9" fill="#e0e7ff" stroke="#4338ca" stroke-width="3"/>
    <circle cx="252" cy="138" r="9" fill="#e0e7ff" stroke="#4338ca" stroke-width="3"/>
    <rect x="118" y="72" width="124" height="108" rx="32" fill="#4f46e5"/>
    <rect x="126" y="80" width="108" height="92" rx="26" fill="#3730a3"/>
    <rect x="136" y="96" width="88" height="58" rx="16" fill="#1e1b4b"/>
    <circle cx="160" cy="124" r="7" fill="#c7d2fe"/>
    <circle cx="202" cy="124" r="7" fill="#c7d2fe"/>
    <rect class="lid" x="148" y="114" width="24" height="18" rx="8" fill="#1e1b4b"/>
    <rect class="lid" x="190" y="114" width="24" height="18" rx="8" fill="#1e1b4b"/>
    <ellipse cx="180" cy="144" rx="6" ry="4" fill="#a5b4fc"/>
    <rect x="166" y="178" width="28" height="16" rx="6" fill="#312e81"/>
    <path d="M118 236c-26 18-24 52-8 70" stroke="#4f46e5" stroke-width="16" stroke-linecap="round" fill="none"/>
    <path d="M242 236c26 18 24 52 8 70" stroke="#4f46e5" stroke-width="16" stroke-linecap="round" fill="none"/>
    <rect x="102" y="192" width="156" height="124" rx="28" fill="#4338ca"/>
    <rect x="120" y="210" width="120" height="84" rx="16" fill="#eef2ff" stroke="#c7d2fe"/>
    <rect x="146" y="224" width="62" height="28" rx="6" fill="#1e1b4b"/>
    <rect x="208" y="232" width="7" height="12" rx="2" fill="#1e1b4b"/>
    <rect class="spark" x="152" y="229" width="8" height="18" rx="2" fill="#f87171"/>
    <text x="186" y="244" fill="#fecaca" font-size="12" font-weight="700" font-family="Poppins, sans-serif">0%</text>
    <circle cx="180" cy="274" r="16" fill="#fff" stroke="#a5b4fc" stroke-width="3"/>
    <path d="M180 274V266" stroke="#4338ca" stroke-width="2" stroke-linecap="round"/>
    <path d="M180 274l8 6" stroke="#4338ca" stroke-width="2" stroke-linecap="round"/>
    <path d="M170 264l7 14 8-6" stroke="#dc2626" stroke-width="2" fill="none" stroke-linecap="round"/>
    <rect x="124" y="328" width="40" height="22" rx="10" fill="#312e81"/>
    <rect x="196" y="328" width="40" height="22" rx="10" fill="#312e81"/>
</svg>
@endsection

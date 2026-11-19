@extends('errors.layout')

@section('title', 'Kesalahan peladen — ARSIP-TA')
@section('code', '500')
@section('label', 'Korslet')
@section('heading', 'Terjadi kesalahan pada peladen')
@section('voice', 'Robot peladen')
@section('defaultMessage')
Panel dada terbuka dan kabel di dalamnya korslet. Satu lengan terlepas, roda gigi macet, dan permintaanmu berhenti di tengah jalan.
@endsection
@section('damage')
    <li>Panel terbuka</li>
    <li>Kabel korslet</li>
    <li>Lengan terlepas</li>
@endsection
@section('hint')
Coba lagi beberapa saat. Jika masih sama, beri tahu pengelola sistem.
@endsection
@section('actions')
    <button class="btn" type="button" onclick="location.reload()">Coba lagi</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
<svg class="bot bot-500" viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <ellipse cx="180" cy="392" rx="112" ry="12" fill="#fecaca" opacity=".65"/>
    <ellipse class="smoke" cx="214" cy="70" rx="12" ry="8" fill="#e2e8f0"/>
    <ellipse class="smoke d2" cx="236" cy="58" rx="16" ry="10" fill="#cbd5e1"/>
    <path d="M156 76V46" stroke="#7f1d1d" stroke-width="6" stroke-linecap="round"/>
    <circle class="spark" cx="156" cy="38" r="8" fill="#facc15"/>
    <path d="M198 78l10-16 12 14" stroke="#fecaca" stroke-width="3" fill="none" stroke-linecap="round"/>
    <circle cx="108" cy="136" r="9" fill="#fee2e2" stroke="#7f1d1d" stroke-width="3"/>
    <rect x="118" y="72" width="124" height="108" rx="32" fill="#b91c1c"/>
    <path d="M214 86l8 18-10 8 12 16" stroke="#fee2e2" stroke-width="3" fill="none" stroke-linecap="round"/>
    <rect x="126" y="80" width="108" height="92" rx="26" fill="#7f1d1d"/>
    <g class="glitch">
        <rect x="136" y="96" width="88" height="58" rx="16" fill="#1a1024"/>
        <rect x="146" y="108" width="68" height="6" fill="#f87171"/>
        <rect x="146" y="120" width="40" height="6" fill="#fde68a"/>
        <rect x="146" y="132" width="58" height="6" fill="#86efac"/>
        <text x="180" y="128" text-anchor="middle" fill="#fff" font-size="13" font-weight="700" font-family="Poppins, sans-serif">ERR</text>
    </g>
    <rect x="166" y="178" width="28" height="16" rx="6" fill="#7f1d1d"/>
    <g transform="rotate(-16 70 348)">
        <path d="M36 332h78" stroke="#b91c1c" stroke-width="16" stroke-linecap="round"/>
        <circle cx="118" cy="332" r="11" fill="#fee2e2" stroke="#7f1d1d" stroke-width="3"/>
    </g>
    <rect x="102" y="192" width="156" height="124" rx="28" fill="#991b1b"/>
    <circle cx="102" cy="236" r="8" fill="#450a0a" stroke="#fecaca" stroke-width="3"/>
    <rect x="120" y="214" width="78" height="78" rx="14" fill="#1a1024"/>
    <path d="M136 236c16 18 8 20 22 34" stroke="#ef4444" stroke-width="3" fill="none"/>
    <path d="M150 230c10 16 28 8 30 28" stroke="#facc15" stroke-width="3" fill="none"/>
    <path d="M138 258c20-6 24 16 40 8" stroke="#4ade80" stroke-width="3" fill="none"/>
    <circle class="spark" cx="168" cy="250" r="5" fill="#fff"/>
    <g class="gear">
        <circle cx="166" cy="268" r="14" fill="none" stroke="#94a3b8" stroke-width="4"/>
        <path d="M166 250v6M166 280v6M148 268h6M178 268h6M154 256l4 4M174 276l4 4M174 256l-4 4M154 276l-4 4" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
        <circle cx="166" cy="268" r="4" fill="#e2e8f0"/>
    </g>
    <g class="hatch">
        <path d="M214 214l62 14-8 78-54 6z" fill="#fecaca" stroke="#fca5a5"/>
        <circle cx="236" cy="236" r="4" fill="#7f1d1d"/>
        <circle cx="246" cy="276" r="4" fill="#7f1d1d"/>
    </g>
    <rect x="124" y="328" width="40" height="22" rx="10" fill="#7f1d1d"/>
    <rect x="196" y="328" width="40" height="22" rx="10" fill="#7f1d1d"/>
</svg>
@endsection

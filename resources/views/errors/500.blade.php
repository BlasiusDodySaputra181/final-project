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
    <button class="btn" type="button" data-error-action="reload">Coba lagi</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
    <object class="bot bot-500" type="image/svg+xml" data="{{ asset('svg/errors/bot-500.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

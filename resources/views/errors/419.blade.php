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
    <button class="btn" type="button" data-error-action="reload">Muat ulang halaman</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
    <object class="bot bot-419" type="image/svg+xml" data="{{ asset('svg/errors/bot-419.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

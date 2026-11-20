@extends('errors.layout')

@section('title', 'Tidak ditemukan — ARSIP-TA')
@section('code', '404')
@section('label', 'Nyasar')
@section('heading', 'Halaman tidak ditemukan')
@section('voice', 'Robot pencari')
@section('defaultMessage')
Antena pencarinya patah dan laci arsipnya kosong. Robot ini sudah memutar rak, tetapi halaman yang kamu minta tidak ada di mana pun.
@endsection
@section('damage')
    <li>Antena patah</li>
    <li>Laci kosong</li>
    <li>Peta sobek</li>
@endsection
@section('hint')
Periksa kembali alamat halaman, atau kembali ke beranda.
@endsection
@section('actions')
    <a class="btn" href="{{ url('/') }}">Kembali ke beranda</a>
@endsection

@section('robot')
    <object class="bot bot-404" type="image/svg+xml" data="{{ asset('svg/errors/bot-404.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

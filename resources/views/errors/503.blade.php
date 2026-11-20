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
    <object class="bot bot-503" type="image/svg+xml" data="{{ asset('svg/errors/bot-503.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

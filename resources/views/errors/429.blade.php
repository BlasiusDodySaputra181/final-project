@extends('errors.layout')

@section('title', 'Terlalu banyak percobaan — ARSIP-TA')
@section('code', '429')
@section('label', 'Kepanasan')
@section('heading', 'Terlalu banyak percobaan')
@section('voice', 'Robot pendingin')
@section('defaultMessage')
Permintaan datang terlalu rapat. Kipas di kepala robot berputar kencang, uap keluar dari bahunya, dan matanya sudah berputar.
@endsection
@section('damage')
    <li>Kipas kelewat cepat</li>
    <li>Uap panas</li>
    <li>Mata berputar</li>
@endsection
@section('hint')
Tunggu sebentar sampai robotnya dingin, lalu coba lagi.
@endsection
@section('actions')
    <button class="btn" type="button" data-error-action="reload">Coba lagi</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
    <object class="bot bot-429" type="image/svg+xml" data="{{ asset('svg/errors/bot-429.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

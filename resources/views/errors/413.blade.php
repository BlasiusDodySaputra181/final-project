@extends('errors.layout')

@section('title', 'Berkas terlalu besar — ARSIP-TA')
@section('code', '413')
@section('label', 'Kelebihan beban')
@section('heading', 'Unggahan terlalu besar')
@section('voice', 'Robot pengangkut')
@section('defaultMessage')
Peti berkas ini lebih besar dari badan robotnya. Pegas kaki sudah melengkung, kertas meluber, dan lampu beban menyala merah.
@endsection
@section('damage')
    <li>Peti meluber</li>
    <li>Pegas melengkung</li>
    <li>Lampu beban merah</li>
@endsection
@section('hint')
Perkecil ukuran berkas atau data yang dikirim, lalu coba lagi.
@endsection
@section('actions')
    <button class="btn" type="button" data-error-action="back">Kembali dan perkecil berkas</button>
    <a class="btn btn-ghost" href="{{ url('/') }}">Beranda</a>
@endsection

@section('robot')
    <object class="bot bot-413" type="image/svg+xml" data="{{ asset('svg/errors/bot-413.svg') }}" width="360" height="420" aria-hidden="true" tabindex="-1"></object>
@endsection

@extends('module.outline.apps')

@section('content')
<div class="dashboard" id="dashboard">
<div class="dashboard-actions" id="dashboardactions">
    <div class="access-qr-copy">
        <div class="access-qr-icon" aria-hidden="true">
            <i class="fas fa-qrcode"></i>
        </div>
        <div>
            <h2>Kode QR Akses</h2>
            @if ($onlineLogin !== '')
                <p>Pindai kode ini dari Wi-Fi, data seluler, atau perangkat lain, di lokasi mana pun. Tautan Worker ini tersimpan sekali dan tidak berubah selamanya. Cetak pratinjau ini untuk petugas dan koordinator. Komputer ini harus menyala dan tersambung ke internet.</p>
            @else
                <p>Simpan satu tautan Worker Cloudflare di peladen lokal. Setelah tersimpan, kode QR di sini tetap sama.</p>
            @endif
            <span class="dashboard-actions-url" id="dashboardappurl">{{ $onlineLogin !== '' ? $onlineLogin : 'Tautan Worker belum tersimpan.' }}</span>
        </div>
    </div>
    @if ($onlineLogin !== '')
        <img class="dashboard-access-qr" id="dashboardaccessqr"
            src="{{ route('access-qr-png') }}?v={{ md5($onlineLogin) }}"
            alt="Pratinjau kode QR akses daring"
            width="148" height="148">
    @endif
    <button type="button" class="btn btn-theme" id="buttongenerateaccessqr"
        data-pdf-url="{{ route('pdf-access-qrcode') }}"
        data-app-url="{{ $onlineLogin }}"
        title="{{ $onlineLogin === '' ? 'Simpan tautan Worker di peladen lokal terlebih dahulu.' : 'Buka PDF untuk dilihat dan dicetak.' }}"
        @disabled($onlineLogin === '')>
        <i class="fas fa-print"></i>
        Lihat dan cetak
    </button>
</div>

<div class="stats" id="dashboardstats">

<div class="card" id="statvisitors">
<div class="icon-box orange">
<i class="fas fa-users"></i>
</div>
<h2 title="{{ number_format($totalVisitors, 0, ',', '.') }}">{{ format_count_label($totalVisitors) }}</h2>
<p>Total Pengunjung</p>
</div>

<div class="card" id="statfinalprojects">
<div class="icon-box purple">
<i class="fas fa-graduation-cap"></i>
</div>
<h2 title="{{ number_format($totalFinalProjects, 0, ',', '.') }}">{{ format_count_label($totalFinalProjects) }}</h2>
<p>Total Tugas Akhir</p>
</div>

<div class="card" id="statundertenyears">
<div class="icon-box blue">
<i class="fas fa-calendar-check"></i>
</div>
<h2 title="{{ number_format($underTenYears, 0, ',', '.') }}">{{ format_count_label($underTenYears) }}</h2>
<p>Berlaku</p>
</div>

<div class="card" id="statovertenyears">
<div class="icon-box red">
<i class="fas fa-calendar-xmark"></i>
</div>
<h2 title="{{ number_format($overTenYears, 0, ',', '.') }}">{{ format_count_label($overTenYears) }}</h2>
<p>Tidak Berlaku</p>
</div>

</div>

<div class="grid" id="dashboardcharts">

<div class="chart-card" id="chartbarcard">

<div class="chart-title">
Statistik Tugas Akhir
</div>

<div class="chart-wrap">
  <canvas id="chartsbar" data-under-ten-years="{{ $underTenYears }}" data-over-ten-years="{{ $overTenYears }}"></canvas>
</div>

</div>

<div class="chart-card" id="chartpiecard">

<div class="chart-title">
Persentase Tugas Akhir
</div>

<div class="chart-wrap">
  <canvas id="chartspie"></canvas>
</div>

</div>

</div>
</div>
@endsection

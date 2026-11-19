@extends('module.outline.apps')

@section('content')
<div class="table-card profile-card local-server" id="localdatabase">

    <div class="form-section">
        <div class="section-title">
            <span class="section-badge">
                <i class="fas fa-database"></i>
            </span>
            PANEL BASIS DATA
        </div>

        <div class="info-box">
            <i class="fas fa-circle-info"></i>
            <span>
                Panel ini menggantikan phpMyAdmin dan hanya terbuka di komputer tempat aplikasi dipasang.
                Klien yang membuka tautan internet tidak dapat masuk ke halaman ini.
                Data diubah lewat modul aplikasi. Kata sandi tidak ditampilkan.
                Basis data yang sedang terbuka: <strong>{{ strtoupper($driver) }}</strong>.
            </span>
        </div>

        <p class="storage-path-hint">
            <a href="{{ route('page-local-server') }}">Kembali ke peladen lokal</a>
        </p>

        @include('admin.local-server.exchange')

        @if ($error !== '')
            <div class="info-box info-box--warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ $error }}</span>
            </div>
        @endif

        @if ($tables === [])
            <p>Belum ada tabel. Jalankan pemasangan dari halaman peladen lokal.</p>
        @else
            <div class="local-table-list" id="localdatabasetables">
                @foreach ($tables as $name)
                    <a href="{{ route('page-local-database', ['table' => $name]) }}" @class(['is-active' => $name === $table])>{{ $name }}</a>
                @endforeach
            </div>

            <p class="storage-path-hint" id="localdatabasetable">
                {{ $table }} — {{ number_format($total, 0, ',', '.') }} baris
            </p>

            <div class="local-db-wrap">
                <table class="local-db-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>
                                    <a href="{{ route('page-local-database', ['table' => $table, 'sort' => $column, 'dir' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc']) }}">
                                        {{ $column }}
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(1, count($columns)) }}">Tabel kosong.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="local-server-actions">
                @if ($page > 1)
                    <a class="btn btn-default" href="{{ route('page-local-database', ['table' => $table, 'page' => $page - 1, 'sort' => $sort, 'dir' => $direction]) }}">Sebelumnya</a>
                @endif
                @if ($page * 40 < $total)
                    <a class="btn btn-default" href="{{ route('page-local-database', ['table' => $table, 'page' => $page + 1, 'sort' => $sort, 'dir' => $direction]) }}">Berikutnya</a>
                @endif
            </div>
        @endif
    </div>

</div>
@endsection

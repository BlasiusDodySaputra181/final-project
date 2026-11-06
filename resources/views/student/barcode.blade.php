@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Pindai Kode Batang')
@section('body-class', 'auth-barcode')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-barcode.css') }}">
@endpush

@section('standalone')
<div class="scan-card" id="publicbarcodepage">

    <div class="scan-header">
        <div class="scan-icon">
            <i class="fas fa-book-open" aria-hidden="true"></i>
        </div>

        <h1>Pindai Kode Batang</h1>

        <p>
            Arahkan kode batang ke kamera, baik dari kertas, layar monitor, maupun layar ponsel. Atau pilih gambar dari galeri.
        </p>
    </div>

    <div id="scanalertslot">
        @if (session('error'))
            <div class="scan-alert scan-alert--error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @error('barcode')
            <div class="scan-alert scan-alert--error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>{{ $message }}</span>
            </div>
        @enderror

        @if (session('success'))
            <div class="scan-alert scan-alert--success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
    </div>

    {{-- Form utama: kamera & input manual memakai form yang sama --}}
    <form
        id="barcodeLookupForm"
        method="POST"
        action="{{ route('lookup-public-barcode') }}"
        autocomplete="off"
        novalidate
    >
        @csrf

        @include('student.partials.barcode-scanner')

        <div class="scan-manual">
            <label for="lookupBarcodeInput" class="scan-manual-label">
                Atau masukkan kode batang secara manual
            </label>
            <div class="scan-manual-row">
                <input
                    type="text"
                    id="lookupBarcodeInput"
                    name="barcode"
                    class="scan-manual-input @error('barcode') is-invalid @enderror"
                    value="{{ old('barcode') }}"
                    placeholder="Contoh: 89010012024001"
                    required
                    aria-describedby="lookupBarcodeError"
                >
                <button type="submit" id="manualLookupBtn" class="scan-btn scan-btn--primary scan-manual-btn">
                    <i class="fas fa-magnifying-glass"></i> Cari
                </button>
            </div>
            <div
                id="lookupBarcodeError"
                class="field-error"
                role="alert"
                @unless ($errors->has('barcode')) hidden @endunless
            >{{ $errors->first('barcode') }}</div>
        </div>

        @include('student.partials.barcode-tips')

        <div class="scan-actions">
            <button type="button" id="startScan" class="scan-btn scan-btn--primary">
                <i class="fas fa-camera"></i> Mulai Pindai
            </button>

            <button type="button" id="scanImageBtn" class="scan-btn scan-btn--primary">
                <i class="fas fa-image"></i> Pilih Gambar
            </button>

            <button type="button" id="stopScan" class="scan-btn scan-btn--secondary">
                <i class="fas fa-stop"></i> Hentikan Pindai
            </button>
        </div>

        <input
            type="file"
            id="barcodeImageInput"
            accept="image/*"
            hidden
        >
    </form>

    <div class="scan-footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ asset_version('js/vendor/html5-qrcode.min.js') }}" defer></script>
<script src="{{ asset_version('js/barcode-camera.js') }}" defer></script>
<script src="{{ asset_version('js/student-barcode.js') }}" defer></script>
@endpush

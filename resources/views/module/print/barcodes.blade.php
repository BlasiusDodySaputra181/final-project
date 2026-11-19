<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kode Batang Pengenal Tugas Akhir</title>
<link rel="stylesheet" href="{{ asset_version('css/barcode-print.css') }}">
</head>
<body>
    <div class="print-toolbar">
        <p>Pratinjau cetak • Kertas A4 • Landscape</p>
        <button type="button" id="printbarcodes">Cetak</button>
    </div>

    <div class="print-stage">
        @forelse ($pages as $pageItems)
            <div class="sheet">
                <div class="barcode-grid cols-{{ (int) $columns }}">
                    @foreach ($pageItems as $item)
                        <article class="barcode-cell">
                            <div class="barcode-sticker">
                                {!! $item['svg'] !!}
                                <p class="barcode-code">{{ $item['barcode'] !== '' ? $item['barcode'] : '-' }}</p>
                                <p class="barcode-label">{{ $item['label'] !== '' ? $item['label'] : '-' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="sheet">
                <p class="empty">Tidak ada data kode batang untuk diunduh.</p>
            </div>
        @endforelse
    </div>
<script src="{{ asset_version('js/barcode-print.js') }}" defer></script>
</body>
</html>

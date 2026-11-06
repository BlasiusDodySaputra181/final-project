@php
    $summaryTitle = $title ?? 'Ringkasan Data';
    $infoText = $info ?? 'Pastikan data yang diisi lengkap dan benar sebelum melanjutkan ke tahap berikutnya.';
    $summaryItems = $items ?? [
        ['label' => 'Tahap', 'value' => $stage ?? '1 / 3'],
        ['label' => 'Progres', 'value' => $progress ?? '33%'],
        ['label' => 'Status', 'value' => $status ?? 'Draft'],
    ];
@endphp

<div class="sidebar">
    <div class="info-card">
        <h3>{{ $summaryTitle }}</h3>

        @foreach ($summaryItems as $item)
            <div class="summary-item">
                <span>{{ $item['label'] }}</span>
                <span>{{ $item['value'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="info-card">
        <h3>Informasi</h3>
        <p>{{ $infoText }}</p>
    </div>
</div>

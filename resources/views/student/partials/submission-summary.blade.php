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

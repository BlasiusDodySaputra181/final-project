@php
    $degreeLevelOptions = collect($finalProjectTypes ?? [])
        ->filter(fn ($type) => trim((string) ($type->level ?? '')) !== '')
        ->values();
@endphp

@forelse ($degreeLevelOptions as $finalProjectType)
    @php
        $degreeLabel = trim($finalProjectType->level.($finalProjectType->type ? ' — '.$finalProjectType->type : ''));
    @endphp
    <div
        class="custom-option"
        data-id="{{ $finalProjectType->id }}"
        data-value="{{ $finalProjectType->level }}"
        data-label="{{ $degreeLabel }}"
    >{{ $degreeLabel }}</div>
@empty
    <div class="custom-select-empty" data-static-empty="true">Belum ada data jenjang</div>
@endforelse

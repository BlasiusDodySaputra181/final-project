@forelse ($degreeLevelOptions as $finalProjectType)
    <div
        class="custom-option"
        data-id="{{ $finalProjectType->id }}"
        data-value="{{ $finalProjectType->level }}"
        data-label="{{ degree_type_label($finalProjectType) }}"
    >{{ degree_type_label($finalProjectType) }}</div>
@empty
    <div class="custom-select-empty" data-static-empty="true">Belum ada data jenjang</div>
@endforelse

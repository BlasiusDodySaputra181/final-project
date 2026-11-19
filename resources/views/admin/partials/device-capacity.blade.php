<section class="capacity-card" id="devicecapacity" aria-label="Kapasitas memori perangkat">
    <div class="capacity-head">
        <div>
            <h2>Kapasitas memori perangkat</h2>
            <p>
                Sisa ruang pada perangkat tempat aplikasi terpasang.
                Persentase basis data dan berkas arsip tugas akhir yang diunggah petugas atau mahasiswa dihitung terpisah.
            </p>
        </div>
        @if ($capacity['readable'] && $capacity['volume'] !== '')
            <span class="capacity-volume">{{ $capacity['volume'] }}</span>
        @endif
    </div>

    @unless ($capacity['readable'])
        <p class="capacity-note">Kapasitas perangkat tidak dapat dibaca dari komputer ini.</p>
    @else
        <div class="capacity-meter" id="capacityfree">
            <div class="capacity-meter-label">
                <span>Memori masih tersedia</span>
                <strong>{{ $capacity['free_percent_label'] }}</strong>
            </div>
            <div
                class="capacity-track"
                role="meter"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ (int) round(min(100, $capacity['free_percent'])) }}"
                aria-label="Memori masih tersedia {{ $capacity['free_percent_label'] }}"
            >
                <span class="capacity-fill capacity-fill--free capacity-fill--{{ $capacity['free_level'] }}" data-width="{{ $capacity['free_width'] }}"></span>
            </div>
            <p>{{ $capacity['free_label'] }} tersedia dari {{ $capacity['total_label'] }}</p>
        </div>

        <div class="capacity-split">
            <div class="capacity-meter" id="capacitydatabase">
                <div class="capacity-meter-label">
                    <span>Basis data</span>
                    <strong>{{ $capacity['database_percent_label'] }}</strong>
                </div>
                <div
                    class="capacity-track"
                    role="meter"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ (int) round(min(100, $capacity['database_percent'])) }}"
                    aria-label="Basis data memakai {{ $capacity['database_percent_label'] }} memori perangkat"
                >
                    <span class="capacity-fill capacity-fill--database" data-width="{{ $capacity['database_width'] }}"></span>
                </div>
                <p>{{ $capacity['database_label'] }} dari memori perangkat</p>
            </div>

            <div class="capacity-meter" id="capacityarchive">
                <div class="capacity-meter-label">
                    <span>Berkas arsip tugas akhir</span>
                    <strong>{{ $capacity['archive_percent_label'] }}</strong>
                </div>
                <div
                    class="capacity-track"
                    role="meter"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ (int) round(min(100, $capacity['archive_percent'])) }}"
                    aria-label="Berkas arsip tugas akhir memakai {{ $capacity['archive_percent_label'] }} memori perangkat"
                >
                    <span class="capacity-fill capacity-fill--archive" data-width="{{ $capacity['archive_width'] }}"></span>
                </div>
                <p>{{ $capacity['archive_label'] }} dari memori perangkat</p>
            </div>
        </div>

        @if ($capacity['archive_other_volume'])
            <p class="capacity-note">
                Berkas arsip berada di {{ $capacity['archive_volume'] }}.
                Persentase tetap dibandingkan dengan kapasitas perangkat aplikasi ({{ $capacity['volume'] }}).
            </p>
        @endif
    @endunless
</section>

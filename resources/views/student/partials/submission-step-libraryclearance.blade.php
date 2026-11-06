<section class="slide" data-step="3">
    <div class="content">
        <div class="section">
            <div class="section-title">
                <i class="fas fa-file-circle-check"></i> Bebas Pustaka
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="phone_number" class="required-label">Nomor Telepon</label>
                    <input
                        type="text"
                        id="phone_number"
                        name="phone_number"
                        value="{{ old('phone_number') }}"
                        placeholder="08xxxxxxxxxx"
                        autocomplete="tel"
                        required
                    >
                    @error('phone_number')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="required-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="nama@email.com"
                        autocomplete="email"
                        required
                    >
                    @error('email')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <p class="clearance-note">
                Tahun lulus dan semester lulus diambil dari data akademik pada tahap pertama
                untuk melengkapi data bebas pustaka.
            </p>

            <div class="actions">
                <button type="button" class="btn btn-outline prev-btn">
                    <i class="fas fa-arrow-left"></i> Kembali Tahap Sebelumnya
                </button>
                <button type="submit" class="btn btn-primary" id="submitSubmissionBtn">
                    Simpan Penyerahan <i class="fas fa-floppy-disk"></i>
                </button>
            </div>
        </div>

        @include('student.partials.submission-summary', [
            'stage' => '3 / 3',
            'progress' => '100%',
            'status' => 'Bebas Pustaka',
            'info' => 'Pastikan email dan nomor telepon aktif untuk memudahkan komunikasi pemberitahuan lebih lanjut.',
        ])
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
</section>

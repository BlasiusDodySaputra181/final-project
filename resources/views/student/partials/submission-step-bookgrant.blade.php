@php
    $oldBooks = old('books', [['author' => '', 'book_year' => '', 'book_title' => '']]);
    if ($oldBooks === [] || ! is_array($oldBooks)) {
        $oldBooks = [['author' => '', 'book_year' => '', 'book_title' => '']];
    }
    $withoutBooks = (string) old('without_books', '0') === '1';
@endphp

<section class="slide" data-step="2">
    <div class="content">
        <div class="section">
            <div class="section-title">
                <i class="fas fa-book"></i> Hibah Buku
                <span class="book-count-badge" id="bookCountBadge">1 buku</span>
            </div>

            <p class="book-rule-note">
                Hibah buku bersifat opsional. Centang <strong>Tanpa Buku</strong> jika tidak menghibahkan,
                atau isi satu/lebih formulir buku (gunakan <strong>Tambah Formulir</strong>) —
                setiap buku akan tersimpan dan terhubung ke tugas akhir Anda.
            </p>

            <div class="checkbox-wrapper">
                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        id="without_books"
                        name="without_books"
                        value="1"
                        @checked($withoutBooks)
                    >
                    <span>Tanpa Buku</span>
                </label>
                <small class="checkbox-hint">
                    Pilih opsi ini jika tidak menghibahkan buku (misalnya diganti pembayaran kepada petugas).
                </small>
            </div>

            <div class="book-form-wrapper @if ($withoutBooks) form-disabled @endif" id="bookFormWrapper">
                <div id="booksContainer">
                    @foreach ($oldBooks as $index => $book)
                        <div class="book-entry @if ($index > 0) clone-card @endif" data-book-index="{{ $index }}">
                            <div class="clone-header">
                                <div class="clone-title">Form Buku {{ $index + 1 }}</div>
                                @if ($index > 0)
                                    <button type="button" class="btn btn-danger remove-book-btn">
                                        Hapus Formulir <i class="fas fa-minus"></i>
                                    </button>
                                @endif
                            </div>

                            <div class="form-grid book-form">
                                <div class="form-group">
                                    <label class="required-label">Pengarang</label>
                                    <input
                                        type="text"
                                        name="books[{{ $index }}][author]"
                                        value="{{ $book['author'] ?? '' }}"
                                        placeholder="Nama Pengarang"
                                    >
                                    @error("books.$index.author")
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="required-label">Tahun Buku</label>
                                    <input
                                        type="text"
                                        name="books[{{ $index }}][book_year]"
                                        class="years-picker"
                                        value="{{ $book['book_year'] ?? '' }}"
                                        placeholder="Pilih Tahun"
                                        readonly
                                    >
                                    @error("books.$index.book_year")
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group full">
                                    <label class="required-label">Judul Buku</label>
                                    <textarea
                                        name="books[{{ $index }}][book_title]"
                                        placeholder="Judul Buku"
                                    >{{ $book['book_title'] ?? '' }}</textarea>
                                    @error("books.$index.book_title")
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="tpl-book-entry">
                    <div class="book-entry clone-card" data-book-index="0">
                        <div class="clone-header">
                            <div class="clone-title">Form Buku 1</div>
                            <button type="button" class="btn btn-danger remove-book-btn">
                                Hapus Formulir <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <div class="form-grid book-form">
                            <div class="form-group">
                                <label class="required-label">Pengarang</label>
                                <input type="text" name="books[0][author]" placeholder="Nama Pengarang">
                            </div>
                            <div class="form-group">
                                <label class="required-label">Tahun Buku</label>
                                <input type="text" name="books[0][book_year]" class="years-picker" placeholder="Pilih Tahun" readonly>
                            </div>
                            <div class="form-group full">
                                <label class="required-label">Judul Buku</label>
                                <textarea name="books[0][book_title]" placeholder="Judul Buku"></textarea>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="book-actions">
                    <button type="button" class="btn btn-primary" id="addBookBtn">
                        Tambah Formulir <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-outline prev-btn">
                    <i class="fas fa-arrow-left"></i> Kembali Tahap Sebelumnya
                </button>
                <button type="button" class="btn btn-primary next-btn">
                    Lanjut Tahap Berikutnya <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        @include('student.partials.submission-summary', [
            'stage' => '2 / 3',
            'progress' => '66%',
            'status' => 'Hibah Buku',
            'info' => 'Tanpa Buku = tidak ada hibah. Tambah Formulir = hibah lebih dari satu buku. Semua buku tersimpan dengan relasi ke tugas akhir.',
        ])
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
</section>

$(document).ready(function () {

    $('body').on('click', '#buttongenerateaccessqr', function (event) {
        event.preventDefault();

        if (typeof OfflineSync !== 'undefined' && !OfflineSync.requireOnline(
            'Gagal generate QR code.',
            'Unduh PDF QR akses membutuhkan koneksi daring.'
        )) return;

        const pdfUrl = $(this).data('pdf-url');

        if (!pdfUrl) {
            if (typeof showError === 'function') {
                showError('Gagal generate QR code.', 'Alamat PDF tidak ditemukan.');
            }
            return;
        }

        window.open(pdfUrl, '_blank');
    });

});

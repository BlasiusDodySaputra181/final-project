$(document).ready(function () {

    $('body').on('click', '#buttongenerateaccessqr', async function (event) {
        event.preventDefault();

        const button = this;

        const pdfUrl = $(button).data('pdf-url');

        if (!pdfUrl) {
            if (typeof showError === 'function') {
                showError('Gagal membuat kode QR.', 'Alamat PDF tidak ditemukan.');
            }
            return;
        }

        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Menyiapkan pratinjau...';

        try {
            const response = await fetch(pdfUrl, {
                headers: {
                    Accept: 'application/pdf, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const type = response.headers.get('content-type') || '';

            if (!response.ok || type.includes('json')) {
                const data = await response.json().catch(() => ({}));
                const detail = data.message || 'Kode QR daring belum siap.';
                if (typeof showError === 'function') {
                    showError('Gagal membuat kode QR.', detail);
                }
                return;
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            window.open(objectUrl, '_blank');
        } catch {
            if (typeof showError === 'function') {
                showError('Gagal membuat kode QR.', 'Komputer ini tidak menjawab.');
            }
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });

});

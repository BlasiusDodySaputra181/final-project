const printMonths = [
    'januari', 'februari', 'maret', 'april', 'mei', 'juni',
    'juli', 'agustus', 'september', 'oktober', 'november', 'desember',
    'jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'
];

const printMonthIndex = Object.fromEntries(
    printMonths.map((name, index) => [name, (index % 12) + 1])
);

const formatPrintCell = value => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return '-';
    }

    return String(value);
};

const padPrintDatePart = value => String(value).padStart(2, '0');

const formatPrintDate = value => {
    const text = formatPrintCell(value).trim();
    if (text === '-') return text;

    const isoMatch = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/);
    if (isoMatch) {
        return `${padPrintDatePart(isoMatch[3])}/${padPrintDatePart(isoMatch[2])}/${isoMatch[1]}`;
    }

    const slashMatch = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+.*)?$/);
    if (slashMatch) {
        return `${padPrintDatePart(slashMatch[1])}/${padPrintDatePart(slashMatch[2])}/${slashMatch[3]}`;
    }

    const namedMatch = text.match(/^(\d{1,2})\s+([A-Za-z.]+)\s+(\d{4})(?:\s+.*)?$/);
    if (namedMatch) {
        const month = printMonthIndex[namedMatch[2].replace(/\.$/, '').toLowerCase()];
        if (month) {
            return `${padPrintDatePart(namedMatch[1])}/${padPrintDatePart(month)}/${namedMatch[3]}`;
        }
    }

    return text;
};

const printCellClass = options => {
    const classes = [`col-${options?.align || 'left'}`];
    if (options?.nowrap) classes.push('col-nowrap');
    if (options?.type === 'barcode') classes.push('col-barcode');
    return classes.join(' ');
};

const printCellText = (raw, options) => {
    let display = options?.type === 'date' ? formatPrintDate(raw) : formatPrintCell(raw);

    if (options?.wrapDate) {
        display = display.replace(/^(\d{2}\/\d{2}\/)(\d{4})$/, '$1\u200b$2');
    }

    return display;
};

const buildPrintCell = (row, columnIndex, options, rowNumber) => {
    const cell = document.createElement('td');
    const raw = columnIndex === 0 ? rowNumber : row[columnIndex];

    if (options?.type === 'barcode') {
        const code = String(raw ?? '').trim();

        if (!code) {
            cell.className = printCellClass({ ...options, type: null });
            cell.textContent = '-';
            return cell;
        }

        cell.className = printCellClass(options);
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'barcode-code128');
        svg.dataset.barcode = code;
        const caption = document.createElement('div');
        caption.className = 'barcode-text';
        caption.textContent = code;
        cell.append(svg, caption);
        return cell;
    }

    cell.className = printCellClass(options);
    cell.textContent = printCellText(raw, options);
    return cell;
};

const paintPrintBarcodes = root => {
    if (typeof JsBarcode === 'undefined') return;

    root.querySelectorAll('svg.barcode-code128[data-barcode]').forEach(svg => {
        const code = svg.getAttribute('data-barcode');
        if (!code) return;

        try {
            JsBarcode(svg, code, {
                format: 'CODE128',
                lineColor: '#111111',
                width: 1.1,
                height: 26,
                displayValue: false,
                margin: 0
            });
        } catch (_) { /* kode tidak valid untuk Code128 */ }
    });
};

const renderPrintPreview = payload => {
    const titleEl = document.getElementById('printdoctitle');
    const metaEl = document.getElementById('printdocmeta');
    const bodyEl = document.getElementById('printbody');
    if (!titleEl || !metaEl || !bodyEl || !payload) return;

    const title = payload.title || 'Dokumen';
    const subtitle = payload.subtitle || '';
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    const columns = Array.isArray(payload.columns) ? payload.columns : [];
    const printedAt = new Date(payload.printedAt || Date.now()).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    document.title = subtitle ? `${title} — ${subtitle}` : title;
    titleEl.textContent = title;
    metaEl.replaceChildren(document.createTextNode(subtitle));
    metaEl.append(document.createElement('br'));
    metaEl.append(document.createTextNode(`Dicetak: ${printedAt} | Total: ${rows.length} data | Kertas A4 Landscape`));

    bodyEl.replaceChildren();

    if (!rows.length) {
        const empty = document.createElement('p');
        empty.className = 'empty';
        empty.textContent = payload.emptyLabel || 'Tidak ada data.';
        bodyEl.append(empty);
        return;
    }

    const density = columns.length > 14 ? 'dense' : columns.length > 10 ? 'compact' : 'normal';
    const table = document.createElement('table');
    table.className = `print-table ${density}${payload.wrapCells ? ' wrap-cells' : ''}`;

    const colgroup = document.createElement('colgroup');
    const headRow = document.createElement('tr');

    columns.forEach(([label, , options]) => {
        const col = document.createElement('col');
        if (options?.width) col.style.width = options.width;
        colgroup.append(col);

        const th = document.createElement('th');
        th.className = printCellClass({ ...options, type: null });
        th.textContent = label || '';
        headRow.append(th);
    });

    const thead = document.createElement('thead');
    thead.append(headRow);

    const tbody = document.createElement('tbody');
    rows.forEach((row, index) => {
        const tr = document.createElement('tr');
        columns.forEach(([, columnIndex, options]) => {
            tr.append(buildPrintCell(row, columnIndex, options, index + 1));
        });
        tbody.append(tr);
    });

    table.append(colgroup, thead, tbody);
    bodyEl.append(table);
    paintPrintBarcodes(table);
};

const publishPrintPreview = payload => {
    const key = `arsip-print-${Date.now()}`;

    try {
        sessionStorage.setItem(key, JSON.stringify({
            title: payload.title,
            subtitle: payload.subtitle,
            emptyLabel: payload.emptyLabel,
            columns: payload.columns,
            rows: payload.rows,
            wrapCells: Boolean(payload.wrapCells),
            printedAt: new Date().toISOString()
        }));
    } catch (_) {
        if (typeof showError === 'function') {
            showError(payload.errorTitle || 'Gagal membuka pratinjau.', 'Data terlalu besar untuk pratinjau cetak.');
        }
        return;
    }

    const url = `/cetak/pratinjau?key=${encodeURIComponent(key)}`;
    const tab = payload.targetWindow;

    if (tab && !tab.closed) {
        tab.location.href = url;
        return;
    }

    const opened = window.open(url, '_blank');
    if (!opened && typeof showError === 'function') {
        sessionStorage.removeItem(key);
        showError(payload.errorTitle || 'Gagal membuka pratinjau.', 'Izinkan pop-up agar dokumen dapat dibuka.');
    }
};

const bootPrintPreview = () => {
    const button = document.getElementById('printpreview');
    button?.addEventListener('click', () => window.print());

    const key = new URLSearchParams(location.search).get('key');
    if (!key) return;

    let payload = null;
    try {
        payload = JSON.parse(sessionStorage.getItem(key) || 'null');
        sessionStorage.removeItem(key);
    } catch (_) {
        payload = null;
    }

    if (!payload) return;
    renderPrintPreview(payload);
};

if (document.getElementById('printbody')) {
    bootPrintPreview();
}

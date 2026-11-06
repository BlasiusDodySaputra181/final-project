/**
 * Format angka ringkas, sama dengan format_count_label PHP:
 * 1K = 1.000, 1M = 1.000.000, 1B = 1.000.000.000, 1G = 1.000.000.000.000.
 */
(function (root) {
    const suffixes = ['', 'K', 'M', 'B', 'G'];

    const formatCountLabel = value => {
        const n = Number(value);
        if (!Number.isFinite(n)) return String(value ?? '');

        const count = Math.round(n);
        const sign = count < 0 ? '-' : '';
        const abs = Math.abs(count);

        if (abs < 1000) {
            return String(count);
        }

        let exp = Math.min(4, Math.floor(Math.log10(abs) / 3));
        let compact = abs / (1000 ** exp);
        let rounded = compact >= 10 ? Math.round(compact) : Math.round(compact * 10) / 10;

        if (rounded >= 1000 && exp < 4) {
            exp += 1;
            compact = abs / (1000 ** exp);
            rounded = Math.round(compact * 10) / 10;
        }

        const text = Number.isInteger(rounded) ? String(rounded) : String(rounded);

        return sign + text + suffixes[exp];
    };

    const formatCountFull = value => {
        const n = Number(value);
        if (!Number.isFinite(n)) return String(value ?? '');

        return Math.round(n).toLocaleString('id-ID');
    };

    const renderCountLabel = (data, type) => {
        if (type === 'sort' || type === 'type') {
            const numeric = Number(data);
            return Number.isFinite(numeric) ? numeric : data;
        }

        if (data == null || data === '') {
            return type === 'display' ? '' : data;
        }

        const numeric = Number(data);
        if (!Number.isFinite(numeric)) {
            return data;
        }

        if (type !== 'display') {
            return data;
        }

        const label = formatCountLabel(numeric);
        const full = formatCountFull(numeric);

        return countMarkup(label, full);
    };

    const dataTablesInfoCallback = (settings, start, end, max, total) => {
        if (!total) {
            return settings?.oLanguage?.sInfoEmpty || 'Tidak ada data';
        }

        let text = `Tampil ${formatCountFull(start)} - ${formatCountFull(end)} dari ${formatCountLabel(total)} data`;

        if (max > total) {
            text += ` (dari ${formatCountLabel(max)} total)`;
        }

        return text;
    };

    const slimDataTablesRequest = (dtRequest, extras) => {
        const request = dtRequest || {};
        const order = Array.isArray(request.order) && request.order.length ? request.order[0] : null;
        const payload = Object.assign({}, extras || {}, {
            draw: request.draw ?? 1,
            start: request.start ?? 0,
            length: request.length ?? 25,
            'search[value]': request.search?.value || ''
        });

        if (request.export) {
            payload.export = request.export;
        }

        if (!order) {
            return payload;
        }

        const columnIndex = Number(order.column ?? 0);
        const column = request.columns?.[columnIndex] || {};
        payload['order[0][column]'] = columnIndex;
        payload['order[0][dir]'] = order.dir || 'asc';
        payload['columns[' + columnIndex + '][name]'] = column.name || '';

        return payload;
    };

    root.formatCountLabel = formatCountLabel;
    root.formatCountFull = formatCountFull;
    root.renderCountLabel = renderCountLabel;
    root.dataTablesInfoCallback = dataTablesInfoCallback;
    root.slimDataTablesRequest = slimDataTablesRequest;
})(typeof window !== 'undefined' ? window : globalThis);

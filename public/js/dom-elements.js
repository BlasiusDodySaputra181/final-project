/**
 * Bangun elemen dengan API DOM.
 * Serialisasi hanya dipakai untuk pustaka yang menerima string (DataTables).
 */
const faIcon = name => {
    const icon = document.createElement('i');
    const parts = String(name || '').split(/\s+/).filter(Boolean);

    if (!parts.some(part => part === 'fas' || part === 'fa-solid' || part === 'far' || part === 'fab' || part === 'fa-regular' || part === 'fa-brands')) {
        parts.unshift('fas');
    }

    icon.className = parts.join(' ');
    icon.setAttribute('aria-hidden', 'true');
    return icon;
};

const markup = node => {
    const host = document.createElement('template');
    host.content.append(node);
    return host.innerHTML;
};

const detailItem = (label, value) => {
    const item = document.createElement('div');
    item.className = 'detail-item';

    const labelEl = document.createElement('label');
    labelEl.textContent = label ?? '';

    const span = document.createElement('span');
    if (value instanceof Node) {
        span.append(value);
    } else if (value == null) {
        span.textContent = '';
    } else {
        span.textContent = String(value);
    }

    item.append(labelEl, span);
    return item;
};

const detailMarkup = (items, gridClass = 'detail-grid') => {
    const container = document.createElement('div');
    container.className = 'detail-container';

    const grid = document.createElement('div');
    grid.className = gridClass;

    items.forEach(item => {
        if (item instanceof Node) {
            grid.append(item);
            return;
        }

        const [label, value] = item;
        grid.append(detailItem(label, value));
    });

    container.append(grid);
    return markup(container);
};

const indexedDetail = (row, pairs, gridClass = 'detail-grid', fallback = '-') => detailMarkup(
    pairs.map(([label, index]) => [label, row?.[index] ?? fallback]),
    gridClass
);

const checkboxControl = ({ className, tooltip, inputId, inputClass, value, checked = false, role, ariaLabel }) => {
    const label = document.createElement('label');
    label.className = className;
    if (tooltip) label.dataset.tooltip = tooltip;

    const input = document.createElement('input');
    input.type = 'checkbox';
    if (inputId) input.id = inputId;
    if (inputClass) input.className = inputClass;
    if (value != null) input.value = String(value);
    input.checked = Boolean(checked);
    if (role) input.setAttribute('role', role);
    if (ariaLabel) input.setAttribute('aria-label', ariaLabel);

    const mark = document.createElement('span');
    if (role === 'switch') mark.className = 'switch-access-track';

    label.append(input, mark);
    return label;
};

const headerCheckMarkup = (id, tooltip, extraClass = '') => markup(checkboxControl({
    className: ['checkbox-modern', 'button-tooltip-target', extraClass].filter(Boolean).join(' '),
    tooltip,
    inputId: id
}));

const rowCheckMarkup = (tooltip, value, { inputClass = 'row-check', checked = false, extraClass = '' } = {}) => markup(checkboxControl({
    className: ['checkbox-modern', 'button-tooltip-target', extraClass].filter(Boolean).join(' '),
    tooltip,
    inputClass,
    value,
    checked
}));

const accessSwitch = ({ tooltip, inputClass, value, checked, ariaLabel, extraClass = '' }) => checkboxControl({
    className: ['switch-access', 'switch-access-row', 'button-tooltip-target', extraClass].filter(Boolean).join(' '),
    tooltip,
    inputClass,
    value,
    checked,
    role: 'switch',
    ariaLabel
});

const badge = (className, text) => {
    const span = document.createElement('span');
    span.className = className;
    span.textContent = text ?? '';
    return span;
};

const badgeMarkup = (className, text) => markup(badge(className, text));

const detailToggle = () => {
    const wrap = document.createElement('div');
    wrap.className = 'detail-toggle';
    wrap.append(faIcon('fa-chevron-right'));
    return wrap;
};

const detailToggleMarkup = () => markup(detailToggle());

const iconButton = ({ id, type, className, tooltip, icon, dataId, dataset = {} }) => {
    const button = document.createElement('button');
    if (type) button.type = type;
    if (id) button.id = id;
    button.className = className;
    if (tooltip) button.dataset.tooltip = tooltip;
    if (dataId != null) button.setAttribute('data-id', String(dataId));

    Object.entries(dataset).forEach(([key, value]) => {
        if (value != null) button.dataset[key] = String(value);
    });

    button.append(faIcon(icon));
    return button;
};

const buttonsMarkup = (buttons, { group = false } = {}) => {
    if (group) {
        const wrap = document.createElement('div');
        wrap.className = 'table-actions';
        buttons.forEach(button => wrap.append(button));
        return markup(wrap);
    }

    const fragment = document.createDocumentFragment();
    buttons.forEach((button, index) => {
        if (index > 0) fragment.append(document.createTextNode(' '));
        fragment.append(button);
    });
    return markup(fragment);
};

const editDeleteMarkup = (editId, deleteId, payload, rowId, { group = false, leading = [] } = {}) => buttonsMarkup([
    ...leading,
    iconButton({
        id: editId,
        className: 'icon-edit button-tooltip-target',
        tooltip: 'Ubah',
        icon: 'fa-pen',
        dataId: payload
    }),
    iconButton({
        id: deleteId,
        className: 'icon-delete button-tooltip-target',
        tooltip: 'Hapus',
        icon: 'fa-trash-can',
        dataId: rowId
    })
], { group });

const customOption = ({ id, value, label, selected = false, dataLabel = null }) => {
    const option = document.createElement('div');
    option.className = selected ? 'custom-option selected' : 'custom-option';
    option.dataset.id = id == null ? '' : String(id);
    option.dataset.value = value == null ? '' : String(value);
    if (dataLabel != null) option.dataset.label = String(dataLabel);
    option.textContent = label == null ? '' : String(label);
    return option;
};

const fileSizeSpan = path => {
    const span = document.createElement('span');
    span.dataset.fileSizePath = path || '';
    span.textContent = typeof getStoredFileSize === 'function' ? getStoredFileSize(path) : '…';
    return span;
};

const fileDetailItem = (label, { href = '#', className = '', fileName = '', filePath = null, plainSize = null, dataset = {} }) => {
    const content = document.createElement('span');
    content.append(faIcon('fa-download'), document.createTextNode(' '));

    const link = document.createElement('a');
    link.href = href || '#';
    if (className) link.className = className;
    Object.entries(dataset).forEach(([key, value]) => {
        if (value != null) link.dataset[key] = String(value);
    });
    link.append(document.createTextNode(fileName || ''));

    if (filePath != null) {
        link.append(document.createTextNode(' ('), fileSizeSpan(filePath), document.createTextNode(' MB)'));
    } else if (plainSize != null) {
        link.append(document.createTextNode(` (${plainSize} MB)`));
    }

    content.append(link);
    return detailItem(label, content);
};

const barcodeSvg = (code, { height = 40, displayValue = true } = {}) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'barcode-code128');
    svg.dataset.barcode = String(code);
    svg.dataset.height = String(height);
    svg.dataset.displayValue = displayValue ? '1' : '0';
    return svg;
};

const fillCheckedFileName = (el, name, sizeLabel = '') => {
    el.replaceChildren(faIcon('fa-circle-check'));

    const text = document.createElement('span');
    text.className = 'file-name-text';
    text.title = name;
    text.textContent = name;
    el.append(text);

    if (sizeLabel) {
        const size = document.createElement('span');
        size.className = 'file-name-size';
        size.textContent = sizeLabel;
        el.append(size);
    }
};

const checkedFileLabel = (name, sizeLabel) => {
    const fragment = document.createDocumentFragment();
    fragment.append(faIcon('fa-circle-check'), document.createTextNode(` ${name} (${sizeLabel} MB)`));
    return fragment;
};

const appendStatus = (element, iconClass, message) => {
    element.replaceChildren(faIcon(iconClass));

    if (message instanceof Node) {
        element.append(document.createTextNode(' '), message);
        return;
    }

    element.append(document.createTextNode(` ${message ?? ''}`));
};

const strongText = text => {
    const node = document.createElement('b');
    node.textContent = text ?? '';
    return node;
};

const statusSentence = (lead, emphasis, tail) => {
    const fragment = document.createDocumentFragment();
    fragment.append(document.createTextNode(lead));
    fragment.append(strongText(emphasis));
    if (tail) fragment.append(document.createTextNode(tail));
    return fragment;
};

const countMarkup = (label, full) => {
    const span = document.createElement('span');
    span.className = 'dt-count';
    span.title = full;
    span.textContent = label;
    return markup(span);
};

const emphasizedSentence = (before, emphasis, after) => {
    const fragment = document.createDocumentFragment();
    fragment.append(document.createTextNode(before));
    const strong = document.createElement('strong');
    strong.textContent = emphasis;
    fragment.append(strong, document.createTextNode(after));
    return markup(fragment);
};

const processingText = 'Memuat data…';

const paginateLabels = {
    first: 'Pertama',
    last: 'Terakhir',
    next: 'Berikutnya',
    previous: 'Sebelumnya'
};

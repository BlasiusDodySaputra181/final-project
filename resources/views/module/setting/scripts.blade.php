@php
    $routeName = request()->route()?->getName() ?? '';
    $needsImportPdf = $routeName === 'page-import';
    $needsCharts = $routeName === 'page-dashboard';
    $needsBarcodeLibs = $routeName === 'page-barcode';
    $needsSettings = $routeName === 'page-setting';
    $needsTable = ! in_array($routeName, ['page-dashboard', 'page-profile', 'page-setting'], true);
    $needsDatepicker = in_array($routeName, [
        'page-finalproject',
        'page-import',
        'page-bookgrant',
        'page-libraryclearance',
        'page-submission',
        'page-membership',
    ], true);
    $pageForms = match ($routeName) {
        'page-finalproject' => ['admin.finalproject.form'],
        'page-studyprogram' => ['admin.studyprogram.form'],
        'page-membership' => ['admin.membership.form'],
        'page-finalprojecttype' => ['admin.finalprojecttype.form'],
        'page-visitor' => ['admin.visitor.form'],
        'page-bookgrant' => ['admin.bookgrant.form'],
        'page-libraryclearance' => ['admin.libraryclearance.form'],
        'page-barcode' => ['admin.barcode.form'],
        'page-destruction' => auth()->user()?->isOfficer() ? ['admin.destruction.form'] : [],
        'page-archive' => ['admin.archive.form', 'admin.archive.download'],
        'page-report' => ['admin.report.form', 'admin.report.download'],
        'page-submission' => ['admin.submission.form'],
        'page-user' => ['admin.user.form'],
        'page-import' => ['admin.import.detail', 'admin.import.folder', 'admin.import.form'],
        default => [],
    };
    $needsCrud = $pageForms !== [] || $routeName === 'page-profile';
@endphp

@foreach ($pageForms as $pageForm)
    @include($pageForm)
@endforeach

@if ($needsBarcodeLibs)
@include('module.scanner.scanners')
@include('module.scanner.devicescanners')
@endif
@include('module.confirm.success')
@include('module.confirm.alerts')
@include('module.confirm.errors')
@include('module.confirm.progress')

@if ($needsBarcodeLibs)
<script src="{{ asset_version('js/vendor/html5-qrcode.min.js') }}" defer></script>
<script src="{{ asset_version('js/barcode-camera.js') }}" defer></script>
<script src="{{ asset_version('js/vendor/JsBarcode.all.min.js') }}" defer></script>
@endif
<script src="{{ asset_version('js/vendor/jquery-3.7.1.min.js') }}" defer></script>
<script src="{{ asset_version('js/vendor/popper.min.js') }}" defer></script>
@if ($needsCharts)
<script src="{{ asset_version('js/vendor/chart.umd.min.js') }}" defer></script>
@endif
@if ($needsTable)
<script src="{{ asset_version('js/vendor/jquery.dataTables.min.js') }}" defer></script>
@endif
@if ($needsImportPdf)
<meta name="pdf-worker" content="{{ asset_version('js/vendor/pdf.worker.min.js') }}">
<script src="{{ asset_version('js/vendor/pdf.min.js') }}" defer></script>
@endif
@if ($needsCrud)
<script src="{{ asset_version('js/vendor/jquery.validate.min.js') }}" defer></script>
@endif
@if ($needsDatepicker)
<script src="{{ asset_version('js/vendor/air-datepicker.min.js') }}" defer></script>
@endif
<script src="{{ asset_version('js/vendor/driver.js.iife.js') }}" defer></script>

<script src="{{ asset_version('js/offline-database.js') }}" defer></script>
<script src="{{ asset_version('js/pwa-register.js') }}" defer></script>
<script src="{{ asset_version('js/auth-loader.js') }}" defer></script>
<script src="{{ asset_version('js/dom-elements.js') }}" defer></script>
<script src="{{ asset_version('js/print-preview.js') }}" defer></script>
<script src="{{ asset_version('js/format-count.js') }}" defer></script>
<script src="{{ asset_version('js/global-setup.js') }}" defer></script>
<script src="{{ asset_version('js/offline-sync.js') }}" defer></script>
@if ($needsImportPdf)
<script src="{{ asset_version('js/component-imports.js') }}" defer></script>
@endif
@if ($needsTable)
<script src="{{ asset_version('js/component-table.js') }}" defer></script>
@endif
@if ($needsDatepicker)
<script src="{{ asset_version('js/component-datepicker.js') }}" defer></script>
@endif
<script src="{{ asset_version('js/component-dropdown-select.js') }}" defer></script>
<script src="{{ asset_version('js/component-modals-alerts.js') }}" defer></script>
@if ($needsCrud)
<script src="{{ asset_version('js/form-validation-crud.js') }}" defer></script>
@endif
@if ($needsBarcodeLibs)
<script src="{{ asset_version('js/component-scanner.js') }}" defer></script>
<script src="{{ asset_version('js/component-device-scanner.js') }}" defer></script>
@endif
@if ($needsCharts)
<script src="{{ asset_version('js/component-charts.js') }}" defer></script>
<script src="{{ asset_version('js/component-access-qr.js') }}" defer></script>
@endif
<script src="{{ asset_version('js/component-button.js') }}" defer></script>
<script src="{{ asset_version('js/component-help-tour.js') }}" defer></script>
@if ($needsSettings)
<script src="{{ asset_version('js/component-settings.js') }}" defer></script>
@endif

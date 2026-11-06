<link rel="preload" href="{{ asset_version('images/logo.png') }}" as="image" type="image/png">

<!-- PWA -->
<meta name="theme-color" content="#7A1CAC">
<link rel="manifest" href="{{ asset_version('manifest.json') }}">
<link rel="icon" type="image/png" href="{{ asset('images/icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192.png') }}">

<link rel="stylesheet" href="{{ asset_version('css/vendor/poppins.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/vendor/fontawesome.min.css') }}">
@if (in_array(request()->route()?->getName(), ['page-finalproject', 'page-import', 'page-bookgrant', 'page-libraryclearance', 'page-submission', 'page-membership'], true))
<link rel="stylesheet" href="{{ asset_version('css/vendor/air-datepicker.min.css') }}">
@endif
<link rel="stylesheet" href="{{ asset_version('css/vendor/driver.css') }}">

<link rel="stylesheet" href="{{ asset_version('css/variables.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/layout.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/components.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/tables.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/forms.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/overlays.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/responsive.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/progress.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/help-tour.css') }}">

@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Penyerahan Tugas Akhir')
@section('body-class', 'auth-submission')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/vendor/air-datepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/student-submission.css') }}">
@endpush

@section('standalone')
<div class="page">
    <div class="container">
        <div class="portal-card">

            @include('student.partials.submission-header')
            @include('student.partials.submission-hero')

            @include('student.partials.submission-alert', [
                'type' => 'success',
                'title' => 'Penyerahan Berhasil',
                'message' => session('success'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Penyerahan Gagal',
                'message' => session('error'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Data Belum Lengkap',
                'messages' => $errors->all(),
            ])

            @include('student.partials.submission-stepper')

            @php
                $submissionInitialStep = 0;
                if ($errors->hasAny(['email', 'phone_number'])) {
                    $submissionInitialStep = 2;
                } elseif (collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'books'))) {
                    $submissionInitialStep = 1;
                }
            @endphp
            <form
                id="submissionpublicform"
                method="POST"
                action="{{ route('store-public-submission') }}"
                enctype="multipart/form-data"
                novalidate
                data-initial-step="{{ $submissionInitialStep }}"
            >
                @csrf

                <div class="slider-wrapper">
                    <div class="slider-track">
                        @include('student.partials.submission-step-finalproject')
                        @include('student.partials.submission-step-bookgrant')
                        @include('student.partials.submission-step-libraryclearance')
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset_version('js/vendor/air-datepicker.min.js') }}" defer></script>
<script src="{{ asset_version('js/student-submission.js') }}" defer></script>
@endpush

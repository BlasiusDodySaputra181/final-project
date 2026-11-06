<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BookGrantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DestructionController;
use App\Http\Controllers\FinalProjectController;
use App\Http\Controllers\FinalProjectTypeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LibraryClearanceController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudyProgramController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guests sign in at /.
| An active role session is kept so another role can sign in
| at the same time in one browser (officer, coordinator, student).
|--------------------------------------------------------------------------
*/
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.submit');

Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
Route::view('/cetak/pratinjau', 'module.print.preview')->name('print.preview');
Route::view('/luring', 'module.offline.unavailable')->name('offline.unavailable');
Route::post('/daftar', [AuthController::class, 'register'])
    ->middleware('throttle:5,1')
    ->name('register.submit');

Route::middleware('auth:web,coordinator,student')->group(function () {
    Route::post('/sesi/aktif', [AuthController::class, 'activate'])->name('session.activate');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Students: visitor log, search, barcode scan, membership, submission, proof, account.
| Progressive menu:
| - not a member and not submitted: Visitor, Search, Scan, Membership
| - /anggota before registration: Visitor, Search, Scan, Membership, Proof
| - /serah before submission: Visitor, Search, Scan, Submission, Proof
| - after membership OR submission (other pages): Visitor, Search, Scan, Proof
| The account page (/akun) is opened from the navbar, outside the progressive menu.
| Guard: student (parallel with officer and coordinator sessions)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:student', 'student'])->group(function () {
    Route::get('akun', [ProfileController::class, 'publicPage'])->name('page-public-profile');
    Route::post('akun', [ProfileController::class, 'publicStore'])->name('store-public-profile');

    Route::controller(VisitorController::class)->group(function () {
        Route::get('kunjungan', 'publicForm')->name('page-public-visitor');
        Route::post('kunjungan', 'publicStore')->name('store-public-visitor');
    });

    Route::controller(SearchController::class)->group(function () {
        Route::get('cari', 'publicSearch')->name('page-public-search');
        Route::get('cari/index', 'publicSearchIndex')->name('index-public-search');
    });

    Route::controller(BarcodeController::class)->group(function () {
        Route::get('pindai', 'publicScan')->name('page-public-barcode');
        Route::post('pindai/lookup', 'publicLookup')->name('lookup-public-barcode');
        Route::get('pindai/metadata/{barcode}/persetujuan', 'publicApprovalSheet')
            ->name('download-public-approvalsheet');
        Route::get('pindai/metadata/{barcode}', 'publicMetadata')
            ->name('page-public-barcode-metadata');
    });

    Route::controller(SubmissionController::class)->group(function () {
        Route::get('serah', 'publicForm')->name('page-public-submission');
        Route::post('serah', 'publicStore')->name('store-public-submission');
        Route::get('bukti', 'publicProof')->name('page-public-proof');
        Route::get('bukti/index', 'publicProofIndex')->name('index-public-proof');
        Route::get('bukti/bebaspustaka/{id}/surat', 'publicLibraryClearanceLetter')
            ->whereNumber('id')->name('letter-public-libraryclearance');
        Route::get('bukti/hibahbuku/{id}/surat', 'publicBookGrantLetter')
            ->whereNumber('id')->name('letter-public-bookgrant');
        Route::get('bukti/{id}', 'publicProofDetail')
            ->whereNumber('id')->name('page-public-proof-detail');
    });

    Route::controller(MembershipController::class)->group(function () {
        Route::get('anggota', 'publicForm')->name('page-public-membership');
        Route::post('anggota', 'publicStore')->name('store-public-membership');
        Route::get('bukti/keanggotaan/{id}/surat', 'publicLetter')
            ->whereNumber('id')->name('letter-public-membership');
    });
});

/*
|--------------------------------------------------------------------------
| Officer and coordinator — protected dashboard.
| Guards: web (officer) and coordinator, parallel with the student session.
|--------------------------------------------------------------------------
*/
Route::middleware(['admin.session', 'admin'])->group(function () {
    Route::get('profil', [ProfileController::class, 'page'])->name('page-profile');
    Route::post('storeprofile', [ProfileController::class, 'store'])->name('store-profile');

    Route::get('dasbor', [DashboardController::class, 'page'])->name('page-dashboard');
    Route::get('dasbor/qrcode/pdf', [DashboardController::class, 'accessQrPdf'])->name('pdf-access-qrcode');

    /* Read (GET) — officer and coordinator */

    Route::controller(ImportController::class)->group(function () {
        Route::get('impor', 'page')->name('page-import');
    });

    Route::controller(FinalProjectController::class)->group(function () {
        Route::get('tugas-akhir/{type}', 'page')->name('page-finalproject');
        Route::redirect('tesis', '/tugas-akhir/tesis');

        Route::post('showfilesize', 'showFileSize')->name('show-filesize');
        Route::get('finalproject/index', 'index')->name('index-finalproject');
        Route::get('finalproject/options', 'options')->name('options-finalproject');

        Route::get('finalproject/download/{id}', 'downloadFinalProject')
            ->whereNumber('id')->name('download-finalproject');

        Route::get('approvalsheet/download/{id}', 'downloadApprovalSheet')
            ->whereNumber('id')->name('download-approvalsheet');
    });

    Route::controller(StudyProgramController::class)->group(function () {
        Route::get('prodi', 'page')->name('page-studyprogram');
        Route::get('studyprogram/index', 'index')->name('index-studyprogram');
        Route::get('studyprogram/options', 'options')->name('options-studyprogram');
    });

    Route::controller(MembershipController::class)->group(function () {
        Route::get('keanggotaan', 'page')->name('page-membership');
        Route::get('membership/index', 'index')->name('index-membership');
        Route::get('membership/surat/{id}', 'letter')
            ->whereNumber('id')->name('letter-membership');
    });

    Route::controller(FinalProjectTypeController::class)->group(function () {
        Route::get('jenista', 'page')->name('page-finalprojecttype');
        Route::get('finalprojecttype/index', 'index')->name('index-finalprojecttype');
        Route::get('finalprojecttype/options', 'options')->name('options-finalprojecttype');
    });

    Route::controller(VisitorController::class)->group(function () {
        Route::get('pengunjung', 'page')->name('page-visitor');
        Route::get('visitor/index', 'index')->name('index-visitor');
        Route::get('visitor/access', 'accessStatus')->name('status-visitor-access');
    });

    Route::controller(BookGrantController::class)->group(function () {
        Route::get('hibahbuku', 'page')->name('page-bookgrant');
        Route::get('bookgrant/index', 'index')->name('index-bookgrant');
        Route::get('bookgrant/surat/{id}', 'letter')
            ->whereNumber('id')->name('letter-bookgrant');
    });

    Route::controller(LibraryClearanceController::class)->group(function () {
        Route::get('bebaspustaka', 'page')->name('page-libraryclearance');
        Route::get('libraryclearance/index', 'index')->name('index-libraryclearance');
        Route::get('libraryclearance/surat/{id}', 'letter')
            ->whereNumber('id')->name('letter-libraryclearance');
    });

    Route::controller(BarcodeController::class)->group(function () {
        Route::get('kodebatang', 'page')->name('page-barcode');
        Route::get('barcode/index', 'index')->name('index-barcode');
        Route::get('barcode/pdf', 'pdf')->name('pdf-barcode');
        Route::get('barcode/access', 'accessStatus')->name('status-barcode-access');
    });

    Route::controller(DestructionController::class)->group(function () {
        Route::get('pemusnahan', 'page')->name('page-destruction');
        Route::get('destruction/index', 'index')->name('index-destruction');
        Route::post('destruction/approve', 'approve')->name('approve-destruction');
    });

    Route::controller(ArchiveController::class)->group(function () {
        Route::get('arsip', 'page')->name('page-archive');
        Route::get('archive/index', 'index')->name('index-archive');
    });

    Route::controller(ReportController::class)->group(function () {
        Route::get('laporan', 'page')->name('page-report');
        Route::get('report/index', 'index')->name('index-report');
    });

    Route::controller(SubmissionController::class)->group(function () {
        Route::get('penyerahan', 'page')->name('page-submission');
        Route::get('submission/index', 'index')->name('index-submission');
        Route::get('submission/access', 'accessStatus')->name('status-submission-access');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('pengguna', 'page')->name('page-user');
        Route::get('user/index', 'index')->name('index-user');
        Route::get('user/options', 'options')->name('options-user');
    });

    /* Mutations — officer only */

    Route::middleware('officer')->group(function () {
        Route::get('pengaturan', [SettingController::class, 'page'])->name('page-setting');
        Route::post('storesetting', [SettingController::class, 'store'])->name('store-setting');
        Route::get('setting/storage-move-progress', [SettingController::class, 'moveProgress'])
            ->name('progress-setting-storage');
        Route::get('setting/storage-folders', [SettingController::class, 'browse'])
            ->name('browse-setting-storage');

        Route::controller(ImportController::class)->group(function () {
            Route::post('storeimport', 'store')->name('store-import');
            Route::post('reader/import', 'reader')->name('reader-import');
        });

        Route::controller(FinalProjectController::class)->group(function () {
            Route::post('storefinalproject', 'store')->name('store-finalproject');

            Route::delete('finalproject/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-finalproject');
        });

        Route::controller(StudyProgramController::class)->group(function () {
            Route::post('storestudyprogram', 'store')->name('store-studyprogram');

            Route::delete('studyprogram/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-studyprogram');
        });

        Route::controller(MembershipController::class)->group(function () {
            Route::post('storemembership', 'store')->name('store-membership');

            Route::delete('membership/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-membership');
        });

        Route::controller(FinalProjectTypeController::class)->group(function () {
            Route::post('storefinalprojecttype', 'store')->name('store-finalprojecttype');

            Route::delete('finalprojecttype/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-finalprojecttype');
        });

        Route::controller(VisitorController::class)->group(function () {
            Route::post('storevisitor', 'store')->name('store-visitor');
            Route::post('visitor/access', 'toggleAccess')->name('toggle-visitor-access');
            Route::post('visitor/supervise', 'supervise')->name('supervise-visitor');

            Route::delete('visitor/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-visitor');
        });

        Route::controller(BookGrantController::class)->group(function () {
            Route::post('storebookgrant', 'store')->name('store-bookgrant');

            Route::delete('bookgrant/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-bookgrant');
        });

        Route::controller(LibraryClearanceController::class)->group(function () {
            Route::post('storelibraryclearance', 'store')->name('store-libraryclearance');

            Route::delete('libraryclearance/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-libraryclearance');
        });

        Route::controller(BarcodeController::class)->group(function () {
            Route::post('storebarcode', 'store')->name('store-barcode');
            Route::post('barcode/access', 'toggleAccess')->name('toggle-barcode-access');

            Route::delete('barcode/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-barcode');
        });

        Route::controller(DestructionController::class)->group(function () {
            Route::post('storedestruction', 'store')->name('store-destruction');

            Route::delete('destruction/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-destruction');
        });

        Route::controller(ArchiveController::class)->group(function () {
            Route::post('storearchive', 'store')->name('store-archive');

            Route::delete('archive/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-archive');
        });

        Route::controller(ReportController::class)->group(function () {
            Route::post('storereport', 'store')->name('store-report');

            Route::delete('report/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-report');
        });

        Route::controller(SubmissionController::class)->group(function () {
            Route::post('storesubmission', 'store')->name('store-submission');
            Route::post('submission/access', 'toggleAccess')->name('toggle-submission-access');

            Route::delete('submission/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-submission');
        });

        Route::controller(UserController::class)->group(function () {
            Route::post('storeuser', 'store')->name('store-user');
            Route::post('user/submission-access', 'toggleSubmissionAccess')
                ->name('toggle-user-submission-access');
            Route::post('user/membership-access', 'toggleMembershipAccess')
                ->name('toggle-user-membership-access');

            Route::delete('user/destroy/{id}', 'destroy')
                ->whereNumber('id')->name('delete-user');
        });
    });
});

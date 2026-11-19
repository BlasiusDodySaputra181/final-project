<?php

namespace App\View;

use App\Models\FinalProject;
use App\Models\Membership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;

/**
 * Values previously calculated inside Blade templates.
 * Templates stay as markup; this class owns the PHP.
 */
final class BladeState
{
    public static function register(): void
    {
        View::composer('auth.outline', self::class.'@authOutline');
        View::composer('auth.login', self::class.'@login');
        View::composer('auth.register', self::class.'@registerForm');
        View::composer('auth.partials.role-sessions', self::class.'@loginRoleSessions');
        View::composer('admin.partials.role-sessions', self::class.'@adminRoleSessions');
        View::composer('admin.partials.degree-level-options', self::class.'@degreeLevelOptions');
        View::composer('admin.profile.index', self::class.'@profile');
        View::composer('module.outline.apps', self::class.'@adminShell');
        View::composer('module.setting.scripts', self::class.'@adminScripts');
        View::composer('student.access', self::class.'@accessClosed');
        View::composer('student.membership', self::class.'@membershipForm');
        View::composer('student.submission', self::class.'@submissionForm');
        View::composer('student.partials.session-bar', self::class.'@studentSession');
        View::composer('student.partials.submission-alert', self::class.'@submissionAlert');
        View::composer('student.partials.submission-summary', self::class.'@submissionSummary');
        View::composer('student.partials.submission-step-bookgrant', self::class.'@bookGrantStep');
        View::composer('student.partials.submission-step-finalproject', self::class.'@finalProjectStep');
    }

    public function authOutline(ViewInstance $view): void
    {
        $view->with('isAuthGate', request()->routeIs('login', 'register'));
    }

    public function login(ViewInstance $view): void
    {
        $sessions = $view->getData()['roleSessions'] ?? [];
        $errors = $view->getData()['errors'] ?? null;
        $authError = null;

        if ($errors) {
            $authError = collect($errors->all())->first(
                fn ($message) => str_contains((string) $message, 'tidak sesuai')
                    || str_contains((string) $message, 'Akses hanya')
                    || str_contains((string) $message, 'Pendaftaran publik')
                    || str_contains((string) $message, 'Sesi peran')
                    || str_contains((string) $message, 'beberapa akun')
            );
        }

        $view->with([
            'roleSessions' => $sessions,
            'allRolesActive' => count($sessions) >= 3,
            'authError' => $authError,
        ]);
    }

    public function registerForm(ViewInstance $view): void
    {
        $errors = $view->getData()['errors'] ?? null;
        $bannerError = null;

        if ($errors) {
            $bannerError = collect($errors->getMessages())
                ->reject(fn ($messages, $key) => in_array($key, ['useridentitynumber', 'username', 'useremail', 'userpassword'], true))
                ->flatten()
                ->first();
        }

        $view->with('bannerError', $bannerError);
    }

    public function loginRoleSessions(ViewInstance $view): void
    {
        $view->with('roleSessions', $view->getData()['roleSessions'] ?? role_sessions());
    }

    public function adminRoleSessions(ViewInstance $view): void
    {
        $roleSessions = role_sessions();

        $view->with([
            'roleSessions' => $roleSessions,
            'currentGuard' => auth()->getDefaultDriver(),
            'showRoleSwitcher' => count($roleSessions) > 1
                || collect($roleSessions)->contains(fn ($session) => $session['guard'] === 'student'),
        ]);
    }

    public function degreeLevelOptions(ViewInstance $view): void
    {
        $view->with(
            'degreeLevelOptions',
            collect($view->getData()['finalProjectTypes'] ?? [])
                ->filter(fn ($type) => trim((string) ($type->level ?? '')) !== '')
                ->values()
        );
    }

    public function profile(ViewInstance $view): void
    {
        $view->with('profileUser', $view->getData()['user'] ?? auth()->user());
    }

    public function adminShell(ViewInstance $view): void
    {
        $view->with('authUser', auth()->user());
    }

    public function adminScripts(ViewInstance $view): void
    {
        $routeName = request()->route()?->getName() ?? '';
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

        $view->with([
            'needsImportPdf' => $routeName === 'page-import',
            'needsCharts' => $routeName === 'page-dashboard',
            'needsBarcodeLibs' => $routeName === 'page-barcode',
            'needsSettings' => $routeName === 'page-setting',
            'needsLocalServer' => in_array($routeName, ['page-local-server', 'page-local-database'], true),
            'needsTable' => ! in_array($routeName, ['page-dashboard', 'page-profile', 'page-setting', 'page-local-server', 'page-local-database'], true),
            'needsDatepicker' => in_array($routeName, [
                'page-finalproject',
                'page-import',
                'page-bookgrant',
                'page-libraryclearance',
                'page-submission',
                'page-membership',
            ], true),
            'pageForms' => $pageForms,
            'needsCrud' => $pageForms !== [] || $routeName === 'page-profile',
        ]);
    }

    public function accessClosed(ViewInstance $view): void
    {
        $view->with('errorText', session('error') ?? ($view->getData()['errorMessage'] ?? null));
    }

    public function membershipForm(ViewInstance $view): void
    {
        $selectedProgram = collect($view->getData()['studyPrograms'] ?? [])
            ->firstWhere('id', (int) old('study_program_id'));

        $view->with([
            'selectedProgramLabel' => $selectedProgram ? study_program_label($selectedProgram) : null,
            'selectedGender' => old('gender'),
        ]);
    }

    public function submissionForm(ViewInstance $view): void
    {
        $errors = $view->getData()['errors'] ?? null;
        $step = 0;

        if ($errors && $errors->hasAny(['email', 'phone_number'])) {
            $step = 2;
        } elseif ($errors && collect($errors->keys())->contains(fn ($key) => str_starts_with((string) $key, 'books'))) {
            $step = 1;
        }

        $view->with('submissionInitialStep', $step);
    }

    public function studentSession(ViewInstance $view): void
    {
        $studentUser = Auth::guard('student')->user();

        if ($studentUser === null) {
            return;
        }

        $studentName = (string) $studentUser->name;
        $studentHasProof = FinalProject::existsForNim($studentUser->identity_number);
        $studentHasMembership = Membership::existsForUser($studentUser->id);
        $onAnggotaPage = request()->is('anggota');
        $onSerahPage = request()->is('serah');
        $showAnggotaMenu = false;
        $showSerahMenu = false;
        $showBuktiMenu = false;

        if ($onAnggotaPage && ! $studentHasMembership) {
            $showAnggotaMenu = true;
            $showBuktiMenu = true;
        } elseif ($onSerahPage && ! $studentHasProof) {
            $showSerahMenu = true;
            $showBuktiMenu = true;
        } elseif (! $studentHasMembership && ! $studentHasProof) {
            $showAnggotaMenu = true;
        } else {
            $showBuktiMenu = true;
        }

        $view->with([
            'studentUser' => $studentUser,
            'studentName' => $studentName,
            'studentInitials' => collect(preg_split('/\s+/', trim($studentName)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr((string) $part, 0, 1)))
                ->implode(''),
            'showAnggotaMenu' => $showAnggotaMenu,
            'showSerahMenu' => $showSerahMenu,
            'showBuktiMenu' => $showBuktiMenu,
            'studentNavExtended' => ($showAnggotaMenu && $showBuktiMenu) || ($showSerahMenu && $showBuktiMenu),
            'otherSessions' => collect(role_sessions())
                ->reject(fn ($session) => $session['guard'] === 'student'),
        ]);
    }

    public function submissionAlert(ViewInstance $view): void
    {
        $data = $view->getData();
        $isSuccess = ($data['type'] ?? 'error') === 'success';
        $messages = $data['messages'] ?? [];

        if (! is_array($messages)) {
            $messages = collect($messages)->all();
        }

        if (filled($data['message'] ?? null)) {
            array_unshift($messages, $data['message']);
        }

        $view->with([
            'isSuccess' => $isSuccess,
            'title' => $data['title'] ?? ($isSuccess ? 'Berhasil' : 'Gagal'),
            'messages' => array_values(array_filter($messages, fn ($item) => filled($item))),
        ]);
    }

    public function submissionSummary(ViewInstance $view): void
    {
        $data = $view->getData();

        $view->with([
            'summaryTitle' => $data['title'] ?? 'Ringkasan Data',
            'infoText' => $data['info'] ?? 'Pastikan data yang diisi lengkap dan benar sebelum melanjutkan ke tahap berikutnya.',
            'summaryItems' => $data['items'] ?? [
                ['label' => 'Tahap', 'value' => $data['stage'] ?? '1 / 3'],
                ['label' => 'Kemajuan', 'value' => $data['progress'] ?? '33%'],
                ['label' => 'Status', 'value' => $data['status'] ?? 'Draf'],
            ],
        ]);
    }

    public function bookGrantStep(ViewInstance $view): void
    {
        $oldBooks = old('books', [['author' => '', 'book_year' => '', 'book_title' => '']]);

        if ($oldBooks === [] || ! is_array($oldBooks)) {
            $oldBooks = [['author' => '', 'book_year' => '', 'book_title' => '']];
        }

        $view->with([
            'oldBooks' => $oldBooks,
            'withoutBooks' => (string) old('without_books', '0') === '1',
        ]);
    }

    public function finalProjectStep(ViewInstance $view): void
    {
        $data = $view->getData();
        $programs = collect($data['studyPrograms'] ?? []);
        $types = collect($data['finalProjectTypes'] ?? []);
        $selectedProgram = $programs->firstWhere('study_program', old('program_study'));
        $selectedType = $types->firstWhere('id', (int) old('final_project_type_id'))
            ?? $types->firstWhere('level', old('degree_level'));

        $view->with([
            'selectedProgramLabel' => $selectedProgram ? study_program_label($selectedProgram) : null,
            'selectedTypeLabel' => $selectedType ? degree_type_label($selectedType) : null,
        ]);
    }
}

<?php

namespace App\Setup;

use App\Models\FinalProject;
use App\Models\StudyProgram;
use App\Models\User;
use App\Models\Visitor;
use App\Support\CatalogCache;
use App\Support\RoleSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Masuk, daftar bootstrap, dan ganti sesi peran.
 * AuthController hanya memanggil kelas ini.
 */
class SignIn
{
    /**
     * Halaman login. Sesi peran yang sudah aktif tidak dihapus
     * agar peran lain bisa masuk di browser yang sama.
     */
    public function showLogin(): View
    {
        return view('auth.login', $this->authHeroStats());
    }

    /**
     * Halaman daftar akun — hanya untuk bootstrap (belum ada petugas/koordinator).
     */
    public function showRegister(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfAnyAuthenticated()) {
            return $redirect;
        }

        if ($this->adminExists()) {
            return redirect()
                ->route('login')
                ->withErrors(['name' => 'Pendaftaran publik ditutup. Hubungi Petugas untuk membuat akun.']);
        }

        return view('auth.register', $this->authHeroStats());
    }

    /**
     * Simpan akun bootstrap pertama (selalu Petugas). Akun berikutnya lewat menu Pengguna.
     */
    public function register(Request $request): RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()
                ->route('login')
                ->withErrors(['name' => 'Pendaftaran publik ditutup. Hubungi Petugas untuk membuat akun.']);
        }

        $validated = $request->validate([
            'useridentitynumber' => ['required', 'string', 'max:50', 'regex:/^[0-9\s.\/-]+$/'],
            'username' => ['required', 'string', 'min:3', 'max:255', 'unique:users,name'],
            'useremail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'userpassword' => ['required', 'string', 'min:8'],
        ], [
            'useridentitynumber.required' => 'Nomor identitas harus diisi!',
            'useridentitynumber.max' => 'Nomor identitas maksimal 50 karakter!',
            'useridentitynumber.regex' => 'Nomor identitas hanya boleh berisi angka, spasi, titik, garis miring, atau tanda hubung!',
            'username.required' => 'Nama harus diisi!',
            'username.min' => 'Nama minimal 3 karakter!',
            'username.max' => 'Nama maksimal 255 karakter!',
            'username.unique' => 'Nama sudah terdaftar!',
            'useremail.required' => 'Email harus diisi!',
            'useremail.email' => 'Format email tidak valid!',
            'useremail.unique' => 'Email sudah terdaftar!',
            'userpassword.required' => 'Kata sandi wajib diisi.',
            'userpassword.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        // Bootstrap publik selalu Petugas; akun Koordinator/Mahasiswa lewat menu Pengguna.
        User::create([
            'identity_number' => $validated['useridentitynumber'],
            'name' => $validated['username'],
            'email' => $validated['useremail'],
            'password' => $validated['userpassword'],
            'role' => User::ROLE_OFFICER,
        ]);

        return redirect()
            ->route('login')
            ->with('success', 'Akun berhasil dibuat. Silakan masuk dengan nama dan kata sandi Anda.');
    }

    /**
     * Autentikasi name + password ke slot guard sesuai peran.
     * Guard peran lain di browser yang sama tetap aktif.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama harus diisi!',
            'name.max' => 'Nama maksimal 255 karakter!',
            'password.required' => 'Kata sandi harus diisi!',
        ]);

        $login = trim($credentials['name']);
        $remember = $request->boolean('remember');

        $user = $this->authenticateByIdentifier($login, $credentials['password']);

        if (! $user->isAdmin() && ! $user->isStudent()) {
            throw ValidationException::withMessages([
                'name' => 'Akses hanya untuk Petugas, Koordinator, atau Mahasiswa.',
            ]);
        }

        // Rehash otomatis ke driver terbaru (Argon2id) jika algoritma lama.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $credentials['password']])->save();
        }

        $guard = RoleSession::guardFor($user);

        Auth::guard($guard)->login($user, $remember);
        RoleSession::activate($guard);
        $request->session()->regenerate();

        return RoleSession::homeRedirect($user);
    }

    /**
     * Pindah ke beranda peran yang sudah aktif di peramban ini.
     */
    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guard' => ['required', Rule::in(RoleSession::all())],
        ]);

        $guard = $validated['guard'];

        if (! Auth::guard($guard)->check()) {
            return redirect()
                ->route('login')
                ->withErrors(['name' => 'Sesi peran itu belum aktif. Silakan masuk terlebih dahulu.']);
        }

        RoleSession::activate($guard);

        return redirect()->route(RoleSession::homeRoute($guard));
    }

    /**
     * Keluar dari satu peran, atau semua jika guard=all / tidak valid.
     */
    public function logout(Request $request): RedirectResponse
    {
        $guard = (string) $request->input('guard', 'all');

        if ($guard !== 'all' && in_array($guard, RoleSession::all(), true) && Auth::guard($guard)->check()) {
            RoleSession::logoutGuard($request, $guard);

            return RoleSession::redirectAfterLogout();
        }

        RoleSession::logoutAll($request);

        return redirect()->route('login');
    }

    /**
     * Statistik hero auth: dokumen arsip, program studi, pengunjung harian.
     *
     * @return array{
     *     archiveCountLabel: string,
     *     studyProgramCount: string,
     *     dailyVisitorCountLabel: string,
     *     canRegister: bool,
     *     roleSessions: list<array{guard: string, role: string, user: User, home: string, current: bool}>
     * }
     */
    private function authHeroStats(): array
    {
        $stats = Cache::remember(CatalogCache::AUTH_HERO_STATS, 60, static function (): array {
            return [
                'archiveCountLabel' => format_count_label(self::safeTableCount(FinalProject::class)),
                'studyProgramCount' => format_count_label(self::safeTableCount(StudyProgram::class)),
                'dailyVisitorCountLabel' => format_count_label(self::safeDailyVisitorCount()),
            ];
        });

        return [
            ...$stats,
            'canRegister' => ! $this->adminExists(),
            'roleSessions' => RoleSession::snapshot(),
        ];
    }

    /**
     * Hitung baris tabel tanpa menjatuhkan halaman login jika migrasi belum jalan.
     *
     * @param  class-string<Model>  $model
     */
    private static function safeTableCount(string $model): int
    {
        try {
            return (int) $model::query()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function safeDailyVisitorCount(): int
    {
        try {
            $start = now()->startOfDay();

            return (int) Visitor::query()
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $start->copy()->addDay())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Apakah sudah ada akun Petugas/Koordinator.
     */
    private function adminExists(): bool
    {
        $request = request();

        if ($request->attributes->has('auth.admin_exists')) {
            return (bool) $request->attributes->get('auth.admin_exists');
        }

        if (! app()->runningUnitTests() && Cache::get('auth.admin_exists') === true) {
            $request->attributes->set('auth.admin_exists', true);

            return true;
        }

        try {
            $exists = User::query()
                ->whereIn('role', [User::ROLE_OFFICER, User::ROLE_COORDINATOR])
                ->exists();
        } catch (\Throwable) {
            $request->attributes->set('auth.admin_exists', false);

            return false;
        }

        $request->attributes->set('auth.admin_exists', $exists);

        if ($exists && ! app()->runningUnitTests()) {
            Cache::put('auth.admin_exists', true, 3600);
        }

        return $exists;
    }

    /**
     * Cocokkan nama, email, atau nomor identitas + kata sandi.
     * Jika beberapa akun memakai nama yang sama, pilih yang katasandinya cocok.
     */
    private function authenticateByIdentifier(string $login, string $password): User
    {
        $digits = Account::identityDigits($login);

        $candidates = User::query()
            ->where(function ($query) use ($login, $digits) {
                $nameWithTitle = addcslashes($login, '%_\\').',%';

                $query->where('name', $login)
                    ->orWhere('name', 'like', $nameWithTitle)
                    ->orWhere('email', $login)
                    ->orWhere('identity_number', $login);

                if ($digits !== null) {
                    $query->orWhere('identity_number', $digits)
                        ->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(identity_number, ''), ' ', ''), '.', ''), '/', ''), '-', ''), ',', '') = ?",
                            [$digits]
                        );
                }
            })
            ->get();

        $matched = $candidates->filter(
            static fn (User $user): bool => Hash::check($password, $user->password)
        );

        if ($matched->isEmpty()) {
            throw ValidationException::withMessages([
                'name' => 'Nama, email, NIM/NIP, atau kata sandi tidak sesuai.',
            ]);
        }

        if ($matched->count() === 1) {
            return $matched->first();
        }

        $byEmail = $matched->first(
            static fn (User $user): bool => strcasecmp((string) $user->email, $login) === 0
        );

        if ($byEmail) {
            return $byEmail;
        }

        $byIdentity = $matched->first(
            function (User $user) use ($login, $digits): bool {
                $stored = (string) $user->identity_number;

                return $stored === $login
                    || ($digits !== null && Account::identityDigits($stored) === $digits);
            }
        );

        if ($byIdentity) {
            return $byIdentity;
        }

        throw ValidationException::withMessages([
            'name' => 'Ada beberapa akun dengan identitas ini. Masuk memakai email atau nomor identitas.',
        ]);
    }

    private function redirectIfAnyAuthenticated(): ?RedirectResponse
    {
        $sessions = RoleSession::authenticated();

        if ($sessions === []) {
            return null;
        }

        $first = reset($sessions);

        return $first instanceof User
            ? RoleSession::homeRedirect($first)
            : null;
    }
}

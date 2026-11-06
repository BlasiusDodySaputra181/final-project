<?php

namespace App\Setup;

use App\Models\User;
use App\Support\RoleSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batas akses peran untuk middleware.
 * Middleware hanya meneruskan request ke sini.
 */
class RoleGuard
{
    /**
     * Konteks dasbor yang aktif harus Petugas atau Koordinator.
     *
     * @param  Closure(Request): Response  $next
     */
    public function ensureAdmin(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json('Akses hanya untuk Petugas atau Koordinator.', 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['name' => 'Akses hanya untuk Petugas atau Koordinator.']);
        }

        return $next($request);
    }

    /**
     * Dasbor memakai sesi Petugas atau Koordinator yang sedang aktif.
     * Guard lain di peramban yang sama tetap masuk.
     *
     * @param  Closure(Request): Response  $next
     */
    public function resolveAdminSession(Request $request, Closure $next): Response
    {
        if (! RoleSession::applyAdminContext()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json('Akses hanya untuk Petugas atau Koordinator.', 401);
            }

            return redirect()->guest(route('login'))
                ->withErrors(['name' => 'Akses hanya untuk Petugas atau Koordinator.']);
        }

        return $next($request);
    }

    /**
     * Layanan mahasiswa memakai guard mahasiswa.
     * Sesi Petugas dan Koordinator di peramban yang sama tetap masuk.
     *
     * @param  Closure(Request): Response  $next
     */
    public function ensureStudent(Request $request, Closure $next): Response
    {
        $user = $request->user('student');

        if (! $user instanceof User || ! $user->isStudent()) {
            if ($request->user('web')?->isOfficer() || $request->user('coordinator')?->isCoordinator()) {
                return redirect()->route('page-dashboard');
            }

            return redirect()
                ->route('login')
                ->withErrors(['name' => 'Akses layanan mahasiswa memerlukan akun Mahasiswa. Hubungi petugas.']);
        }

        return $next($request);
    }

    /**
     * Perubahan data hanya saat sesi aktif milik Petugas.
     * Sesi Koordinator yang berjalan bersamaan tidak dipakai untuk menulis.
     *
     * @param  Closure(Request): Response  $next
     */
    public function ensureOfficer(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isOfficer()) {
            if ($request->expectsJson()) {
                return response()->json('Koordinator hanya dapat melihat dan mengunduh data.', 403);
            }

            abort(403, 'Koordinator hanya dapat melihat dan mengunduh data.');
        }

        return $next($request);
    }
}

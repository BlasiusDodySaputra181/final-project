<?php

namespace App\Setup;

use App\Models\User;
use App\Support\RoleSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role access checks for middleware.
 * Middleware only forwards the request here.
 */
class RoleGuard
{
    /**
     * The active dashboard context must be an officer or a coordinator.
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
     * The dashboard uses the active officer or coordinator session.
     * Other guards in the same browser stay signed in.
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
     * Student services use the student guard.
     * Officer and coordinator sessions in the same browser stay signed in.
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
     * Data changes are allowed only when the active session belongs to an officer.
     * A concurrent coordinator session is not used for writes.
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

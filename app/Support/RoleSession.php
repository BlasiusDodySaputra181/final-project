<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One browser cookie holds a separate authentication session per role.
 * Officer, coordinator, and student use different guards
 * so they can stay signed in together.
 */
final class RoleSession
{
    public const OFFICER = 'web';

    public const COORDINATOR = 'coordinator';

    public const STUDENT = 'student';

    public const ACTIVE_KEY = 'active_admin_guard';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::OFFICER, self::COORDINATOR, self::STUDENT];
    }

    /**
     * @return list<string>
     */
    public static function adminGuards(): array
    {
        return [self::OFFICER, self::COORDINATOR];
    }

    public static function guardFor(User $user): string
    {
        if ($user->isStudent()) {
            return self::STUDENT;
        }

        if ($user->isCoordinator()) {
            return self::COORDINATOR;
        }

        return self::OFFICER;
    }

    public static function roleLabel(string $guard): string
    {
        return match ($guard) {
            self::OFFICER => User::ROLE_OFFICER,
            self::COORDINATOR => User::ROLE_COORDINATOR,
            self::STUDENT => User::ROLE_STUDENT,
            default => $guard,
        };
    }

    public static function homeRoute(string $guard): string
    {
        return $guard === self::STUDENT
            ? 'page-public-visitor'
            : 'page-dashboard';
    }

    public static function user(string $guard): ?User
    {
        $user = Auth::guard($guard)->user();

        if (! $user instanceof User) {
            return null;
        }

        return match ($guard) {
            self::OFFICER => $user->isOfficer() ? $user : null,
            self::COORDINATOR => $user->isCoordinator() ? $user : null,
            self::STUDENT => $user->isStudent() ? $user : null,
            default => null,
        };
    }

    /**
     * @return array<string, User>
     */
    public static function authenticated(): array
    {
        $sessions = [];

        foreach (self::all() as $guard) {
            $user = self::user($guard);

            if ($user) {
                $sessions[$guard] = $user;
            }
        }

        return $sessions;
    }

    public static function anyAuthenticated(): bool
    {
        return self::authenticated() !== [];
    }

    /**
     * @return list<array{guard: string, role: string, user: User, home: string, current: bool}>
     */
    public static function snapshot(): array
    {
        $activeAdmin = (string) session(self::ACTIVE_KEY, '');
        $items = [];

        foreach (self::authenticated() as $guard => $user) {
            $items[] = [
                'guard' => $guard,
                'role' => self::roleLabel($guard),
                'user' => $user,
                'home' => route(self::homeRoute($guard)),
                'current' => $guard === $activeAdmin
                    || ($guard === self::STUDENT && request()->routeIs(
                        'page-public-visitor',
                        'page-public-search',
                        'page-public-barcode',
                        'page-public-barcode-metadata',
                        'download-public-approvalsheet',
                        'page-public-membership',
                        'page-public-submission',
                        'page-public-proof',
                        'page-public-proof-detail',
                        'page-public-profile',
                        'letter-public-libraryclearance',
                        'letter-public-bookgrant',
                    )),
            ];
        }

        return $items;
    }

    public static function activate(string $guard): void
    {
        if (! in_array($guard, self::adminGuards(), true)) {
            return;
        }

        session([self::ACTIVE_KEY => $guard]);
        Auth::shouldUse($guard);
    }

    /**
     * Choose the active admin guard (officer or coordinator).
     */
    public static function applyAdminContext(): bool
    {
        $officer = self::user(self::OFFICER);
        $coordinator = self::user(self::COORDINATOR);
        $hasOfficer = $officer?->isOfficer() === true;
        $hasCoordinator = $coordinator?->isCoordinator() === true;

        if (! $hasOfficer && ! $hasCoordinator) {
            return false;
        }

        $preferred = session(self::ACTIVE_KEY);

        if ($preferred === self::OFFICER && $hasOfficer) {
            Auth::shouldUse(self::OFFICER);
        } elseif ($preferred === self::COORDINATOR && $hasCoordinator) {
            Auth::shouldUse(self::COORDINATOR);
        } elseif ($hasOfficer) {
            Auth::shouldUse(self::OFFICER);
            session([self::ACTIVE_KEY => self::OFFICER]);
        } else {
            Auth::shouldUse(self::COORDINATOR);
            session([self::ACTIVE_KEY => self::COORDINATOR]);
        }

        return true;
    }

    public static function logoutGuard(Request $request, string $guard): void
    {
        if (Auth::guard($guard)->check()) {
            Auth::guard($guard)->logout();
        }

        if ($request->session()->get(self::ACTIVE_KEY) === $guard) {
            $request->session()->forget(self::ACTIVE_KEY);
        }

        if (self::authenticated() === []) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return;
        }

        $request->session()->regenerateToken();
        self::applyAdminContext();
    }

    public static function logoutAll(Request $request): void
    {
        foreach (self::all() as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->forget(self::ACTIVE_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public static function redirectAfterLogout(): RedirectResponse
    {
        if (self::applyAdminContext()) {
            return redirect()->route('page-dashboard');
        }

        $student = self::user(self::STUDENT);

        if ($student?->isStudent()) {
            return redirect()->route('page-public-visitor');
        }

        return redirect()->route('login');
    }

    public static function homeRedirect(User $user): RedirectResponse
    {
        if ($user->isStudent()) {
            return redirect()->route('page-public-visitor');
        }

        return redirect()->intended(route('page-dashboard'));
    }
}

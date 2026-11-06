<?php

namespace App\Http\Middleware;

use App\Setup\RoleGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficer
{
    public function __construct(private RoleGuard $guard) {}

    /**
     * Allow data changes only when the active session belongs to an officer.
     * A parallel coordinator session is not used for writes.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->guard->ensureOfficer($request, $next);
    }
}

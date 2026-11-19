<?php

namespace App\Http\Middleware;

use App\Setup\RoleGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function __construct(private RoleGuard $guard) {}

    /**
     * The active dashboard context must be an officer or a coordinator.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->guard->ensureAdmin($request, $next);
    }
}

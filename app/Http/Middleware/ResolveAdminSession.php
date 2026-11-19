<?php

namespace App\Http\Middleware;

use App\Setup\RoleGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAdminSession
{
    public function __construct(private RoleGuard $guard) {}

    /**
     * The dashboard uses the active officer or coordinator session.
     * Other guards in the same browser stay signed in.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->guard->resolveAdminSession($request, $next);
    }
}

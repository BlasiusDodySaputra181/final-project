<?php

namespace App\Http\Middleware;

use App\Setup\RoleGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudent
{
    public function __construct(private RoleGuard $guard) {}

    /**
     * Student features require the student guard.
     * Officer and coordinator sessions in the same browser stay signed in.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $this->guard->ensureStudent($request, $next);
    }
}

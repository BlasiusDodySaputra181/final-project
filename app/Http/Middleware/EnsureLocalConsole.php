<?php

namespace App\Http\Middleware;

use App\Support\LocalConsole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalConsole
{
    /**
     * A client that opened the public link must not manage the server or the database.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (LocalConsole::allows($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => LocalConsole::DENIED], 403);
        }

        abort(403, LocalConsole::DENIED);
    }
}

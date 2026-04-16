<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleAdminMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
if (session('rol_id') != 1) {
    abort(403, 'Esta sección es solo para administradores.');
}

        return $next($request);
    }
}

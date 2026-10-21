<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class LocalPortalReview
{
    public function handle(Request $request, Closure $next)
    {
        // Raw peer address, not proxy-provided X-Forwarded-For.
        abort_unless(
            app()->environment('local')
            && config('portal_review.enabled') === true
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true),
            404,
        );
        // No sessions/DB-backed CSRF middleware. Require a browser same-origin
        // submission; reject cross-site requests before constructing the reader.
        if ($request->isMethod('POST')) {
            abort_unless($request->header('Origin') === $request->getSchemeAndHttpHost(), 403);
        }
        return $next($request);
    }
}

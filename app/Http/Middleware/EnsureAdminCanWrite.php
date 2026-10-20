<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCanWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            abort_unless(
                $request->user()?->hasAnyRole(['superadmin', 'manager']),
                403,
                'No tienes permisos para modificar contenido.',
            );
        }

        return $next($request);
    }
}

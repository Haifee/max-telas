<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Candado por roles. Uso en rutas: ->middleware('ensure.rol:owner,manager')
 * El rol viene de la sesión (lo pone AuthController al validar el PIN), no del cliente.
 */
class EnsureRol
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!in_array(session('rol'), $roles, true)) {
            return response()->json([
                'error' => 'No tienes permiso para realizar esta acción',
            ], 403);
        }

        return $next($request);
    }
}

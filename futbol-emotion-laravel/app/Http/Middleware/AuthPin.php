<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Exige una sesión iniciada con PIN.
 * Los PIN se verifican en AuthController (vienen de config/tienda.php, no del código).
 * Aquí solo se comprueba que el rol de la sesión sea uno de los válidos.
 */
class AuthPin
{
    const ROLES = ['owner', 'manager', 'trabajador'];

    public function handle(Request $request, Closure $next)
    {
        $rol = session('rol');

        if (!$rol || !in_array($rol, self::ROLES, true)) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        // Si el dueño desactivó el acceso del encargado, cortar también las sesiones ya abiertas
        if ($rol === 'manager' && $this->managerBloqueado()) {
            session()->forget('rol');
            return response()->json(['error' => 'Este acceso está desactivado. Comunicate con el dueño.'], 401);
        }

        // Guardar el rol en el request para usarlo en los controladores
        $request->merge(['_rol' => $rol]);

        return $next($request);
    }

    private function managerBloqueado(): bool
    {
        try {
            $fila = DB::table('configuracion')->where('clave', 'manager_bloqueado')->first();
            return $fila && $fila->valor === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }
}

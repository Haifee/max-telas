<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Anti-XSS: limpia todo texto que llega al API antes de guardarlo.
 * Cambia < > " ' ` por caracteres parecidos e inofensivos (‹ › ″ ’), así un nombre
 * de producto o cliente nunca puede convertirse en código dentro de la app.
 * No toca PINs, claves, códigos de barras ni datos de notificaciones push.
 */
class SanitizarTexto
{
    private const OMITIR = ['pin', 'codigo', 'codigos', 'endpoint', 'keys', 'p256dh', 'auth', 'subscription', 'suscripcion'];

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            if ($request->isJson()) {
                $request->json()->replace($this->limpiar($request->json()->all()));
            } else {
                $request->request->replace($this->limpiar($request->request->all()));
            }
        }
        return $next($request);
    }

    private function omitir($clave): bool
    {
        if (!is_string($clave)) return false;
        $k = strtolower($clave);
        return in_array($k, self::OMITIR, true) || str_contains($k, 'clave') || str_contains($k, 'pin');
    }

    private function limpiar($valor, $clave = null)
    {
        if ($clave !== null && $this->omitir($clave)) return $valor;
        if (is_array($valor)) {
            $out = [];
            foreach ($valor as $k => $v) {
                $out[is_string($k) ? $this->texto($k) : $k] = $this->limpiar($v, $k);
            }
            return $out;
        }
        return is_string($valor) ? $this->texto($valor) : $valor;
    }

    private function texto(string $s): string
    {
        if (!preg_match('/[<>"\'`]/', $s)) return $s;
        // Textos que son JSON (ej. la lista de marcas en configuración): limpiar por dentro
        $t = ltrim($s);
        if ($t !== '' && ($t[0] === '[' || $t[0] === '{')) {
            $dec = json_decode($t, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                return json_encode($this->limpiar($dec), JSON_UNESCAPED_UNICODE);
            }
        }
        return strtr($s, ['<' => '‹', '>' => '›', '"' => '″', "'" => '’', '`' => '’']);
    }
}

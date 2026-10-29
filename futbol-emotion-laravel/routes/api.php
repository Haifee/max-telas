<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CamisetaController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\TransaccionController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\CierreController;
use App\Http\Controllers\MigracionController;
use App\Http\Controllers\ActividadController;

// ── AUTH ─────────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);

// ── RUTAS PROTEGIDAS (requieren sesión) ───────────────────────────────────────
// Permisos (los decide el SERVIDOR, no la pantalla):
//   · Cualquier rol (incluido trabajador): leer datos, vender con el carrito,
//     buscar códigos de barras, actividad y notificaciones.
//   · Dueño y encargado ('ensure.rol:owner,manager'): todo lo que modifica
//     inventario, ventas ya hechas, pedidos, envíos, gastos y cierres.
//   · Solo dueño ('ensure.owner'): aprobaciones, borrados, configuración, importar.
Route::middleware('auth.pin')->group(function () {

    // ── Cualquier rol ──
    Route::get('/camisetas',                  [CamisetaController::class, 'index']);
    Route::get('/camisetas/barcode/{codigo}', [CamisetaController::class, 'buscarPorCodigo']);

    Route::get('/ventas',          [VentaController::class, 'index']);
    Route::get('/ventas/resumen',  [VentaController::class, 'resumen']);
    Route::post('/ventas/carrito', [VentaController::class, 'storeCarrito']);

    Route::get('/pedidos',         [PedidoController::class, 'index']);
    Route::get('/envios',          [EnvioController::class, 'index']);
    Route::get('/devoluciones',    [DevolucionController::class, 'index']);
    Route::get('/transacciones',   [TransaccionController::class, 'index']);
    Route::get('/transacciones/cierre', [TransaccionController::class, 'cierre']);
    Route::get('/config',          [ConfigController::class, 'index']);

    Route::get('/cierres',            [CierreController::class, 'index']);
    Route::get('/cierres/estado',     [CierreController::class, 'estado']);
    Route::get('/cierres/mensuales',  [CierreController::class, 'mensuales']);
    Route::get('/cierres/dia/{fecha}',[CierreController::class, 'resumenDia']);

    Route::get('/push/clave',        [PushController::class, 'clavePublica']);
    Route::post('/push/suscribir',   [PushController::class, 'suscribir']);
    Route::post('/push/desuscribir', [PushController::class, 'desuscribir']);

    Route::get('/actividad',         [ActividadController::class, 'index']);
    Route::post('/actividad',        [ActividadController::class, 'store']);
    Route::post('/actividad/vistas', [ActividadController::class, 'marcarVistas']);

    // ── Dueño y encargado ──
    Route::middleware('ensure.rol:owner,manager')->group(function () {
        // Inventario
        Route::post('/camisetas/barcode',   [CamisetaController::class, 'asociarCodigo']);
        Route::post('/camisetas',           [CamisetaController::class, 'store']);
        Route::put('/camisetas/{id}',       [CamisetaController::class, 'update']);
        Route::put('/camisetas/{id}/stock', [CamisetaController::class, 'ajustarStock']);

        // Ventas: venta suelta, corregir y borrar (queda registrado en el historial)
        Route::post('/ventas',          [VentaController::class, 'store']);
        Route::put('/ventas/{id}',      [VentaController::class, 'update']);
        Route::delete('/ventas/{id}',   [VentaController::class, 'destroy']);

        // Pedidos, envíos, devoluciones
        Route::post('/pedidos',                [PedidoController::class, 'store']);
        Route::put('/pedidos/{id}/recibido',   [PedidoController::class, 'marcarRecibido']);
        Route::post('/envios',                 [EnvioController::class, 'store']);
        Route::put('/envios/{id}',             [EnvioController::class, 'update']);
        Route::put('/envios/{id}/estado',      [EnvioController::class, 'avanzarEstado']);
        Route::post('/devoluciones',           [DevolucionController::class, 'store']);

        // Gastos/ingresos manuales y cierres de caja
        Route::post('/transacciones', [TransaccionController::class, 'store']);
        Route::post('/cierres',       [CierreController::class, 'store']);

        Route::post('/push/prueba',   [PushController::class, 'prueba']);
    });

    // ── Solo dueño ──
    Route::middleware('ensure.owner')->group(function () {
        Route::post('/camisetas/importar',   [CamisetaController::class, 'importar']);
        Route::delete('/camisetas/{id}',     [CamisetaController::class, 'destroy']);

        Route::put('/pedidos/{id}/aprobar',  [PedidoController::class, 'aprobar']);
        Route::put('/pedidos/{id}/rechazar', [PedidoController::class, 'rechazar']);

        Route::put('/devoluciones/{id}/aprobar',   [DevolucionController::class, 'aprobar']);
        Route::put('/devoluciones/{id}/rechazar',  [DevolucionController::class, 'rechazar']);
        Route::put('/devoluciones/{id}/completar', [DevolucionController::class, 'completar']);

        Route::delete('/transacciones/{id}', [TransaccionController::class, 'destroy']);

        Route::post('/config',          [ConfigController::class, 'update']);
        Route::post('/config/tasa-bcv', [ConfigController::class, 'tasaBcv']);

        Route::delete('/cierres/{id}',  [CierreController::class, 'destroy']);

        Route::delete('/actividad/{id}', [ActividadController::class, 'destroy']);
        Route::delete('/actividad',      [ActividadController::class, 'limpiar']);

        // Migración desde localStorage: BORRA tablas. Además está apagada por
        // configuración (tienda.permitir_migracion) salvo que se active a propósito.
        Route::post('/migrar', [MigracionController::class, 'importar']);

        // Borrado de datos (reinicio)
        Route::post('/datos/borrar', [\App\Http\Controllers\DatosController::class, 'borrar']);
    });
});

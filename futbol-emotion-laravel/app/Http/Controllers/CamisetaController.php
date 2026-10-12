<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CamisetaController extends Controller
{
    // ── Tallas flexibles: el inventario vive en camisetas.tallas_json ────────────
    // (clave = talla en MAYÚSCULAS, valor = unidades). Se siguen escribiendo las
    // columnas talla_* antiguas para no romper nada que aún las lea.
    private function tallasObj($c){
        if ($c && !empty($c->tallas_json)) {
            $o = json_decode($c->tallas_json, true);
            if (is_array($o)) return array_map(fn($v) => (int)$v, $o);
        }
        $legacy = ['S'=>'talla_s','M'=>'talla_m','L'=>'talla_l','XL'=>'talla_xl','XXL'=>'talla_xxl','10'=>'talla_10','12'=>'talla_12','14'=>'talla_14','16'=>'talla_16','U'=>'talla_u'];
        $o = [];
        foreach ($legacy as $k => $col) { $v = (int)($c->$col ?? 0); if ($v !== 0) $o[$k] = $v; }
        return $o;
    }

    public function index()
    {
        $camisetas = DB::table('camisetas')->orderBy('equipo')->get();

        return response()->json($camisetas->map(function ($c) {
            return $this->formatear($c);
        }));
    }

    public function store(Request $request)
    {
        $request->validate([
            'equipo'       => 'required|string|max:100',
            'temporada'    => 'nullable|string|max:60',
            'tipo'         => 'required|in:Local,Visitante,Tercera,Portero,Otro',
            'categoria'    => 'nullable|string|max:50',
            'tallas'       => 'required|array',
            'stock_minimo' => 'required|integer|min:0',
            'proveedor_id' => 'required|integer|between:1,4',
            'precio'       => 'nullable|numeric|min:0',
            'precio2'      => 'nullable|numeric|min:0',
            'color'        => 'nullable|string|max:40',
            'departamento' => 'nullable|string|max:40',
        ]);

        $tallas = $request->input('tallas', []);
        $tallas = array_map(fn($v) => max(0, (int)$v), $tallas); // nunca negativos

        $id = DB::table('camisetas')->insertGetId([
            'equipo'        => $request->equipo,
            'temporada'     => $request->temporada,
            'tipo'          => $request->tipo,
            'categoria'     => $request->input('categoria', 'camiseta') ?: 'camiseta',
            'talla_s'       => $tallas['S']   ?? 0,
            'talla_m'       => $tallas['M']   ?? 0,
            'talla_l'       => $tallas['L']   ?? 0,
            'talla_xl'      => $tallas['XL']  ?? 0,
            'talla_xxl'     => $tallas['XXL'] ?? 0,
            'talla_10'      => $tallas['10']  ?? 0,
            'talla_12'      => $tallas['12']  ?? 0,
            'talla_14'      => $tallas['14']  ?? 0,
            'talla_16'      => $tallas['16']  ?? 0,
            'talla_u'       => $tallas['U']   ?? 0,
            'tallas_json'   => json_encode($tallas, JSON_UNESCAPED_UNICODE),
            'stock_minimo'  => $request->stock_minimo,
            'proveedor_id'  => $request->proveedor_id,
            'precio'        => $request->input('precio'),
            'precio2'       => $request->input('precio2'),
            'color'         => $request->input('color'),
            'departamento'  => $request->input('departamento'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json($this->formatear(DB::table('camisetas')->find($id)), 201);
    }

    public function update(Request $request, $id)
    {
        $camiseta = DB::table('camisetas')->find($id);
        if (!$camiseta) return response()->json(['error' => 'No encontrada'], 404);

        $incoming = $request->input('tallas', []);
        $incoming = array_map(fn($v) => max(0, (int)$v), $incoming); // nunca negativos
        // El formulario envía el SET COMPLETO de tallas del tipo elegido: lo entrante
        // reemplaza al set anterior (así cambiar de tallaje no deja tallas huérfanas).
        // Si no viene ninguna talla, se conservan las actuales.
        $tallas = !empty($incoming) ? $incoming : $this->tallasObj($camiseta);

        DB::table('camisetas')->where('id', $id)->update([
            'categoria'    => $request->input('categoria', $camiseta->categoria ?? 'camiseta'),
            'equipo'       => $request->input('equipo', $camiseta->equipo),
            'temporada'    => $request->input('temporada', $camiseta->temporada),
            'tipo'         => $request->input('tipo', $camiseta->tipo),
            'talla_s'      => $tallas['S']   ?? 0,
            'talla_m'      => $tallas['M']   ?? 0,
            'talla_l'      => $tallas['L']   ?? 0,
            'talla_xl'     => $tallas['XL']  ?? 0,
            'talla_xxl'    => $tallas['XXL'] ?? 0,
            'talla_10'     => $tallas['10']  ?? 0,
            'talla_12'     => $tallas['12']  ?? 0,
            'talla_14'     => $tallas['14']  ?? 0,
            'talla_16'     => $tallas['16']  ?? 0,
            'talla_u'      => $tallas['U']   ?? 0,
            'tallas_json'  => json_encode($tallas, JSON_UNESCAPED_UNICODE),
            'stock_minimo' => $request->input('stock_minimo', $camiseta->stock_minimo),
            'proveedor_id' => $request->input('proveedor_id', $camiseta->proveedor_id),
            'precio'       => $request->input('precio', $camiseta->precio),
            'precio2'      => $request->input('precio2', $camiseta->precio2),
            'color'        => $request->input('color', $camiseta->color),
            'departamento' => $request->input('departamento', $camiseta->departamento),
            'updated_at'   => now(),
        ]);

        return response()->json($this->formatear(DB::table('camisetas')->find($id)));
    }

    public function destroy($id)
    {
        $eliminadas = DB::table('camisetas')->where('id', $id)->delete();
        if (!$eliminadas) return response()->json(['error' => 'No encontrada'], 404);
        return response()->json(['ok' => true]);
    }

    public function ajustarStock(Request $request, $id)
    {
        $camiseta = DB::table('camisetas')->find($id);
        if (!$camiseta) return response()->json(['error' => 'No encontrada'], 404);

        $incoming = $request->input('tallas', []);
        $incoming = array_map(fn($v) => max(0, (int)$v), $incoming); // nunca negativos
        $tallas = array_merge($this->tallasObj($camiseta), $incoming);

        DB::table('camisetas')->where('id', $id)->update([
            'talla_s'    => $tallas['S']   ?? 0,
            'talla_m'    => $tallas['M']   ?? 0,
            'talla_l'    => $tallas['L']   ?? 0,
            'talla_xl'   => $tallas['XL']  ?? 0,
            'talla_xxl'  => $tallas['XXL'] ?? 0,
            'talla_10'   => $tallas['10']  ?? 0,
            'talla_12'   => $tallas['12']  ?? 0,
            'talla_14'   => $tallas['14']  ?? 0,
            'talla_16'   => $tallas['16']  ?? 0,
            'talla_u'    => $tallas['U']   ?? 0,
            'tallas_json'=> json_encode($tallas, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return response()->json($this->formatear(DB::table('camisetas')->find($id)));
    }

    // ── CÓDIGOS DE BARRAS ────────────────────────────────────────────────────

    // Buscar camiseta+talla por código escaneado.
    // Siempre responde 200: {encontrado:true, ...} o {encontrado:false}
    public function buscarPorCodigo($codigo)
    {
        $fila = DB::table('codigos_barras')
            ->where('codigo', $codigo)
            ->first();

        if (!$fila) {
            return response()->json(['encontrado' => false, 'codigo' => $codigo]);
        }

        $camiseta = DB::table('camisetas')->find($fila->camiseta_id);
        if (!$camiseta) {
            // Código huérfano (no debería pasar por el cascade, pero por si acaso)
            DB::table('codigos_barras')->where('id', $fila->id)->delete();
            return response()->json(['encontrado' => false, 'codigo' => $codigo]);
        }

        return response()->json([
            'encontrado' => true,
            'codigo'     => $codigo,
            'talla'      => $fila->talla,
            'camiseta'   => $this->formatear($camiseta),
        ]);
    }

    // Asociar un código de barras a una camiseta + talla
    public function asociarCodigo(Request $request)
    {
        $request->validate([
            'codigo'      => 'required|string|max:64',
            'camiseta_id' => 'required|integer|exists:camisetas,id',
            'talla'       => 'required|string|max:10',
        ]);

        $existente = DB::table('codigos_barras')->where('codigo', $request->codigo)->first();
        if ($existente) {
            return response()->json(['error' => 'Ese código ya está asociado a otra camiseta'], 422);
        }

        DB::table('codigos_barras')->insert([
            'codigo'      => $request->codigo,
            'camiseta_id' => $request->camiseta_id,
            'talla'       => $request->talla,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    // Convierte el formato de la BD al formato que usa el frontend
    private function formatear($c)
    {
        return [
            'id'      => $c->id,
            'equipo'  => $c->equipo,
            'temp'    => $c->temporada,
            'tipo'    => $c->tipo,
            'categoria' => $c->categoria ?? 'camiseta',
            'tallas'  => $this->tallasObj($c),
            'min'    => (int)$c->stock_minimo,
            'prov'   => (int)$c->proveedor_id,
            'precio' => isset($c->precio) && $c->precio !== null ? (float)$c->precio : null,
            'precio2' => isset($c->precio2) && $c->precio2 !== null ? (float)$c->precio2 : null,
            'color' => $c->color ?? null,
            'departamento' => $c->departamento ?? null,
        ];
    }
}

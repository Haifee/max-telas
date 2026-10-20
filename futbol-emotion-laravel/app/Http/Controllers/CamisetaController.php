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
        $codigos = DB::table('codigos_barras')->orderBy('id')->get()->groupBy('camiseta_id');

        return response()->json($camisetas->map(function ($c) use ($codigos) {
            $f = $this->formatear($c);
            $map = [];
            if (isset($codigos[$c->id])) {
                foreach ($codigos[$c->id] as $row) {
                    $t = (string)($row->talla !== null && $row->talla !== '' ? $row->talla : 'U');
                    $map[$t] = $row->codigo;
                }
            }
            $f['codigos'] = $map;
            return $f;
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
            'proveedor_id' => 'required|integer|min:1',
            'precio'       => 'nullable|numeric|min:0',
            'precio2'      => 'nullable|numeric|min:0',
            'precio_costo' => 'nullable|numeric|min:0',
            'codigos'      => 'nullable|array',
            'color'        => 'nullable|string|max:40',
            'departamento' => 'nullable|string|max:40',
            'tipo_prenda'  => 'nullable|string|max:40',
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
            'precio_costo'  => $request->input('precio_costo'),
            'color'         => $request->input('color'),
            'departamento'  => $request->input('departamento'),
            'tipo_prenda'   => $request->input('tipo_prenda'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->guardarCodigos($id, $request->input('codigos', []));

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
            'precio_costo' => $request->input('precio_costo', $camiseta->precio_costo),
            'color'        => $request->input('color', $camiseta->color),
            'departamento' => $request->input('departamento', $camiseta->departamento),
            'tipo_prenda'  => $request->input('tipo_prenda', $camiseta->tipo_prenda),
            'updated_at'   => now(),
        ]);

        $this->guardarCodigos($id, $request->input('codigos', []));

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
            'talla'       => 'nullable|string|max:10',
        ]);

        $existente = DB::table('codigos_barras')->where('codigo', $request->codigo)->first();
        if ($existente && (int)$existente->camiseta_id !== (int)$request->camiseta_id) {
            return response()->json(['error' => 'Ese código ya está asociado a otro producto'], 422);
        }

        // Un código por producto: reasigna si ya existía
        DB::table('codigos_barras')->updateOrInsert(
            ['codigo' => $request->codigo],
            ['camiseta_id' => $request->camiseta_id, 'talla' => (string)($request->input('talla', '') ?? ''), 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['ok' => true], 201);
    }

    // Mapea un objeto de tallas flexibles a las columnas fijas legacy (las no listadas = 0)
    private function tallasAColumnas(array $tallas): array
    {
        $legacy = ['S'=>'talla_s','M'=>'talla_m','L'=>'talla_l','XL'=>'talla_xl','XXL'=>'talla_xxl','10'=>'talla_10','12'=>'talla_12','14'=>'talla_14','16'=>'talla_16','U'=>'talla_u'];
        $out = [];
        foreach ($legacy as $k => $col) { $out[$col] = max(0, (int)($tallas[$k] ?? 0)); }
        return $out;
    }

    // Asocia/reasigna códigos de barras por talla a un producto (uno por talla).
    private function guardarCodigos($camId, $codigos)
    {
        if (!is_array($codigos) || !$camId) return;
        foreach ($codigos as $talla => $cod) {
            $cod = trim((string)$cod);
            if ($cod === '') continue;
            DB::table('codigos_barras')->updateOrInsert(
                ['codigo' => mb_substr($cod, 0, 64)],
                ['camiseta_id' => $camId, 'talla' => mb_substr((string)$talla, 0, 10), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    // Importación masiva desde Excel: el frontend envía los productos ya agrupados.
    // Hace upsert por (equipo + color + departamento): si existe, actualiza; si no, crea.
    // Todo dentro de una transacción (si algo falla, no queda nada a medias).
    public function importar(Request $request)
    {
        $request->validate([
            'productos'               => 'required|array|min:1',
            'productos.*.equipo'      => 'required|string|max:100',
            'productos.*.tallas'      => 'required|array',
        ]);

        $items = $request->input('productos');
        $creados = 0; $actualizados = 0; $errores = [];

        DB::beginTransaction();
        try {
            foreach ($items as $idx => $p) {
                $equipo = trim((string)($p['equipo'] ?? ''));
                if ($equipo === '') { $errores[] = 'Fila '.($idx+1).': sin nombre'; continue; }

                $categoria    = trim((string)($p['categoria'] ?? 'camiseta')) ?: 'camiseta';
                $color        = isset($p['color']) && $p['color'] !== '' ? mb_substr(trim((string)$p['color']),0,40) : null;
                $departamento = isset($p['departamento']) && $p['departamento'] !== '' ? mb_substr(trim((string)$p['departamento']),0,40) : null;
                $tipoPrenda   = isset($p['tipo_prenda']) && $p['tipo_prenda'] !== '' ? mb_substr(trim((string)$p['tipo_prenda']),0,40) : null;
                $temp         = isset($p['temporada']) && $p['temporada'] !== '' ? mb_substr(trim((string)$p['temporada']),0,60) : '—';
                $tallas       = is_array($p['tallas'] ?? null) ? array_map(fn($v)=>max(0,(int)$v), $p['tallas']) : [];
                $precio       = isset($p['precio'])  && $p['precio']  !== '' ? (float)$p['precio']  : null;
                $precio2      = isset($p['precio2']) && $p['precio2'] !== '' ? (float)$p['precio2'] : null;
                $precioCosto  = isset($p['precio_costo']) && $p['precio_costo'] !== '' ? (float)$p['precio_costo'] : null;
                $min          = (int)($p['min'] ?? 5); if ($min < 0) $min = 0;
                $prov         = (int)($p['prov'] ?? 1); if ($prov < 1) $prov = 1;
                $codigos      = is_array($p['codigos'] ?? null) ? $p['codigos'] : [];

                $cols = $this->tallasAColumnas($tallas);

                // Buscar un producto equivalente para no duplicar
                $q = DB::table('camisetas')->where('equipo', $equipo);
                if ($color !== null) $q->where('color', $color); else $q->whereNull('color');
                if ($departamento !== null) $q->where('departamento', $departamento);
                $ex = $q->first();

                if ($ex) {
                    $camId = $ex->id;
                    DB::table('camisetas')->where('id', $ex->id)->update(array_merge($cols, [
                        'tallas_json'  => json_encode($tallas, JSON_UNESCAPED_UNICODE),
                        'temporada'    => $temp,
                        'categoria'    => $categoria,
                        'color'        => $color,
                        'departamento' => $departamento,
                        'tipo_prenda'  => $tipoPrenda,
                        'stock_minimo' => $min,
                        'proveedor_id' => $prov,
                        'precio'       => $precio,
                        'precio2'      => $precio2,
                        'precio_costo' => $precioCosto,
                        'updated_at'   => now(),
                    ]));
                    $actualizados++;
                } else {
                    $camId = DB::table('camisetas')->insertGetId(array_merge($cols, [
                        'equipo'       => $equipo,
                        'temporada'    => $temp,
                        'tipo'         => 'Otro',
                        'categoria'    => $categoria,
                        'color'        => $color,
                        'departamento' => $departamento,
                        'tipo_prenda'  => $tipoPrenda,
                        'tallas_json'  => json_encode($tallas, JSON_UNESCAPED_UNICODE),
                        'stock_minimo' => $min,
                        'proveedor_id' => $prov,
                        'precio'       => $precio,
                        'precio2'      => $precio2,
                        'precio_costo' => $precioCosto,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]));
                    $creados++;
                }

                // Códigos de barras: uno por talla (RE064-T6, RE064-T8…). Reasigna si ya existía.
                $this->guardarCodigos($camId, $codigos);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al importar: '.$e->getMessage()], 500);
        }

        return response()->json([
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'errores'      => $errores,
        ]);
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
            'precio_costo' => isset($c->precio_costo) && $c->precio_costo !== null ? (float)$c->precio_costo : null,
            'color' => $c->color ?? null,
            'departamento' => $c->departamento ?? null,
            'tipo_prenda' => $c->tipo_prenda ?? null,
        ];
    }
}

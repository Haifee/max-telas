<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tallas flexibles: el inventario pasa a guardarse en una columna JSON
     * (tallas_json) con la forma {"S":12,"M":8,"28":4,...}. Así el sistema admite
     * CUALQUIER talla (letras, números de niño, tallas de pantalón) sin tener que
     * agregar una columna nueva cada vez.
     *
     * Las columnas talla_* antiguas NO se eliminan: se dejan y se mantienen
     * sincronizadas por compatibilidad, para no romper nada que aún las lea.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('camisetas', 'tallas_json')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->text('tallas_json')->nullable()->after('talla_u');
            });
        }

        // Backfill: construir el JSON a partir de las columnas fijas existentes.
        $legacy = [
            'S' => 'talla_s', 'M' => 'talla_m', 'L' => 'talla_l',
            'XL' => 'talla_xl', 'XXL' => 'talla_xxl',
            '10' => 'talla_10', '12' => 'talla_12', '14' => 'talla_14', '16' => 'talla_16',
            'U' => 'talla_u',
        ];

        DB::table('camisetas')->orderBy('id')->chunk(200, function ($rows) use ($legacy) {
            foreach ($rows as $r) {
                // No pisar un json que ya exista (re-ejecución segura)
                if (!empty($r->tallas_json)) {
                    continue;
                }
                $obj = [];
                foreach ($legacy as $k => $col) {
                    $v = (int) ($r->$col ?? 0);
                    if ($v !== 0) {
                        $obj[$k] = $v;
                    }
                }
                DB::table('camisetas')->where('id', $r->id)->update([
                    'tallas_json' => json_encode($obj, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('camisetas', 'tallas_json')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->dropColumn('tallas_json');
            });
        }
    }
};

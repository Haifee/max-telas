<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ubicación física del producto: "Almacén" (tienda) o "Depósito".
    // Permite ver por separado qué hay en cada sitio. Por defecto "Almacén".
    public function up(): void
    {
        if (!Schema::hasColumn('camisetas', 'ubicacion')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->string('ubicacion', 20)->default('Almacén')->after('tipo_prenda');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('camisetas', 'ubicacion')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->dropColumn('ubicacion');
            });
        }
    }
};

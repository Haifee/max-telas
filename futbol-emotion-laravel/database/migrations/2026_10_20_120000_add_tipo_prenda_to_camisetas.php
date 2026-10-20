<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tipo de prenda / categoría para clasificar: Unisex, Niña, Niño, Dama,
    // Caballero, Manga princesa (M/P)… Es un campo aparte del departamento.
    public function up(): void
    {
        if (!Schema::hasColumn('camisetas', 'tipo_prenda')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->string('tipo_prenda', 40)->nullable()->after('departamento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('camisetas', 'tipo_prenda')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->dropColumn('tipo_prenda');
            });
        }
    }
};

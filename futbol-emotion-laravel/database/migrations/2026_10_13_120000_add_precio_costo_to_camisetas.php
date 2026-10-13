<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Precio de costo del producto (lo que le cuesta a la tienda).
    // Sirve para calcular el margen de ganancia frente a Precio 1 / Precio 2.
    public function up(): void
    {
        if (!Schema::hasColumn('camisetas', 'precio_costo')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->decimal('precio_costo', 12, 2)->nullable()->after('precio2');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('camisetas', 'precio_costo')) {
            Schema::table('camisetas', function (Blueprint $table) {
                $table->dropColumn('precio_costo');
            });
        }
    }
};

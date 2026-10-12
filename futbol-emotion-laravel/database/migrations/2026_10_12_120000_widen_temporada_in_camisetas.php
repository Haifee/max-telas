<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            // "temporada" ahora guarda la MARCA: necesita más largo que "24/25"
            $table->string('temporada', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            $table->string('temporada', 10)->nullable()->change();
        });
    }
};

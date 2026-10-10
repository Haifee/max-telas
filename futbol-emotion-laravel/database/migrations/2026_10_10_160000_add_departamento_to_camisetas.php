<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            $table->string('departamento', 40)->nullable()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            $table->dropColumn('departamento');
        });
    }
};

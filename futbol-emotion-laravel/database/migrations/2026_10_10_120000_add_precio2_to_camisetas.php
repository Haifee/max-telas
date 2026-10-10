<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            $table->decimal('precio2', 10, 2)->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('camisetas', function (Blueprint $table) {
            $table->dropColumn('precio2');
        });
    }
};

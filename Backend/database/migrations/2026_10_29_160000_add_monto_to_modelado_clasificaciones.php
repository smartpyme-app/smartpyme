<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->decimal('monto', 15, 2)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('modelado_clasificaciones', function (Blueprint $table) {
            $table->dropColumn('monto');
        });
    }
};

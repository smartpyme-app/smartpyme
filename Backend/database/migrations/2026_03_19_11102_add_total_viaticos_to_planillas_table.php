<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Total viáticos de la planilla (suma de viáticos por empleado).
     */
    public function up(): void
    {
        Schema::table('planillas', function (Blueprint $table) {
            $table->decimal('total_viaticos', 10, 2)->default(0)->after('total_neto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planillas', function (Blueprint $table) {
            $table->dropColumn('total_viaticos');
        });
    }
};

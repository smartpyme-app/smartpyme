<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Viaticos: no constituyen salario ni renta gravable etc...segun ley
     * No afectan ISSS, AFP ni ISR. Se suman al total a pagar.
     */
    public function up(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->decimal('viaticos', 10, 2)->default(0)->after('abonos_sin_retencion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->dropColumn('viaticos');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinica_expedientes', function (Blueprint $table) {
            $table->integer('id_sucursal_apertura')->nullable()->after('id_paciente');
            $table->foreign('id_sucursal_apertura')->references('id')->on('sucursales')->nullOnDelete();
        });

        // ponytail: backfill one-shot desde sucursal actual del paciente
        DB::statement('
            UPDATE clinica_expedientes e
            INNER JOIN clinica_pacientes p ON p.id = e.id_paciente
            SET e.id_sucursal_apertura = p.id_sucursal
            WHERE e.id_sucursal_apertura IS NULL AND p.id_sucursal IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('clinica_expedientes', function (Blueprint $table) {
            $table->dropForeign(['id_sucursal_apertura']);
            $table->dropColumn('id_sucursal_apertura');
        });
    }
};

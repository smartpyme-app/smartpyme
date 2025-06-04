<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeAreaEmpresaToIdAreaEmpresaInEgresosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('egresos', function (Blueprint $table) {
            // Agregar nueva columna id_area_empresa
            $table->unsignedBigInteger('id_area_empresa')->nullable();
            
            // Crear índice y foreign key
            $table->index('id_area_empresa');
            $table->foreign('id_area_empresa')->references('id')->on('areas_empresa')->onDelete('set null');
        });
            
            // Opcional: Migrar datos existentes si hay texto que coincida con nombres de áreas
            /*
            DB::statement("
                UPDATE egresos e 
                INNER JOIN areas_empresa ae ON ae.nombre = e.area_empresa 
                SET e.id_area_empresa = ae.id 
                WHERE e.area_empresa IS NOT NULL
            ");
            */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropForeign(['id_area_empresa']);
            $table->dropIndex(['id_area_empresa']);
            $table->dropColumn('id_area_empresa');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentoEmpleadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documentos_empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empleado')->constrained('empleados')->onDelete('cascade');
            $table->integer('tipo_documento'); // Contrato, DUI, NIT, etc.
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->date('fecha_documento');
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();

            // Índice para búsquedas por tipo de documento
            $table->index('tipo_documento');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentos_empleado');
    }
}

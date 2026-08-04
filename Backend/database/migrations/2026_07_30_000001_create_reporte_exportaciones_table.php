<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReporteExportacionesTable extends Migration
{
    public function up()
    {
        Schema::create('reporte_exportaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_configuracion');
            $table->string('modo', 20); // download | email
            $table->string('formato', 20); // excel | pdf
            $table->string('estado', 20)->default('pending'); // pending | processing | done | failed
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->json('sucursales')->nullable();
            $table->json('destinatarios')->nullable();
            $table->string('ruta_archivo')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'estado']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reporte_exportaciones');
    }
}

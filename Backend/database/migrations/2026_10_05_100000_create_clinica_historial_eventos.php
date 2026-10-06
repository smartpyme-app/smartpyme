<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_historial_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->string('tipo', 40);
            $table->date('fecha_evento');
            $table->time('hora_evento')->nullable();
            $table->unsignedBigInteger('id_usuario_profesional')->nullable();
            $table->string('origen_tipo', 40);
            $table->unsignedBigInteger('origen_id');
            $table->string('resumen', 255);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->index(['id_expediente', 'fecha_evento', 'hora_evento']);
            $table->unique(['origen_tipo', 'origen_id']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        foreach (DB::table('clinica_expedientes')->orderBy('id')->get() as $expediente) {
            $existe = DB::table('clinica_historial_eventos')
                ->where('origen_tipo', 'expediente')
                ->where('origen_id', $expediente->id)
                ->exists();
            if ($existe) {
                return;
            }
            DB::table('clinica_historial_eventos')->insert([
                'id_empresa' => $expediente->id_empresa,
                'id_expediente' => $expediente->id,
                'tipo' => 'expediente_apertura',
                'fecha_evento' => $expediente->fecha_apertura,
                'hora_evento' => null,
                'id_usuario_profesional' => null,
                'origen_tipo' => 'expediente',
                'origen_id' => $expediente->id,
                'resumen' => 'Apertura del expediente clínico',
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_historial_eventos');
    }
};

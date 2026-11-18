<?php

use Database\Seeders\NifMapeoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nif_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->boolean('afecta_resultados')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('norma', 30)->default('NIIF_PYMES');
            $table->timestamps();
        });

        // id_empresa = 0 es el mapeo base de SmartPyme. Una empresa lo pisa con su propia fila.
        Schema::create('nif_mapeo_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->default(0);
            $table->string('origen', 10); // tipo | categoria
            $table->string('clave', 120); // nombre del tipo, o id de gastos_categorias
            $table->foreignId('id_nif_categoria')->constrained('nif_categorias');
            $table->boolean('revisar')->default(false);
            $table->timestamps();

            $table->unique(['id_empresa', 'origen', 'clave'], 'nif_mapeo_gastos_unico');
        });

        (new NifMapeoSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('nif_mapeo_gastos');
        Schema::dropIfExists('nif_categorias');
    }
};

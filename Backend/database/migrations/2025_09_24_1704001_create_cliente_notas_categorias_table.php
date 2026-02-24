<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cliente_notas_categorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_nota');
            $table->string('categoria'); // 'preferencias', 'quejas', 'comentarios', 'seguimiento'
            $table->string('subcategoria')->nullable(); // 'productos_favoritos', 'metodos_pago', 'problemas_tecnico', etc.
            $table->decimal('score_relevancia', 3, 2)->default(1.00); // Score de relevancia de la categorización
            $table->timestamps();

            $table->foreign('id_nota')->references('id')->on('cliente_notas')->onDelete('cascade');
            $table->index(['categoria', 'subcategoria']);
            $table->index(['id_nota', 'categoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_notas_categorias');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consigna_custodias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->index();
            $table->unsignedBigInteger('id_cliente')->index();
            $table->unsignedBigInteger('id_producto')->index();
            $table->unsignedBigInteger('id_bodega')->index();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['id_empresa', 'id_cliente', 'id_producto', 'id_bodega'],
                'consigna_custodias_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consigna_custodias');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compra_impuestos')) {
            return;
        }

        Schema::create('compra_impuestos', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('monto', 10, 4)->default(0);
            $table->unsignedInteger('id_impuesto');
            $table->unsignedInteger('id_compra');
            $table->timestamps();

            $table->index('id_compra');
            $table->index('id_impuesto');
            $table->unique(['id_compra', 'id_impuesto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_impuestos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comandas_restaurante')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                if (! Schema::hasColumn('comandas_restaurante', 'preparando_at')) {
                    $table->timestamp('preparando_at')->nullable();
                }
                if (! Schema::hasColumn('comandas_restaurante', 'listo_at')) {
                    $table->timestamp('listo_at')->nullable();
                }
                if (! Schema::hasColumn('comandas_restaurante', 'servido_at')) {
                    $table->timestamp('servido_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('comanda_estado_tiempos')) {
            Schema::create('comanda_estado_tiempos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa');
                $table->foreignId('comanda_id')->constrained('comandas_restaurante')->cascadeOnDelete();
                $table->string('estado_desde', 20);
                $table->string('estado_hasta', 20);
                $table->timestamp('inicio_at');
                $table->timestamp('fin_at');
                $table->unsignedInteger('segundos');
                $table->timestamps();

                $table->index(['id_empresa', 'comanda_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_estado_tiempos');

        if (Schema::hasTable('comandas_restaurante')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                foreach (['preparando_at', 'listo_at', 'servido_at'] as $col) {
                    if (Schema::hasColumn('comandas_restaurante', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

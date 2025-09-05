<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTiposClienteEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipos_cliente_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empresa')->constrained('empresas');
            $table->foreignId('id_tipo_base')->nullable()->constrained('tipos_cliente_base');
            $table->integer('nivel')->nullable()->comment('1=STANDARD, 2=VIP, 3=ULTRAVIP');
            $table->string('nombre_personalizado')->nullable();
            $table->boolean('activo')->default(true);
            
            // Configuración COR
            $table->decimal('puntos_por_dolar', 8, 4)->default(1.0000)->comment('CORE - DEFAULT 1.0000');
            $table->integer('minimo_canje')->default(100)->comment('CORE - DEFAULT 100');
            $table->integer('maximo_canje')->default(1000)->comment('CORE - DEFAULT 1000');
            $table->integer('expiracion_meses')->default(12)->comment('CORE - DEFAULT 12');
            
            // Configuración HÍBRIDA para flexibilidad futura
            $table->json('configuracion_avanzada')->nullable()->comment('HÍBRIDO - flexibilidad futura');
            
            // Control de default
            $table->boolean('is_default')->default(false)->comment('único por empresa');
            $table->integer('default_key')->storedAs('CASE WHEN is_default = 1 THEN CONCAT(id_empresa, "_", nivel) ELSE NULL END');

            
            $table->timestamps();
            
            // Constraint: solo un default por empresa
            $table->unique(['id_empresa', 'nivel', 'default_key']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipos_cliente_empresa');
    }
}

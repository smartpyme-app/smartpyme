<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpleadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('empleados')) {
            Schema::create('empleados', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20);
                $table->string('nombres', 100);
                $table->string('apellidos', 100);
                $table->string('dui', 10)->unique();
                $table->string('nit', 17)->unique();
                $table->string('isss', 20)->nullable();
                $table->string('afp', 20)->nullable();
                $table->date('fecha_nacimiento');
                $table->text('direccion');
                $table->string('telefono', 20);
                $table->string('email')->unique();
                $table->decimal('salario_base', 10, 2);
                $table->integer('tipo_contrato');
                $table->integer('tipo_jornada');
                $table->date('fecha_ingreso');
                $table->date('fecha_fin')->nullable();
                $table->integer('estado')->default(1);
                $table->foreignId('id_departamento')->constrained('departamentos_empresa');
                $table->foreignId('id_cargo')->constrained('cargos_de_empresa');
                $table->integer('id_sucursal')->unsigned();
                $table->integer('id_empresa')->unsigned();
                $table->foreign('id_sucursal')
                    ->references('id')
                    ->on('sucursales')
                    ->onDelete('cascade');
                    $table->foreign('id_empresa')
                    ->references('id')
                    ->on('empresas')
                    ->onDelete('cascade');    
                $table->timestamps();
                $table->softDeletes();

                // Índices compuestos para optimizar búsquedas
                $table->unique(['codigo', 'id_sucursal']);
                $table->index(['estado', 'id_sucursal']);
                $table->index(['id_sucursal', 'id_departamento']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('empleados');
        Schema::enableForeignKeyConstraints();
    }
}

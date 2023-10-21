    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresasTable extends Migration {

    public function up()
    {
        Schema::create('empresas', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->string('propietario')->nullable();
            $table->string('sector')->nullable();
            $table->string('giro')->nullable();
            $table->string('nit')->nullable();
            $table->string('registro')->nullable();
            $table->string('tipo_contribuyente')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('logo')->default('empresas/default.jpg');
            
            $table->string('direccion')->default(0);
            $table->string('pais')->default(0);
            $table->string('moneda')->default(0);
            $table->boolean('impuesto')->default(0);
            $table->boolean('editar_precio_venta')->default(0);
            $table->boolean('vender_sin_stock')->default(0);
            $table->string('valor_inventario')->default('Promedio');
            $table->string('ips', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('empresas');
    }

}

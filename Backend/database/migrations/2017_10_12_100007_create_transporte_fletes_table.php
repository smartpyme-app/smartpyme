    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransporteFletesTable extends Migration {

    public function up()
    {
        Schema::create('transporte_fletes', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha')->nullable();
            $table->string('tipo')->nullable();
            $table->string('estado')->nullable();
            $table->integer('cliente_id')->nullable();
            $table->integer('proveedor_id')->nullable();
            $table->integer('motorista_id')->nullable();
            $table->integer('cabezal_id')->nullable();
            $table->integer('remolque_id')->nullable();
            $table->string('tipo_transporte')->nullable();
            $table->datetime('fecha_carga')->nullable();
            $table->datetime('fecha_descarga')->nullable();
            $table->string('punto_origen')->nullable();
            $table->string('punto_destino')->nullable();
            $table->string('aduana_entrada')->nullable();
            $table->string('aduana_salida')->nullable();
            $table->string('num_seguimiento')->nullable();
            $table->string('num_pedido')->nullable();
            $table->decimal('galones', 9,2)->nullable();
            $table->decimal('subtotal', 9,2)->default(0);
            $table->decimal('motorista', 9,2)->default(0);
            $table->decimal('combustible', 9,2)->default(0);
            $table->decimal('gastos', 9,2)->default(0);
            $table->decimal('seguro', 9,2)->default(0);
            $table->decimal('otros', 9,2)->default(0);
            $table->decimal('no_sujeto', 9,2)->default(0);
            $table->decimal('total', 9,2)->default(0);
            $table->string('nota_facturacion')->nullable();
            $table->string('nota')->nullable();

            $table->integer('venta_id')->nullable();
            $table->integer('usuario_id');
            $table->integer('sucursal_id');

            
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('transporte_fletes');
    }

}

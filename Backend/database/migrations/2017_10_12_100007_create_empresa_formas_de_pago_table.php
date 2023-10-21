    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaFormasDePagoTable extends Migration {

    public function up()
    {
        Schema::create('empresa_formas_de_pago', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->integer('orden')->nullable();
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('empresa_formas_de_pago');
    }

}

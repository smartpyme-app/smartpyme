    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaImpuestosTable extends Migration {

    public function up()
    {
        Schema::create('empresa_impuestos', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->decimal('porcentaje',6,2);
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('empresa_impuestos');
    }

}

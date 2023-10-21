    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaBancosTable extends Migration {

    public function up()
    {
        Schema::create('empresa_bancos', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('contacto')->nullable();
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('empresa_bancos');
    }

}

    <?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaDashboardsTable extends Migration {

    public function up()
    {
        Schema::create('empresa_dashboards', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->string('plataforma')->nullable();
            $table->string('tipo')->nullable();
            $table->text('codigo')->nullable();
            $table->text('codigo_movil')->nullable();
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('empresa_dashboards');
    }

}

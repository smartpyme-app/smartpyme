<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFulltextIndexesVentasClientes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE ventas ADD FULLTEXT INDEX ft_ventas_buscador (num_orden, observaciones, forma_pago, estado, numero_control)');
        DB::statement('ALTER TABLE clientes ADD FULLTEXT INDEX ft_clientes_buscador (nombre, apellido, nombre_empresa, nit, ncr)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE ventas DROP INDEX ft_ventas_buscador');
        DB::statement('ALTER TABLE clientes DROP INDEX ft_clientes_buscador');
    }
}

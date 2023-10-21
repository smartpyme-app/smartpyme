<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transporte\Fletes\Flete;
use App\Models\Transporte\Fletes\Detalle;
use App\Models\Transporte\Motoristas\Motorista;
use App\Models\Transporte\Flotas\Flota;
use App\Models\Ventas\Clientes\Cliente;
     
class FletesTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();


        for($i = 0; $i <= 20 ; $i++)
        {
            $table = new Flete;

            $table->fecha           = $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now', $timezone = null);
            $table->tipo            = $faker->randomElement(['Local','Importación']);
            $table->estado          = $faker->randomElement(['Pagado','Pendiente']);
            $table->cliente_id      = Cliente::inRandomOrder()->first()->id;
            $table->cabezal_id      = Flota::where('tipo', 'Cabezal')->inRandomOrder()->first()->id;
            $table->remolque_id     = Flota::where('tipo', 'Remolque')->inRandomOrder()->first()->id;
            $table->motorista_id    = Motorista::inRandomOrder()->first()->id;
            $table->tipo_transporte = $faker->randomElement(['Furgón']);
            $table->fecha_carga     = $faker->datetime;
            $table->fecha_descarga  = $faker->datetime;
            $table->punto_origen    = 'Primer lugar';
            $table->punto_destino   = 'Segundo lugar';
            $table->aduana_entrada  = 'Aduana A';
            $table->aduana_salida   = 'Aduana B';
            $table->num_seguimiento = $faker->unique()->ipv4;
            $table->subtotal        = $faker->numberBetween(500, 1000);
            $table->motorista       = $faker->numberBetween(50, 100);
            $table->combustible     = $faker->numberBetween(50, 100);
            $table->gastos          = $faker->numberBetween(50, 100);
            $table->seguro          = $faker->numberBetween(50, 100);
            $table->otros           = 0;
            $table->total           = $table->subtotal + $table->motorista + $table->combustible + $table->viaticos + $table->seguro + $table->otros;
            $table->nota            = '';
            $table->usuario_id      = 1;
            $table->sucursal_id     = 1;
            $table->save();

            for ($j=0; $j < $faker->numberBetween(1,4); $j++) {
                $detalle = new Detalle;
                $detalle->descripcion     = 'Lorem ipsup';
                $detalle->peso            = 100;
                $detalle->unidades        = $faker->numberBetween(1, 10);
                $detalle->bultos          = $faker->numberBetween(1, 10);
                $detalle->valor_carga     = $faker->numberBetween(100, 10000);
                $detalle->tipo_embalaje   = $faker->randomElement(['Granel','Paletas', 'Bolsas','Cajas']);
                $detalle->flete_id        = $i + 1;
                $detalle->save();
            }
           
        }

    }
     
}

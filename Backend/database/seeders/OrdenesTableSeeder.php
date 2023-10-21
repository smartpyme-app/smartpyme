<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ordenes\Orden;
use App\Models\Ordenes\Detalle;
     
class OrdenesTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();

        for($i = 1; $i <= 100 ; $i++)
        {
            $table = new Orden;

            $table->fecha       = $faker->dateTimeBetween($startDate = '-10 days', $endDate = '-1 days', $timezone = null);
            $table->estado      = $faker->randomElement(['En proceso', 'Facturada', 'Cancelada']);
            $table->tipo_servicio = $faker->randomElement(['Sala de Venta', 'Ruta', 'A Domicilio']);
            $table->total       = $faker->numberBetween(20,100);
            $table->cliente_id  = $faker->numberBetween(1,20);
            $table->usuario_id  = $faker->numberBetween(4,6);
            $table->sucursal_id = 1;

            for($j = 1; $j <= 2 ; $j++)
            {
                $table2 = new Detalle;

                $table2->producto_id  = $faker->numberBetween(1,20);
                $table2->estado       = $faker->randomElement(['Solicitada', 'En Proceso', 'Entregada']);
                $table2->cantidad     = $faker->numberBetween(1,3);
                $table2->precio       = $faker->numberBetween(1,20);
                $table2->tiempo      = $faker->numberBetween(5,30);
                $table2->costo        = $faker->numberBetween(1,20);
                $table2->descuento    = 0;
                $table2->total        = $faker->numberBetween(1,50);
                $table2->orden_id   = $i;
                $table2->save();
                
            }

            $table->total        = $table2->sum('total');
            $table->save();
            
        }


    }
     
}
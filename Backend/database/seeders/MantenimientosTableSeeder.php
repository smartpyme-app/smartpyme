<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transporte\Mantenimientos\Mantenimiento;
use App\Models\Transporte\Mantenimientos\Detalle;
use App\Models\Transporte\Flotas\Flota;
use App\Models\Inventario\Producto;
use Faker\Factory;
     
class MantenimientosTableSeeder extends Seeder {

    public function run()
    {

        $faker = Factory::create();

        for($i = 0; $i < 20 ; $i++)
        {
            $table = new Mantenimiento;

            $table->fecha       = $faker->date;
            $table->estado      = $faker->randomElement(['Completado', 'En Proceso', 'Programado']);
            $table->flota_id    = Flota::inRandomOrder()->first()->id;
            $table->tipo        = $faker->randomElement(['Preventivo', 'Correctivo', 'Otro']);
            $table->total       = $faker->numberBetween(100, 1000);
            $table->nota        = '';
            $table->bodega_id  = 1;
            $table->usuario_id  = 1;
            $table->sucursal_id = 1;
            $table->save();

            for ($j=0; $j < 3; $j++) { 
                $detalle = new Detalle;
                $detalle->producto_id = Producto::inRandomOrder()->first()->id;
                $detalle->cantidad    = $faker->numberBetween(1, 10);
                $detalle->costo       = $faker->numberBetween(10, 100);
                $detalle->total       = $detalle->cantidad * $detalle->costo;
                $detalle->mantenimiento_id = $i + 1;
                $detalle->save();
            }

   
            
        }
        
    }

     
}

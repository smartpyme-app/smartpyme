<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use Faker\Factory;
     
class ComprasTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = Factory::create();

        for($i = 1; $i <= 50 ; $i++)
        {
            $table = new Compra;

            $table->fecha       = $faker->dateTimeBetween($startDate = '-10 days', $endDate = 'now', $timezone = null);
            $table->estado      = $faker->randomElement(['Pagada', 'Pendiente']);
            $table->referencia  = $faker->numberBetween(1,200);
            $table->proveedor_id = $faker->numberBetween(1,50);
            $table->tipo    = 'Interna';
            $table->forma_pago   = 'Contado';
            $table->tipo_documento   = 'Factura';
            $table->metodo_pago    = 'Efectivo';
            // if ($table->estado == 1) {
            //     $table->fecha_pago = $faker->date;
            // }else{
            //     $table->fecha_pago = date('Y-m-d');
            // }
            $table->iva_retenido    = $faker->numberBetween(1,200);
            $table->descuento   = $faker->numberBetween(1,200);
            $table->iva = $faker->numberBetween(1,200);
            $table->subtotal    = $faker->numberBetween(1,200);
            $table->total   = $faker->numberBetween(1,200);

            $table->usuario_id  = 1;
            $table->empresa_id = $faker->numberBetween(1,2);
            $table->save();

            for($j = 1; $j <= $faker->numberBetween(1,5) ; $j++)
            {
                $table = new Detalle;

                $table->producto_id     = $faker->numberBetween(1,200);
                $table->cantidad        = $faker->numberBetween(1,20);
                $table->costo           = $faker->numberBetween(1,20);
                $table->descuento       = 0;
                $table->iva             = ($table->cantidad * $table->costo - $table->descuento) * 0.13;
                $table->subtotal    = $faker->numberBetween(1,200);
                $table->total   = $faker->numberBetween(1,200);
                $table->compra_id       = $i;
                
                $table->save();
                
            }
            
        }
    }
     
}
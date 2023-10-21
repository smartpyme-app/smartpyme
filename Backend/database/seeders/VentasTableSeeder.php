<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
     
class VentasTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();

        for($i = 1; $i <= 50 ; $i++)
        {
            $table = new Venta;

            $table->fecha           = $faker->dateTimeBetween($startDate = '-10 days', $endDate = '-1 days', $timezone = null);
            $table->correlativo     = $i;
            $table->canal           = $faker->randomElement(['Tienda','A Domicilio', 'Ruta']);
            $table->estado          = $faker->randomElement(['En Proceso','Pendiente', 'Pagada']);
            $table->metodo_pago     = $faker->randomElement(['Efectivo', 'Tarjeta']);
            $table->tipo_documento  = $faker->randomElement(['Factura', 'Crédito Fiscal', 'Ticket']);
            $table->referencia      = $faker->numberBetween(10000,50000);
            $table->iva_retenido    =  0;  
            $table->iva             = $faker->numberBetween(1,50);
            $table->subcosto        = $faker->numberBetween(50,150);
            $table->subtotal        = $faker->numberBetween(50,150);
            $table->nota            = $i;
            $table->total           = $faker->numberBetween(100,250);
            $table->caja_id         = 1;
            $table->corte_id        = 1;
            $table->cliente_id      = $faker->numberBetween(1,20);
            $table->usuario_id      = $faker->numberBetween(4,6);
            $table->sucursal_id     = $faker->numberBetween(1,2);
            $table->save();

            for($j = 1; $j <= $faker->numberBetween(1,5) ; $j++)
            {
                $detalle = new Detalle;

                $detalle->producto_id = $faker->numberBetween(1,100);
                $detalle->cantidad    = $faker->numberBetween(1,20);
                $detalle->precio      = $faker->numberBetween(1,20);
                $detalle->costo      = $faker->numberBetween(1,20);
                $detalle->descuento   = 0;
                $detalle->tipo_impuesto =  'Gravada';  
                $detalle->subcosto      = $detalle->costo * $detalle->cantidad;  
                $detalle->total         = $detalle->precio * $detalle->cantidad;  
                $detalle->subtotal      = $detalle->total / 1.13;  
                $detalle->iva           = $detalle->subtotal * 0.13;
                
                $detalle->venta_id    = $i;
                $detalle->save();
                
            }

            $table->iva             = $table->detalles()->sum('iva');
            $table->subcosto        = $table->detalles()->sum('subcosto');
            $table->subtotal        = $table->detalles()->sum('subtotal');
            $table->total           = $table->detalles()->sum('total');
            $table->save();
            
        }
    }
     
}

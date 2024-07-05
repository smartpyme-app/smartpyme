<?php

namespace Database\Seeders;

use App\Models\Contabilidad\Partidas\Detalle;
use Faker\Factory;
use Illuminate\Database\Seeder;

class PartidaDetalleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $faker = Factory::create();

        for($i = 0; $i <= 150 ; $i++){
            $table= new Detalle();
            $table->id_cuenta = $faker->numberBetween(1, 801);
            $table->codigo = $faker->randomElement(['440201', '5101', '21060101', '110101', '2201','420202']);
            $table->nombre_cuenta = $faker->randomElement(['Compras', 'Ventas', 'IVA DEBITO FISCAL', 'Prestamos Bancarios', '2201','420202']);
            $table->concepto = $faker->randomElement(['Venta', 'Caja', 'Iva Debito Fiscal']);
            $table->debe= $faker->randomFloat(2,200, 500);
            $table->haber= $faker->randomFloat(2,200, 500);
            $table->saldo= $faker->randomFloat(2,500, 1000);
            $table->id_partida = $faker->numberBetween(1,50);
            $table->save();
        }



    }
}

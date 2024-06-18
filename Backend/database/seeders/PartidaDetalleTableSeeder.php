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
            $table->id_cuenta = $faker->randomElement([5101, 110101, 21060102] );
            $table->id_partida = $faker->numberBetween(1,50);
            $table->concepto = $faker->randomElement(['Venta', 'Caja', 'Iva Debito Fiscal']);
            $table->abono= $faker->randomFloat(2,200, 500);
            $table->cargo= $faker->randomFloat(2,200, 500);
            $table->saldo= $faker->randomFloat(2,500, 1000);
            $table->save();
        }



    }
}

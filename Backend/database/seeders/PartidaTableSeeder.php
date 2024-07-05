<?php

namespace Database\Seeders;

use App\Models\Contabilidad\Partidas\Partida;
use Faker\Factory;
use Illuminate\Database\Seeder;

class PartidaTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Factory::create();

        for($i = 0; $i <= 50 ; $i++){
            $table = new Partida();
            $table->fecha = $faker->date();
            $table->tipo = $faker->randomElement(['Ingreso','Egreso','Diario','CxP','CxC','Cierre']);
            $table->concepto = $faker->text;
            $table->estado = $faker->randomElement(['Aprobado', 'Pendiente']);
            $table->id_usuario = $faker->numberBetween(1,4);
            $table->id_empresa = 13;
            $table->save();
        }
    }
}

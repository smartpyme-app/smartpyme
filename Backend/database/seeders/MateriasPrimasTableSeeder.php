<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transporte\Flete;
use App\Models\Inventario\Inventario;
use Faker\Factory;
     
class MateriasPrimasTableSeeder extends Seeder {

    public function run()
    {

        $faker = Factory::create();

        for($i = 0; $i < 50 - 1 ; $i++)
        {
            $table = new Flete;

            $table->propietario = $faker->name;
            $table->tipo        =  $faker->randomElement(['Cabezal', 'Remolque']);
            $table->num_chasis  = $faker->numberBetween(1000000, 999999999);
            $table->num_motor   = $faker->numberBetween(1000000, 999999999);
            $table->modelo      = $faker->name;
            $table->marca       = $faker->name;
            $table->color       = $faker->name;
            $table->precio      = $faker->numberBetween(10000, 99999);
            $table->tipo_combustible    = "Diesel";
            $table->ano = '';
            $table->placa   = '';
            $table->kilometraje = '';
            $table->img = '';
            $table->nota    = '';
            $table->empresa_id      = 1;
            $table->save();

   
            
        }
        
    }

     
}

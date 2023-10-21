<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ventas\Clientes\Cliente;
     
class ClientesTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();


        for($i = 0; $i < 20 - 1 ; $i++)
        {
            $table = new Cliente;

            // $table->nombre        = $clientes[$i]['nombre'];
            $table->nombre        = $faker->name;
            $table->nit           = $faker->unique()->ipv4;
            $table->dui           = $faker->unique()->ipv4;
            $table->registro      = $faker->unique()->ipv4;
            $table->municipio     = $faker->city;
            $table->departamento  = $faker->country;
            $table->direccion     = $faker->address;
            $table->sexo          = $faker->randomElement(['Hombre','Mujer']);
            $table->telefono      = $faker->phoneNumber;
            $table->correo        = $faker->email;
            $table->usuario_id    = 1;
            $table->empresa_id    = 1;
            $table->save();
           
        }

    }
     
}

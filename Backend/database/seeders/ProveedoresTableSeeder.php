<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Compras\Proveedores\Proveedor;
     
class ProveedoresTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();

        for($i = 0; $i < 20 ; $i++)
        {
            $table = new Proveedor;

            $table->nombre        = $faker->name;
            $table->nit           = $faker->unique()->ipv4;
            $table->dui           = $faker->unique()->ipv4;
            $table->registro      = $faker->unique()->ipv4;
            $table->municipio     = $faker->city;
            $table->departamento  = $faker->country;
            $table->giro          = $faker->name;
            $table->direccion     = $faker->address;
            $table->telefono      = $faker->phoneNumber;
            $table->correo        = $faker->email;
            $table->usuario_id    = 1;
            $table->empresa_id    = 1;
            $table->save();

            $table->save();
        }
        
    }
     
}
            

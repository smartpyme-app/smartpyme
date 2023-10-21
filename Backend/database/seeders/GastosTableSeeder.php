<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Compras\Gasto;
     
class GastosTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = \Faker\Factory::create();

        $categorias = [
            "Transporte",
            "Alimentación",
            "Viaticos",
            "Agua",
            "Luz",
            "Teléfono",
            "Internet",
            "Cable",
            "Material de oficina",
            "Material de limpieza",
            "Otros",
        ];

        for($i = 1; $i <= 100 ; $i++)
        {
            $table = new Gasto;

            $table->fecha       = $faker->dateTimeBetween($startDate = '-120 days', $endDate = 'now', $timezone = null);
            $table->descripcion = $faker->text(30);
            $table->categoria   = $faker->randomElement($categorias);
            $table->total       = $faker->numberBetween(20,100);
            $table->usuario_id  = $faker->numberBetween(4,6);
            $table->sucursal_id = 1;
            $table->save();
            
        }


    }
     
}

<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;
use Faker\Factory;

     
class CategoriasTableSeeder extends Seeder {
     
    public function run()
    {

        $faker = Factory::create();

        Categoria::create(['nombre' => 'Repuestos ', 'empresa_id' => 1]);
            SubCategoria::create(['nombre' => 'Nuevos', 'categoria_id' => 1]);
            SubCategoria::create(['nombre' => 'Usados', 'categoria_id' => 1]);

        Categoria::create(['nombre' => 'Cemento', 'empresa_id' => 1]);
            SubCategoria::create(['nombre' => 'Cemento', 'categoria_id' => 2]);

        Categoria::create(['nombre' => 'Servicios ', 'empresa_id' => 1]);
            SubCategoria::create(['nombre' => 'Fletes', 'categoria_id' => 3]);

        Categoria::create(['nombre' => 'Mantenimiento', 'empresa_id' => 1]);
            SubCategoria::create(['nombre' => 'Mano de obra', 'categoria_id' => 4]);



    }
     
}

<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Sucursal;
     
class ProductosTableSeeder extends Seeder {

    public function run()
    {

        $faker = \Faker\Factory::create();

        Producto::create([
            'nombre'            => 'Flete',
            'precio'            => 50,
            'costo'             => 100,
            'categoria_id'      => 3,
            'subcategoria_id'   => 4,
            'tipo'              => "Servicio",
            'tipo_impuesto'     => "Gravada",
            'impuesto'          => 0.13,
            'empresa_id'        => 1,
        ]);

        Sucursal::create(['producto_id' => 1, 'activo' => true, 'sucursal_id' => 1 ]);

        Producto::create([
            'nombre'            => 'Mano de obra',
            'precio'            => 50,
            'costo'             => 100,
            'categoria_id'      => 4,
            'subcategoria_id'   => 5,
            'tipo'              => "Servicio",
            'tipo_impuesto'     => "Gravada",
            'impuesto'          => 0.13,
            'empresa_id'        => 1,
        ]);

        Sucursal::create(['producto_id' => 2, 'activo' => true, 'sucursal_id' => 1 ]);

        for ($i=0; $i < 130; $i++) { 
            Producto::create([
                'nombre'            => $faker->name,
                'precio'            => $faker->numberBetween(50,100),
                'costo'             => $faker->numberBetween(50,100),
                'categoria_id'      => 2,
                'subcategoria_id'   => 3,
                'tipo'              => "Producto",
                'tipo_impuesto'     => "Gravada",
                'impuesto'          => 0.13,
                'empresa_id'        => 1,
            ]);

            Sucursal::create(['producto_id' => $i + 3, 'activo' => true, 'sucursal_id' => 1 ]);

        }

        for ($i=0; $i < 130; $i++) { 
            Producto::create([
                'nombre'            => $faker->name,
                'precio'            => $faker->numberBetween(50,100),
                'costo'             => $faker->numberBetween(50,100),
                'categoria_id'      => 1,
                'subcategoria_id'   => $faker->numberBetween(1,2),
                'tipo'              => "Repuesto",
                'tipo_impuesto'     => "Gravada",
                'impuesto'          => 0.13,
                'empresa_id'        => 1,
            ]);

            Sucursal::create(['producto_id' => $i + 133, 'activo' => true, 'sucursal_id' => 1 ]);

        }


    }

     
}

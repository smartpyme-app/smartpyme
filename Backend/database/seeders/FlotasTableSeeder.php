<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transporte\Flotas\Flota;
     
class FlotasTableSeeder extends Seeder {

    public function run()
    {

        $faker = \Faker\Factory::create();

        for ($i=0; $i < 20; $i++) { 
            Flota::create([
                'img'             => 'flotas/default.jpg',
                'propietario'     => 'Orlando espinoza',
                'placa'           => 'R-'.$faker->numberBetween(12345,54321),
                'vin'             => $faker->ipv4,
                'num_chasis'      => $faker->numberBetween(12345,54321),
                'num_motor'       => $faker->numberBetween(12345,54321),
                'modelo'          => $faker->randomElement(['Honda', 'Mazda']),
                'marca'           => $faker->randomElement(['Civic', 'Acura']),
                'capacidad'       => '5 toneladas',
                'anio'            => $faker->year,
                'vencimiento_tarjeta' => $faker->date,
                'vencimiento_garantia' => $faker->date,
                'vencimiento_poliza' => $faker->date,
                'ultimo_mantenimiento' => $faker->dateTimeBetween($startDate = '-1 years', $endDate = '-3 months', $timezone = null),
                'proximo_mantenimiento' => $faker->dateTimeBetween($startDate = '-3 months', $endDate = '+6 months', $timezone = null),
                'color'           => 'Negro',
                'tipo_combustible' => 'Diesel',
                'tipo'            => $faker->randomElement(['Cabezal', 'Remolque']),
                'usuario_id'      => 1,
                'sucursal_id'      => 1,

            ]);
        }


    }

     
}

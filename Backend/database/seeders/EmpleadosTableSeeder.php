<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empleados\Empleados\Empleado;
use Faker\Factory;
     
class EmpleadosTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = Factory::create();


        for($i = 0; $i <= 5 ; $i++)
        {
            $table = new Empleado;


            $table->nombre      = $faker->name;
            $table->fecha_nacimiento = $faker->date;
            $table->genero      = $faker->randomElement(['Hombre','Mujer']);
            $table->dui         = $faker->unique()->ipv4;
            $table->telefono    = $faker->phoneNumber;
            $table->correo      = $faker->email;
            $table->municipio   = $faker->city;
            $table->departamento = $faker->country;
            $table->direccion   = $faker->address;
            $table->pais        = 'El Salvador';
            $table->nacionalidad = 'Salvadoreño';
            $table->activo      = 1;
            $table->num_licencia = $faker->unique()->ipv4;
            $table->fecha_vencimiento = $faker->date;
            $table->tipo_licencia = $faker->randomElement(['Pesada T','Pesada']);
            $table->cargo_id      = $faker->numberBetween(1, 3);
            $table->fecha_inicio = $faker->date;
            $table->estado      = $faker->randomElement(['Tiempo completo','Temporal']);
            $table->sueldo      = $faker->numberBetween(500, 1000);
            $table->tipo_salario = 'Mensual';
            $table->renta       = 1;
            $table->isss        = 1;
            $table->afp         = 1;
            $table->contacto_nombre = $faker->name;
            $table->contacto_telefono = $faker->phoneNumber;
            $table->nota        = '';
            $table->sucursal_id = 1;
            $table->save();
           
        }

    }
     
}

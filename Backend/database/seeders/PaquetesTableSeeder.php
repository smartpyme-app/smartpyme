<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\User;
use Faker\Factory;
     
class PaquetesTableSeeder extends Seeder {
     
    public function run()
    {
        $faker = Factory::create();


        for($i = 0; $i <= 20 ; $i++)
        {
            $table = new Paquete;

            $table->fecha           = $faker->dateTimeBetween($startDate = '-1 years', $endDate = 'now', $timezone = null);
            $table->wr              = $faker->numberBetween(50000, 59999);
            $table->transportista   = 'AMAZON FULFILLMENT';
            $table->consignatario   = $faker->name;
            $table->transportador   = 'AMAZON';
            $table->estado          = $faker->randomElement(['En bodega','En proceso','Entregado']);
            $table->num_seguimiento = $faker->unique()->ipv4;
            $table->num_guia        = $faker->unique()->ipv4;
            $table->piezas          = $faker->numberBetween(1, 10);
            $table->peso            = $faker->numberBetween(50, 100);
            $table->precio          = 4.99;
            $table->volumen         = $faker->numberBetween(50, 100);
            $table->id_cliente      = Cliente::inRandomOrder()->first()->id;
            $table->id_asesor       = 195;
            $table->id_usuario      = User::inRandomOrder()->first()->id;
            $table->id_sucursal     = 8;
            $table->id_empresa      = 13;
            $table->save();
           
        }

    }
     
}

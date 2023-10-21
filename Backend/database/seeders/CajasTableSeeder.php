<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Caja;
use App\Models\Admin\Corte;
use App\Models\Admin\Documento;
     
class CajasTableSeeder extends Seeder {
     
    public function run()
    {

        Caja::create(['nombre' => 'Principal', 'tipo' => 'Sucursal 1', 'descripcion' => '', 'sucursal_id' => 1]);
            Documento::create(['nombre'=> 'Credito Fiscal', 'inicial' => 1, 'actual' => 1, 'final' => 100, 'caja_id'=> 1, ]);
            Documento::create(['nombre'=> 'Factura', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 1, ]);
            Documento::create(['nombre'=> 'Ticket', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 1, ]);

        // Corte::create(['fecha' => date('Y-m-d'), 'saldo_inicial' => 0, 'saldo_final' => 0, 'apertura' => date('Y-m-d'), 'caja_id' => 1, 'usuario_id' => 4]);
    }
     
}
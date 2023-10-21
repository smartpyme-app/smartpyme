<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Admin\Caja;
use App\Models\Admin\Documento;
use App\Models\Admin\Canal;
use App\Models\Empleados\Cargo;
use App\Models\Empleados\Empleado;
use App\Models\Inventario\Bodega;

use App\Models\Compras\Gastos\Categoria;

class EmpresaTableSeeder extends Seeder {
    
    public function run(){


        Empresa::create(['nombre' => 'Nombre Negocio', 'telefono' => '0000-0000', 'correo' => 'ejemplo@ejemplo.com', 'registro' => '1234']);
        Sucursal::create(['nombre' => 'Sucursal 1', 'telefono' => '0000-0000', 'correo' => 'ejemplo@ejemplo.com', 'direccion' => 'Lot Buenos Aire', 'empresa_id' => 1]);
            Bodega::create(['nombre' => 'Bodega 1', 'sucursal_id' => 1]);

        Caja::create(['nombre' => 'Principal', 'tipo' => 'Sucursal 1', 'descripcion' => '', 'sucursal_id' => 1]);
            Documento::create(['nombre'=> 'Crédito Fiscal', 'inicial' => 1, 'actual' => 1, 'final' => 100, 'caja_id' => 1, ]);
            Documento::create(['nombre'=> 'Factura', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 1, ]);
            // Documento::create(['nombre'=> 'Exportación', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 1, ]);
            Documento::create(['nombre'=> 'Ticket', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 1, ]);

        Sucursal::create(['nombre' => 'Sucursal 2', 'telefono' => '0000-0000', 'correo' => 'ejemplo@ejemplo.com', 'direccion' => 'Lot Buenos Aire', 'empresa_id' => 1]);
            Bodega::create(['nombre' => 'Bodega 2', 'sucursal_id' => 2]);

        Caja::create(['nombre' => 'Principal', 'tipo' => 'Sucursal 2', 'descripcion' => '', 'sucursal_id' => 2]);
            Documento::create(['nombre'=> 'Crédito Fiscal', 'inicial' => 1, 'actual' => 1, 'final' => 100, 'caja_id' => 2, ]);
            Documento::create(['nombre'=> 'Factura', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 2, ]);
            // Documento::create(['nombre'=> 'Exportación', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 2, ]);
            Documento::create(['nombre'=> 'Ticket', 'inicial' => 1, 'actual'=> 1, 'final'=> 100, 'caja_id' => 2, ]);
        
        Canal::create(['nombre'=> 'Tienda', 'empresa_id' => 1, ]);
        Canal::create(['nombre'=> 'A Domicilio', 'empresa_id' => 1, ]);
        
        Cargo::create(['nombre' => 'Gerente', 'empresa_id' => 1]);
        Cargo::create(['nombre' => 'Vendedor', 'empresa_id' => 2]);
        Cargo::create(['nombre' => 'Cajera', 'empresa_id' => 3]);

        $categorias = [
            "Alquiler",
            "Depreciaciones",
            "Formación",
            "Gastos varios",
            "Insumos",
            "Impuestos",
            "Mantenimiento",
            "Marketing",
            "Materia Prima",
            "Pago comisión",
            "Planilla",
            "Publicidad",
            "Préstamos",
            "Seguros",
            "Servicios",
        ];

        for ($i=0; $i < count($categorias); $i++) { 
            Categoria::create(['nombre' => $categorias[$i], 'empresa_id' => 1]);
        }

    }

}

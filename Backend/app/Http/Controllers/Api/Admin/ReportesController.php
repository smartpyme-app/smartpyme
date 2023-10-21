<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use JWTAuth;
use NumeroALetras;
use App\Models\Admin\Empresa;
use App\Models\Admin\Asistencia;
use App\Models\Admin\Empleados\Empleado;
use App\Models\Admin\Corte;
use App\Models\Ventas\Venta;
use App\Models\Ventas\DevolucionVenta;
use App\Models\Ventas\Detalle;
use App\Models\Inventario\Requisicion;

use App\Models\Admin\Empleados\Planillas\Planilla;

class ReportesController extends Controller
{
    
    


    public function requisicionCompra(Request $request) {

        $requisicion = $request;
        $requisicion = Requisicion::where('id', 1)->with('detalles')->first();
        $empresa = Empresa::find(1);

        // return $requisicion;

        if ($requisicion) {
            $reportes = \PDF::loadView('reportes.requisicion', compact('requisicion', 'empresa'));
            return $reportes->download();
        }else{
            return "No entontrado";
        }

    }


    

    




}

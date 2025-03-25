<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Imports\VentasExcelImport;
use App\Models\Admin\Documento;
use App\Models\Inventario\Inventario;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;


class VentasImportController extends Controller
{



    public function importar(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');

        try {
            // Intentar importar usando nuestro servicio personalizado
            $import = new VentasExcelImport();
            Excel::import($import, $file);

            // Obtener el contador de ventas procesadas
            $contador = $import->getContador();

            if ($contador > 0) {
                return response()->json($contador, 200);
            } else {
                return response()->json(['error' => 'No se procesaron ventas. Verifique el formato del archivo.'], 400);
            }
        } catch (\Exception $e) {
            // Si hay un error, devolver mensaje
            return response()->json(['error' => 'Error al importar: ' . $e->getMessage()], 400);
        }
    }
}

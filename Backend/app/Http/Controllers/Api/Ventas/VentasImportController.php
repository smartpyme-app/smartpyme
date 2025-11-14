<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Imports\VentasExcelImport;
use App\Exports\VentasPlantillaExport;
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
            $import = new VentasExcelImport();
            Excel::import($import, $file);

            $ventasExitosas = $import->getContador();
            $errores = $import->getErrores();

            if ($ventasExitosas > 0 && count($errores) > 0) {
                $mensajeExito = "Se procesaron correctamente {$ventasExitosas} ventas.";

                $productosNoEncontrados = [];
                foreach ($errores as $error) {
                    if (strpos($error, 'Producto no encontrado:') !== false) {
                        preg_match('/Producto no encontrado: (.+)/', $error, $matches);
                        if (isset($matches[1])) {
                            $productosNoEncontrados[] = $matches[1];
                        }
                    }
                }

                $mensajeFalla = "No se pudieron procesar " . count($errores) . " ventas debido a que no se encontraron los siguientes productos: " . implode(", ", array_unique($productosNoEncontrados));

                return response()->json([
                    'message' => $mensajeExito . " " . $mensajeFalla,
                    'procesadas' => $ventasExitosas,
                    'fallidas' => count($errores),
                    'productos_faltantes' => array_unique($productosNoEncontrados)
                ], 200);
            } else if ($ventasExitosas > 0) {
                return response()->json([
                    'message' => "¡Importación completada con éxito! Se procesaron {$ventasExitosas} ventas correctamente.",
                    'procesadas' => $ventasExitosas,
                    'fallidas' => 0
                ], 200);
            } else {
                return response()->json(['error' => 'No se pudo procesar ninguna venta. ' . implode("\n", $errores)], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al importar: ' . $e->getMessage()], 400);
        }
    }

    public function downloadPlantilla()
    {
        $export = new VentasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_ventas.xlsx');
    }
}

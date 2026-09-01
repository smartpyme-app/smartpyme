<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Imports\VentasExcelImport;
use App\Exports\VentasPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Ventas\Import\ImportVentasRequest;


class VentasImportController extends Controller
{
    public function importar(ImportVentasRequest $request)
    {

        $file = $request->file('file');

        $import = new VentasExcelImport();

        try {
            Excel::import($import, $file);

            $ventasExitosas = $import->getContador();
            $errores = $import->getErrores();

            if ($errores !== []) {
                return response()->json(self::payloadErrores($errores), 422);
            }

            if ($ventasExitosas < 1) {
                return response()->json(self::payloadErrores([
                    ['fila' => 0, 'columna' => 'archivo', 'mensaje' => 'No se encontraron ventas válidas para importar.'],
                ]), 422);
            }

            return response()->json([
                'message' => "Se importaron {$ventasExitosas} ventas correctamente.",
                'procesadas' => $ventasExitosas,
                'errores' => [],
            ], 200);
        } catch (\Exception $e) {
            $errores = $import->getErrores();
            if ($errores !== []) {
                return response()->json(self::payloadErrores($errores), 422);
            }

            return response()->json(self::payloadErrores([
                ['fila' => 0, 'columna' => 'archivo', 'mensaje' => $e->getMessage()],
            ]), 400);
        }
    }

    /**
     * @param  list<array{fila:int,columna:string,mensaje:string}>  $errores
     * @return array{message:string,procesadas:int,errores:list<array{fila:int,columna:string,mensaje:string}>}
     */
    public static function payloadErrores(array $errores): array
    {
        $n = count($errores);
        $message = $n === 1
            ? 'No se importó ninguna venta. Hay 1 error.'
            : "No se importó ninguna venta. Hay {$n} errores.";

        return [
            'message' => $message,
            'procesadas' => 0,
            'errores' => $errores,
        ];
    }

    public function downloadPlantilla()
    {
        $export = new VentasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_ventas.xlsx');
    }
}

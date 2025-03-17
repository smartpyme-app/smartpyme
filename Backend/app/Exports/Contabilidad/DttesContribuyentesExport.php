<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class DttesContribuyentesExport
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function generateZip()
    {
        $request = $this->request;

        // Obtener las fechas del filtro para el nombre del archivo
        $fechaInicio = $request->inicio ?? date('Y-m-d');
        $fechaFin = $request->fin ?? date('Y-m-d');

        // Formatear las fechas para el nombre del archivo
        $fechaInicioFormateada = date('Ymd', strtotime($fechaInicio));
        $fechaFinFormateada = date('Ymd', strtotime($fechaFin));

        // Crear el nombre del archivo con las fechas
        $nombreArchivo = '';
        if ($fechaInicio == $fechaFin) {
            // Si es un solo día
            $nombreArchivo = 'DTEs_' . $fechaInicioFormateada;
        } else {
            // Si es un rango de fechas
            $nombreArchivo = 'DTEs_' . $fechaInicioFormateada . '_' . $fechaFinFormateada;
        }

        $ventas = Venta::with(['cliente', 'documento'])
            ->whereRaw("dte IS NOT NULL")
            ->whereNotNull('sello_mh')
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->has('inicio') && $request->has('fin'), function ($query) use ($request) {
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->orderByDesc('fecha');

        $ventas = $ventas->get();

        if ($ventas->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No se encontraron DTEs que cumplan con los criterios de búsqueda.'
            ];
        }

        $tempDir = storage_path('app/temp/dtes_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Usar el nombre creado con las fechas
        $zipFileName = $nombreArchivo . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP.'
            ];
        }

        // Resto del código sin cambios...
        $countDtes = 0;
        foreach ($ventas as $venta) {

            if (empty($venta->dte)) {
                Log::info('DTE vacío para venta ID: ' . $venta->id);
                continue;
            }

            $dte = $venta->dte;

            if (isset($dte['identificacion']) && isset($dte['identificacion']['codigoGeneracion'])) {
                $codigoGeneracion = $dte['identificacion']['codigoGeneracion'];
            } elseif (isset($dte['codigoGeneracion'])) {
                $codigoGeneracion = $dte['codigoGeneracion'];
            } else {
                Log::info('No se encontró codigoGeneracion para venta ID: ' . $venta->id);
                $codigoGeneracion = '';
            }

            if (empty($codigoGeneracion)) {
                continue;
            }

            Log::info('Procesando DTE con codigoGeneracion: ' . $codigoGeneracion);

            $fileName = $codigoGeneracion . '.json';
            $filePath = $tempDir . '/' . $fileName;

            file_put_contents($filePath, json_encode($dte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $zip->addFile($filePath, $fileName);
            $countDtes++;
        }

        $zip->close();

        // Eliminar archivos temporales
        foreach (glob($tempDir . '/*.json') as $file) {
            unlink($file);
        }
        rmdir($tempDir);

        return [
            'success' => true,
            'path' => 'public/' . $zipFileName,
            'filename' => $zipFileName,
            'count' => $countDtes,
            'message' => 'Se exportaron ' . $countDtes . ' DTEs correctamente.'
        ];
    }
}

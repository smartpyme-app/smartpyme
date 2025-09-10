<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class GlobalDttesExport
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function generateZip()
    {
        $request = $this->request;

        Log::info($request);

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
            ->where('tipo_dte', $request->typeDTE)
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

        $countDtes = 0;
        $tempFiles = [];
        
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

            $fileContent = json_encode($dte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if ($zip->addFromString($fileName, $fileContent)) {
                $countDtes++;
                $tempFiles[] = $filePath;
            } else {
                Log::error('Error al agregar archivo al ZIP: ' . $fileName);
            }
        }

        // Cerrar el ZIP antes de limpiar archivos temporales
        if (!$zip->close()) {
            return [
                'success' => false,
                'message' => 'Error al cerrar el archivo ZIP.'
            ];
        }

        // Eliminar archivos temporales después de cerrar el ZIP
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Eliminar directorio temporal
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }

        return [
            'success' => true,
            'path' => 'public/' . $zipFileName,
            'filename' => $zipFileName,
            'count' => $countDtes,
            'message' => 'Se exportaron ' . $countDtes . ' DTEs correctamente.'
        ];
    }
}

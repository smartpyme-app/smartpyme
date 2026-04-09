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

        // Obtener las fechas del filtro para el nombre del archivo
        $fechaInicio = $request->inicio ?? date('Y-m-d');
        $fechaFin = $request->fin ?? date('Y-m-d');

        // Formatear las fechas para el nombre del archivo
        $fechaInicioFormateada = date('Ymd', strtotime($fechaInicio));
        $fechaFinFormateada = date('Ymd', strtotime($fechaFin));

        // Crear el nombre del archivo con las fechas
        $estadoJson = $request->input('estado_json', 'no_anulados');
        if (!in_array($estadoJson, ['no_anulados', 'anulados'], true)) {
            $estadoJson = 'no_anulados';
        }

        $prefijoNombre = $estadoJson === 'anulados' ? 'DTEs_anulados_' : 'DTEs_';
        $nombreArchivo = '';
        if ($fechaInicio == $fechaFin) {
            $nombreArchivo = $prefijoNombre . $fechaInicioFormateada;
        } else {
            $nombreArchivo = $prefijoNombre . $fechaInicioFormateada . '_' . $fechaFinFormateada;
        }

        $ventas = Venta::with(['cliente', 'documento'])
            ->whereRaw("dte IS NOT NULL")
            ->whereNotNull('sello_mh')
            ->when($estadoJson === 'anulados', function ($query) {
                return $query->where('estado', 'Anulada');
            }, function ($query) {
                return $query->where('estado', '!=', 'Anulada');
            })
            ->where('cotizacion', 0)
            ->where('tipo_dte', $request->typeDTE)
            ->when($request->filled('id_sucursal'), function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
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
            $mkdirResult = mkdir($tempDir, 0777, true);
            Log::info('Resultado de mkdir: ' . ($mkdirResult ? 'ÉXITO' : 'FALLO'));
            if (!$mkdirResult) {
                Log::error('Error al crear directorio temporal: ' . $tempDir);
                return [
                    'success' => false,
                    'message' => 'No se pudo crear el directorio temporal.'
                ];
            }
        }

        // Usar el nombre creado con las fechas
        $zipFileName = $nombreArchivo . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);
        
        $zip = new ZipArchive();
        $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($zipResult !== true) {
            $errorMessages = [
                ZipArchive::ER_OK => 'No hay error',
                ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
                ZipArchive::ER_RENAME => 'Renaming temporary file failed',
                ZipArchive::ER_CLOSE => 'Closing zip archive failed',
                ZipArchive::ER_SEEK => 'Seek error',
                ZipArchive::ER_READ => 'Read error',
                ZipArchive::ER_WRITE => 'Write error',
                ZipArchive::ER_CRC => 'CRC error',
                ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
                ZipArchive::ER_NOENT => 'No such file',
                ZipArchive::ER_EXISTS => 'File already exists',
                ZipArchive::ER_OPEN => 'Can\'t open file',
                ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
                ZipArchive::ER_ZLIB => 'Zlib error',
                ZipArchive::ER_MEMORY => 'Memory allocation failure',
                ZipArchive::ER_CHANGED => 'Entry has been changed',
                ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
                ZipArchive::ER_EOF => 'Premature EOF',
                ZipArchive::ER_INVAL => 'Invalid argument',
                ZipArchive::ER_NOZIP => 'Not a zip archive',
                ZipArchive::ER_INTERNAL => 'Internal error',
                ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                ZipArchive::ER_REMOVE => 'Can\'t remove file',
                ZipArchive::ER_DELETED => 'Entry has been deleted'
            ];
            
            $errorMessage = $errorMessages[$zipResult] ?? 'Error desconocido: ' . $zipResult;
            Log::error('Error al crear ZIP: ' . $errorMessage);
            
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP. Error: ' . $errorMessage
            ];
        }

        $countDtes = 0;
        $tempFiles = [];
        
        foreach ($ventas as $venta) {

            $dte = ($estadoJson === 'anulados' && !empty($venta->dte_invalidacion))
                ? $venta->dte_invalidacion
                : $venta->dte;

            if (empty($dte)) {
                Log::info('DTE vacío para venta ID: ' . $venta->id);
                continue;
            }

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
        Log::info('Cerrando archivo ZIP...');
        $closeResult = $zip->close();
        Log::info('Resultado de ZIP::close: ' . ($closeResult ? 'ÉXITO' : 'FALLO'));
        
        if (!$closeResult) {
            Log::error('Error al cerrar el archivo ZIP');
            return [
                'success' => false,
                'message' => 'Error al cerrar el archivo ZIP.'
            ];
        }

        // Verificar que el archivo ZIP se creó correctamente
        Log::info('Verificando archivo ZIP creado...');
        Log::info('Archivo existe: ' . (file_exists($zipPath) ? 'SÍ' : 'NO'));
        Log::info('Tamaño del archivo: ' . (file_exists($zipPath) ? filesize($zipPath) . ' bytes' : 'N/A'));
        
        if (file_exists($zipPath)) {
            // Verificar integridad del ZIP
            $testZip = new ZipArchive();
            $testResult = $testZip->open($zipPath);
            Log::info('Test de integridad ZIP: ' . ($testResult === true ? 'VÁLIDO' : 'INVÁLIDO - Código: ' . $testResult));
            if ($testResult === true) {
                Log::info('Número de archivos en ZIP: ' . $testZip->numFiles);
                $testZip->close();
            }
        }

        // Eliminar archivos temporales después de cerrar el ZIP
        Log::info('Limpiando archivos temporales...');
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Eliminar directorio temporal
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }

        Log::info('=== FIN EXPORTACIÓN DTEs ===');
        Log::info('DTEs procesados: ' . $countDtes);
        Log::info('Archivo generado: ' . $zipFileName);

        return [
            'success' => true,
            'path' => 'public/' . $zipFileName,
            'filename' => $zipFileName,
            'count' => $countDtes,
            'message' => 'Se exportaron ' . $countDtes . ' DTEs correctamente.'
        ];
    }
}

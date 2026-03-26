<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Devoluciones\Devolucion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class NotasCreditoDebitoExport
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function generateZip()
    {
        $request = $this->request;

        $fechaInicio = $request->inicio ?? date('Y-m-d');
        $fechaFin = $request->fin ?? date('Y-m-d');
        $fechaInicioFormateada = date('Ymd', strtotime($fechaInicio));
        $fechaFinFormateada = date('Ymd', strtotime($fechaFin));

        $tipoNota = $request->tipo_nota ?? null;
        if (!in_array($tipoNota, ['05', '06'], true)) {
            return [
                'success' => false,
                'message' => 'Parámetro tipo_nota inválido. Use 05 (notas de crédito) o 06 (notas de débito).'
            ];
        }

        $esCredito = ($tipoNota === '05');
        $nombreTipo = $esCredito ? 'NotasCredito' : 'NotasDebito';
        $nombreArchivo = ($fechaInicio == $fechaFin)
            ? $nombreTipo . '_' . $fechaInicioFormateada
            : $nombreTipo . '_' . $fechaInicioFormateada . '_' . $fechaFinFormateada;

        $mensajeVacio = $esCredito
            ? 'No se encontraron notas de crédito que cumplan con los criterios de búsqueda.'
            : 'No se encontraron notas de débito que cumplan con los criterios de búsqueda.';

        $devoluciones = Devolucion::with(['cliente', 'venta'])
            ->whereRaw('dte IS NOT NULL')
            ->whereNotNull('sello_mh')
            ->where('enable', true)
            ->where('tipo_dte', $tipoNota)
            ->whereHas('venta', function ($query) {
                $query->where('estado', '!=', 'Anulada');
            })
            ->when($request->has('inicio') && $request->has('fin'), function ($query) use ($request) {
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->when(!empty($request->id_sucursal), function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->orderByDesc('fecha')
            ->get();

        if ($devoluciones->isEmpty()) {
            return [
                'success' => false,
                'message' => $mensajeVacio
            ];
        }

        $tempDir = storage_path('app/temp/notas_' . time());
        if (!file_exists($tempDir)) {
            if (!mkdir($tempDir, 0777, true)) {
                Log::error('Error al crear directorio temporal: ' . $tempDir);
                return [
                    'success' => false,
                    'message' => 'No se pudo crear el directorio temporal.'
                ];
            }
        }

        $zipFileName = $nombreArchivo . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);
        $zip = new ZipArchive();
        $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($zipResult !== true) {
            $errorMessages = [
                ZipArchive::ER_OPEN => 'No se pudo abrir el archivo',
                ZipArchive::ER_EXISTS => 'El archivo ya existe',
                ZipArchive::ER_MEMORY => 'Error de memoria',
            ];
            $errorMessage = $errorMessages[$zipResult] ?? 'Error desconocido: ' . $zipResult;
            Log::error('Error al crear ZIP notas: ' . $errorMessage);
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP. ' . $errorMessage
            ];
        }

        $countDtes = 0;
        $tempFiles = [];

        foreach ($devoluciones as $devolucion) {
            if (empty($devolucion->dte)) {
                continue;
            }
            $dte = $devolucion->dte;
            $codigoGeneracion = $dte['identificacion']['codigoGeneracion']
                ?? $dte['codigoGeneracion']
                ?? $devolucion->codigo_generacion
                ?? '';
            if (empty($codigoGeneracion)) {
                continue;
            }
            $fileName = $codigoGeneracion . '.json';
            $fileContent = json_encode($dte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($zip->addFromString($fileName, $fileContent)) {
                $countDtes++;
                $tempFiles[] = $tempDir . '/' . $fileName;
            }
        }

        $closeResult = $zip->close();
        if (!$closeResult) {
            return [
                'success' => false,
                'message' => 'Error al cerrar el archivo ZIP.'
            ];
        }

        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }

        return [
            'success' => true,
            'path' => 'public/' . $zipFileName,
            'filename' => $zipFileName,
            'count' => $countDtes,
            'message' => 'Se exportaron ' . $countDtes . ' ' . ($esCredito ? 'notas de crédito' : 'notas de débito') . ' correctamente.'
        ];
    }
}

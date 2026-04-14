<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use App\Services\DteVentaPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GlobalDttesPdfExport
{
    protected $request;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function generateZip(): array
    {
        $request = $this->request;

        $fechaInicio = $request->inicio ?? date('Y-m-d');
        $fechaFin = $request->fin ?? date('Y-m-d');
        $fechaInicioFormateada = date('Ymd', strtotime($fechaInicio));
        $fechaFinFormateada = date('Ymd', strtotime($fechaFin));

        $estadoJson = $request->input('estado_json', 'no_anulados');
        if (!in_array($estadoJson, ['no_anulados', 'anulados'], true)) {
            $estadoJson = 'no_anulados';
        }

        $prefijoNombre = $estadoJson === 'anulados' ? 'DTEs_PDF_anulados_' : 'DTEs_PDF_';
        if ($fechaInicio == $fechaFin) {
            $nombreArchivo = $prefijoNombre . $fechaInicioFormateada;
        } else {
            $nombreArchivo = $prefijoNombre . $fechaInicioFormateada . '_' . $fechaFinFormateada;
        }

        $ventas = Venta::with(['cliente', 'documento', 'detalles.producto'])
            ->whereRaw('dte IS NOT NULL')
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
            ->orderByDesc('fecha')
            ->get();

        if ($ventas->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No se encontraron DTEs que cumplan con los criterios de búsqueda.',
            ];
        }

        $tempDir = storage_path('app/temp/dtes_pdf_' . time());
        if (!file_exists($tempDir)) {
            if (!mkdir($tempDir, 0777, true)) {
                Log::error('GlobalDttesPdfExport: no se pudo crear ' . $tempDir);

                return [
                    'success' => false,
                    'message' => 'No se pudo crear el directorio temporal.',
                ];
            }
        }

        $zipFileName = $nombreArchivo . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        $zip = new ZipArchive();
        $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($zipResult !== true) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP.',
            ];
        }

        $countPdfs = 0;

        foreach ($ventas as $venta) {
            $dte = ($estadoJson === 'anulados' && !empty($venta->dte_invalidacion))
                ? $venta->dte_invalidacion
                : $venta->dte;
            if (is_string($dte)) {
                $dte = json_decode($dte, true);
            }
            if (empty($dte) || !is_array($dte)) {
                continue;
            }
            if (isset($dte['identificacion']['codigoGeneracion'])) {
                $codigoGeneracion = $dte['identificacion']['codigoGeneracion'];
            } elseif (isset($dte['codigoGeneracion'])) {
                $codigoGeneracion = $dte['codigoGeneracion'];
            } else {
                continue;
            }
            if ($codigoGeneracion === '') {
                continue;
            }

            $binary = DteVentaPdfService::renderPdfBinary($venta);
            if ($binary === null || $binary === '') {
                Log::info('GlobalDttesPdfExport: PDF omitido venta ID ' . $venta->id);

                continue;
            }

            $fileName = $codigoGeneracion . '.pdf';
            if ($zip->addFromString($fileName, $binary)) {
                $countPdfs++;
            }
        }

        $closeResult = $zip->close();
        if (!$closeResult) {
            @unlink($zipPath);

            return [
                'success' => false,
                'message' => 'Error al cerrar el archivo ZIP.',
            ];
        }

        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }

        if ($countPdfs === 0) {
            @unlink($zipPath);

            return [
                'success' => false,
                'message' => 'No se pudo generar ningún PDF para los DTEs del periodo.',
            ];
        }

        return [
            'success' => true,
            'path' => 'public/' . $zipFileName,
            'filename' => $zipFileName,
            'count' => $countPdfs,
            'message' => 'Se exportaron ' . $countPdfs . ' PDFs correctamente.',
        ];
    }
}

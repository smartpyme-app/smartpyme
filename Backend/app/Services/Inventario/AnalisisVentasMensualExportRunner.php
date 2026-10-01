<?php

namespace App\Services\Inventario;

use App\Exports\Inventario\InventarioVentasMensualAnalisisReport;
use App\Exports\Inventario\InventarioVentasMensualAnalisisWorkbookExport;
use App\Mail\AnalisisVentasMensualErrorMail;
use App\Mail\AnalisisVentasMensualMail;
use App\Models\Admin\Empresa;
use App\Models\Inventario\AnalisisVentasMensualQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class AnalisisVentasMensualExportRunner
{
    public function processQueueItem(AnalisisVentasMensualQueue $item): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');

        $item->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $empresa = Empresa::find($item->id_empresa);
            if (!$empresa) {
                throw new \RuntimeException('Empresa no encontrada.');
            }

            $params = is_array($item->params) ? $item->params : [];
            $params['id_empresa'] = $item->id_empresa;
            $request = Request::create('/', 'GET', $params);

            $report = new InventarioVentasMensualAnalisisReport($empresa, $request);
            $export = new InventarioVentasMensualAnalisisWorkbookExport($report->buildSheets());

            $anio = $params['anio'] ?? date('Y');
            $fileName = 'reporte-inventario-ventas-' . $anio . '_' . date('Ymd_His') . '.xlsx';
            $relativeDir = 'temp/analisis-ventas';
            $relativePath = $relativeDir . '/' . $fileName;
            $absoluteDir = storage_path('app/' . $relativeDir);

            if (!is_dir($absoluteDir)) {
                mkdir($absoluteDir, 0755, true);
            }

            Excel::store($export, $relativePath, 'local');
            $absolutePath = storage_path('app/' . $relativePath);

            Mail::to($item->email)->send(new AnalisisVentasMensualMail($absolutePath, $fileName));

            $item->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'file_name' => $fileName,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Analisis ventas mensual queue failed: ' . $e->getMessage(), [
                'queue_id' => $item->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $item->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            try {
                Mail::to($item->email)->send(new AnalisisVentasMensualErrorMail($e->getMessage()));
            } catch (\Throwable $mailError) {
                Log::error('Analisis ventas mensual error mail failed: ' . $mailError->getMessage());
            }

            throw $e;
        }
    }
}

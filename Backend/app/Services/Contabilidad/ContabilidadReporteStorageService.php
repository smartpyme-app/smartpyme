<?php

namespace App\Services\Contabilidad;

use App\Http\Controllers\Api\Contabilidad\Reportes\GenerarReportesController;
use App\Http\Controllers\Api\Contabilidad\Reportes\NotasEstadosFinancierosController;
use App\Models\Admin\ReporteExportacion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContabilidadReporteStorageService
{
    /**
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function generate(ReporteExportacion $exportacion): array
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');

        $user = User::find($exportacion->id_usuario);
        if (! $user) {
            throw new \RuntimeException('Usuario de la exportación no encontrado.');
        }

        Auth::login($user);

        try {
            $fechaInicio = $exportacion->fecha_inicio->format('Y-m-d');
            $fechaFin = $exportacion->fecha_fin->format('Y-m-d');
            $params = $exportacion->parametros ?? [];
            $formato = $exportacion->formato;
            $tipo = $exportacion->tipo_reporte;

            if (in_array($tipo, [
                \App\Support\ContabilidadReporteTipos::NOTAS_EEFF,
                \App\Support\ContabilidadReporteTipos::ESTADOS_FINANCIEROS_COMPLETOS,
            ], true)) {
                return app(NotasEstadosFinancierosController::class)
                    ->exportarContabilidadAArchivo($tipo, $formato, $fechaInicio, $fechaFin, $params);
            }

            return app(GenerarReportesController::class)
                ->exportarContabilidadAArchivo($tipo, $formato, $fechaInicio, $fechaFin, $params);
        } finally {
            Auth::logout();
        }
    }

    /**
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function persistBinaryFileResponse(BinaryFileResponse $response, string $nombreArchivo): array
    {
        $file = $response->getFile();
        if (! $file) {
            throw new \RuntimeException('La respuesta de exportación no incluye archivo.');
        }

        $dir = storage_path('app/temp/reportes');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $absolute = $dir . '/' . $nombreArchivo;
        copy($file->getPathname(), $absolute);

        if (! file_exists($absolute)) {
            throw new \RuntimeException('No se pudo guardar el reporte en disco.');
        }

        return [
            'ruta' => 'temp/reportes/' . $nombreArchivo,
            'nombre' => $nombreArchivo,
            'mime' => $response->headers->get('Content-Type') ?: $this->mimeFromNombre($nombreArchivo),
        ];
    }

    /**
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function persistPdf($pdf, string $nombreArchivo): array
    {
        $dir = storage_path('app/temp/reportes');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $absolute = $dir . '/' . $nombreArchivo;
        $pdf->save($absolute);

        if (! file_exists($absolute)) {
            throw new \RuntimeException('No se pudo guardar el PDF del reporte.');
        }

        return [
            'ruta' => 'temp/reportes/' . $nombreArchivo,
            'nombre' => $nombreArchivo,
            'mime' => 'application/pdf',
        ];
    }

    private function mimeFromNombre(string $nombre): string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        if ($ext === 'pdf') {
            return 'application/pdf';
        }

        return 'application/octet-stream';
    }
}

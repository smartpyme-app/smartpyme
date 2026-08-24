<?php

namespace App\Exports;

use App\Services\Finanzas\AntiguedadSaldosService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AntiguedadSaldosExport implements FromArray, WithHeadings, WithTitle
{
    /** @param  array<string, mixed>  $reporte */
    public function __construct(private array $reporte)
    {
    }

    public function title(): string
    {
        return $this->reporte['tipo'] === 'cxp' ? 'CxP' : 'CxC';
    }

    public function headings(): array
    {
        $activos = $this->reporte['buckets_activos'] ?? AntiguedadSaldosService::BUCKETS;

        if (($this->reporte['modo'] ?? '') === 'individual') {
            $cols = ['Fecha', 'Documento', 'Vencimiento', 'Días', 'Bucket', 'Saldo'];

            return $cols;
        }

        $cols = ['Entidad', 'Identificación'];
        foreach ($activos as $b) {
            $cols[] = AntiguedadSaldosService::BUCKET_LABELS[$b] ?? $b;
        }
        $cols[] = 'Total';

        return $cols;
    }

    public function array(): array
    {
        $activos = $this->reporte['buckets_activos'] ?? AntiguedadSaldosService::BUCKETS;
        $rows = [];

        if (($this->reporte['modo'] ?? '') === 'individual') {
            foreach ($this->reporte['filas'] as $fila) {
                $rows[] = [
                    $fila['fecha'] ?? '',
                    $fila['documento'] ?? '',
                    $fila['fecha_pago'] ?? '',
                    $fila['dias'] ?? 0,
                    $fila['bucket_label'] ?? '',
                    $fila['saldo'] ?? 0,
                ];
            }
            $rows[] = ['', '', '', '', 'Total', $this->reporte['totales']['total'] ?? 0];

            return $rows;
        }

        foreach ($this->reporte['filas'] as $fila) {
            $row = [$fila['nombre'] ?? '', $fila['identificacion'] ?? ''];
            foreach ($activos as $b) {
                $row[] = $fila[$b] ?? 0;
            }
            $row[] = $fila['total'] ?? 0;
            $rows[] = $row;
        }

        $totales = $this->reporte['totales'] ?? [];
        $footer = ['TOTALES', ''];
        foreach ($activos as $b) {
            $footer[] = $totales[$b] ?? 0;
        }
        $footer[] = $totales['total'] ?? 0;
        $rows[] = $footer;

        return $rows;
    }
}

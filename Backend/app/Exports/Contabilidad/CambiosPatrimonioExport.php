<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class CambiosPatrimonioExport implements WithMultipleSheets
{
    public function __construct(
        private array $estado,
        private string $empresaNombre,
    ) {}

    public function sheets(): array
    {
        return [
            new CambiosPatrimonioMatrizSheet($this->estado, $this->empresaNombre),
            new CambiosPatrimonioValidacionSheet($this->estado, $this->empresaNombre),
        ];
    }
}

class CambiosPatrimonioMatrizSheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $estado,
        private string $empresaNombre,
    ) {}

    public function title(): string
    {
        return 'Cambios patrimonio';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->empresaNombre];
        $rows[] = ['ESTADO DE CAMBIOS EN EL PATRIMONIO NETO — USD'];
        $rows[] = [$this->estado['periodo_titulo'] ?? ''];
        $rows[] = ['Utilidad neta: ref. estado de resultados NIIF. Reserva legal: 7% hasta 20% capital (Art. 123 C. Comercio SV).'];
        $rows[] = [];

        $columnas = $this->estado['columnas'] ?? [];
        $header = ['Concepto / Movimiento'];
        foreach ($columnas as $col) {
            $header[] = $col['etiqueta'];
        }
        $header[] = 'TOTAL PATRIMONIO';
        $rows[] = $header;

        foreach ($this->estado['bloques'] ?? [] as $bloque) {
            if (count($this->estado['bloques'] ?? []) > 1) {
                $rows[] = [];
                $rows[] = [$bloque['titulo'] ?? ''];
            }
            foreach ($bloque['filas'] ?? [] as $fila) {
                $line = [$fila['etiqueta'] ?? ''];
                foreach ($columnas as $col) {
                    $v = (float) ($fila['valores'][$col['clave']] ?? 0);
                    $line[] = abs($v) < 0.0005 ? null : $v;
                }
                $total = (float) ($fila['total'] ?? 0);
                $line[] = abs($total) < 0.0005 ? null : $total;
                $rows[] = $line;
            }
        }

        return $rows;
    }
}

class CambiosPatrimonioValidacionSheet implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $estado,
        private string $empresaNombre,
    ) {}

    public function title(): string
    {
        return 'Validación';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Validación cruzada con Balance General'];
        $rows[] = ['Período', 'Patrimonio estado', 'Patrimonio balance', 'Diferencia', 'Cuadra'];

        foreach ($this->estado['bloques'] ?? [] as $bloque) {
            $rows[] = [
                $bloque['fecha_fin'] ?? '',
                $bloque['total_patrimonio_estado'] ?? 0,
                $bloque['total_patrimonio_balance'] ?? 0,
                $bloque['diferencia_cuadre'] ?? 0,
                ! empty($bloque['cuadre_balance']) ? 'Sí' : 'No',
            ];
        }

        $rows[] = [];
        $rows[] = ['Parámetros reserva legal (Art. 123 C. Comercio)'];
        $rows[] = ['Tasa reserva legal', 0.07];
        $rows[] = ['Tope reserva (% capital)', 0.20];
        $rows[] = [];
        $rows[] = ['Alertas'];

        $alertas = $this->estado['validaciones']['alertas'] ?? [];
        if ($alertas === []) {
            $rows[] = ['Sin alertas'];
        } else {
            foreach ($alertas as $alerta) {
                $rows[] = [$alerta];
            }
        }

        $rows[] = [];
        $rows[] = ['Fórmula ejemplo reserva legal'];
        $rows[] = ['=MIN(B1*utilidad_neta, MAX(0, capital*0.20-reserva_acum)) donde B1=0.07'];

        return $rows;
    }
}

<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Services\Contabilidad\LibroIvaMontosHelper;
use App\Support\Honduras\FormatoCorrelativoHn;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Libro de ventas a contribuyentes - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroContribuyentesExport implements FromCollection, WithMapping, WithHeadings, WithCustomStartCell, WithEvents
{
    public $request;

    private int $index = 1;

    /** @var list<string> */
    public const TIPOS_CONTRIBUYENTE = ['Factura con RTN', 'Factura', 'Crédito fiscal'];

    /** @var list<string> */
    private const CLAVES_FILA = [
        'no',
        'fecha',
        'correlativo',
        'nrc',
        'nombre',
        'exentas',
        'no_sujetas',
        'gravadas_locales',
        'debito_fiscal',
        'cta_terceros',
        'debito_cta_terceros',
        'iva_percibido',
        'iva_retenido',
        'total',
    ];

    /** @var list<string> */
    private const CLAVES_RESUMEN = [
        'gravadas',
        'exportaciones',
        'debito_fiscal',
        'iva_percibido',
        'iva_retenido',
    ];

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    /** Encabezado SAR en filas 1–6; columnas empiezan en fila 7. */
    public function startCell(): string
    {
        return 'A7';
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $empresa = Auth::user()?->empresa()->first();
                $sheet = $event->sheet;
                $sheet->setCellValue('A2', $empresa->nombre ?? '');
                $sheet->setCellValue('A3', 'LIBRO DE VENTAS A CONTRIBUYENTES');
                $sheet->setCellValue(
                    'A4',
                    'MES: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F'))
                );
                $sheet->setCellValue(
                    'G4',
                    'AÑO: ' . Carbon::parse($this->request->inicio)->format('Y')
                );
                $sheet->setCellValue('A5', 'NIT: ' . ($empresa->nit ?? ''));
                $sheet->setCellValue('G5', 'NRC: ' . ($empresa->ncr ?? ''));
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'N';
                $headerRow = 7;
                $subHeaderRow = 8;

                // Fila secundaria: subcolumnas bajo el grupo "Ventas" (F–N).
                $sheet->insertNewRowBefore($subHeaderRow, 1);
                $sheet->setCellValue("F{$headerRow}", 'Ventas');
                foreach ([
                    'F' => 'Exentas',
                    'G' => 'No Sujetas',
                    'H' => 'Gravadas Locales',
                    'I' => 'Débito Fiscal',
                    'J' => 'Ventas a Cuenta de Terceros',
                    'K' => 'Debito F. a Cta. De Terceros',
                    'L' => 'IVA Percibido',
                    'M' => 'IVA Retenido',
                    'N' => 'Total Ventas',
                ] as $col => $label) {
                    $sheet->setCellValue("{$col}{$subHeaderRow}", $label);
                }

                foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                    $sheet->mergeCells("{$col}{$headerRow}:{$col}{$subHeaderRow}");
                }
                $sheet->mergeCells("F{$headerRow}:{$lastCol}{$headerRow}");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");

                $firstDataRow = $subHeaderRow + 1;
                $lastDataRow = max($subHeaderRow, $sheet->getHighestRow());
                $totalRow = $lastDataRow + 1;

                $sheet->getStyle("A2:{$lastCol}5")->getFont()->setBold(true);
                $sheet->getStyle('A2:A3')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$subHeaderRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$subHeaderRow}")->getAlignment()
                    ->setWrapText(true)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                if ($lastDataRow >= $headerRow) {
                    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                if ($lastDataRow >= $firstDataRow) {
                    $sheet->getStyle("F{$firstDataRow}:{$lastCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // ponytail: 2ª consulta vía rowsForApi para TOTAL+resumen (techo: cachear en map).
                $api = $this->rowsForApi();
                $filas = $api['filas'];
                $resumen = $api['resumen_operaciones'];
                $sum = static fn (string $k) => round(array_sum(array_column($filas, $k)), 2);

                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                foreach ([
                    'F' => 'exentas',
                    'G' => 'no_sujetas',
                    'H' => 'gravadas_locales',
                    'I' => 'debito_fiscal',
                    'J' => 'cta_terceros',
                    'K' => 'debito_cta_terceros',
                    'L' => 'iva_percibido',
                    'M' => 'iva_retenido',
                    'N' => 'total',
                ] as $col => $key) {
                    $sheet->setCellValue("{$col}{$totalRow}", $sum($key));
                }
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("F{$totalRow}:{$lastCol}{$totalRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $r = $totalRow + 2;
                $sheet->setCellValue("E{$r}", 'Resumen Operaciones');
                $sheet->setCellValue("F{$r}", 'Gravadas');
                $sheet->setCellValue("G{$r}", 'Exportaciones');
                $sheet->setCellValue("H{$r}", 'Debito Fiscal');
                $sheet->setCellValue("I{$r}", 'IVA Percibido');
                $sheet->setCellValue("J{$r}", 'IVA Retenido');
                $sheet->getStyle("E{$r}:J{$r}")->getFont()->setBold(true);
                $sheet->getStyle("E{$r}:J{$r}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $r++;

                foreach ([
                    'Consumidor Final' => 'consumidor_final',
                    'Contribuyentes' => 'contribuyentes',
                    'Ventas a Cta de Terceros' => 'cta_terceros',
                    'Total' => 'totales_detalle',
                ] as $label => $bloque) {
                    $vals = $resumen[$bloque] ?? [];
                    $sheet->setCellValue("E{$r}", $label);
                    $sheet->setCellValue("F{$r}", round((float) ($vals['gravadas'] ?? 0), 2));
                    $sheet->setCellValue("G{$r}", round((float) ($vals['exportaciones'] ?? 0), 2));
                    $sheet->setCellValue("H{$r}", round((float) ($vals['debito_fiscal'] ?? 0), 2));
                    $sheet->setCellValue("I{$r}", round((float) ($vals['iva_percibido'] ?? 0), 2));
                    $sheet->setCellValue("J{$r}", round((float) ($vals['iva_retenido'] ?? 0), 2));
                    $sheet->getStyle("F{$r}:J{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("E{$r}:J{$r}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $r++;
                }

                $r += 2;
                $sheet->mergeCells("E{$r}:J{$r}");
                $sheet->setCellValue("E{$r}", '__________________________');
                $sheet->getStyle("E{$r}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->mergeCells('E' . ($r + 1) . ':J' . ($r + 1));
                $sheet->setCellValue('E' . ($r + 1), 'Nombre y Firma de Contador');
                $sheet->getStyle('E' . ($r + 1))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }

    public function headings(): array
    {
        return [
            'No.',
            'Fecha Emisión',
            'Numero Correlativo de Documento',
            'NRC',
            'Nombre del Contribuyente',
            'Ventas',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function collection()
    {
        return $this->registros();
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, resumen_operaciones: array<string, array<string, float>>}
     */
    public function rowsForApi(): array
    {
        $filas = $this->registros()->values()->map(function ($item, $index) {
            return $this->mapVenta($item->registro, $index + 1, (int) $item->mult);
        })->all();

        return [
            'filas' => $filas,
            'resumen_operaciones' => $this->resumenOperaciones($filas),
        ];
    }

    private function registros(): Collection
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereHas('documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONTRIBUYENTE))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($v) => (object) ['registro' => $v, 'mult' => 1]);

        $devoluciones = DevolucionVenta::with(['cliente', 'documento', 'venta.documento'])
            ->where('enable', true)
            ->whereHas('venta.documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONTRIBUYENTE))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($d) => (object) ['registro' => $d, 'mult' => -1]);

        return $ventas->merge($devoluciones)
            ->sortBy(fn ($x) => [(string) $x->registro->fecha, (string) ($x->registro->correlativo ?? '')])
            ->values();
    }

    private function mapVenta(object $venta, int $no, int $mult = 1): array
    {
        $cliente = $venta->cliente ?? null;
        $documento = $venta->documento ?? null;

        return [
            'no' => $no,
            'fecha' => (string) $venta->fecha,
            'correlativo' => FormatoCorrelativoHn::format(
                $documento->numero_emision ?? null,
                $venta->correlativo
            ),
            'nrc' => (string) ($cliente?->ncr ?? ''),
            'nombre' => (string) ($venta->nombre_cliente ?? $cliente?->nombre ?? ''),
            'exentas' => round(LibroIvaMontosHelper::ventasExentas($venta) * $mult, 2),
            'no_sujetas' => round(LibroIvaMontosHelper::ventasNoSujetas($venta) * $mult, 2),
            'gravadas_locales' => round(LibroIvaMontosHelper::ventasGravadas($venta) * $mult, 2),
            'debito_fiscal' => round((float) ($venta->iva ?? 0) * $mult, 2),
            'cta_terceros' => round((float) ($venta->cuenta_a_terceros ?? 0) * $mult, 2),
            // ponytail: no hay fuente de débito fiscal por cuenta de terceros (techo: persistir campo si el SAR lo exige separado).
            'debito_cta_terceros' => 0.0,
            'iva_percibido' => round((float) ($venta->iva_percibido ?? 0) * $mult, 2),
            'iva_retenido' => round((float) ($venta->iva_retenido ?? 0) * $mult, 2),
            'total' => round((float) ($venta->total ?? 0) * $mult, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{
     *     totales_detalle: array<string, float>,
     *     consumidor_final: array<string, float>,
     *     contribuyentes: array<string, float>,
     *     cta_terceros: array<string, float>
     * }
     */
    private function resumenOperaciones(array $filas): array
    {
        $vacio = array_fill_keys(self::CLAVES_RESUMEN, 0.0);
        $totalesDetalle = $vacio;
        $contribuyentes = $vacio;
        $ctaTerceros = $vacio;

        foreach ($filas as $fila) {
            $gravadas = (float) ($fila['gravadas_locales'] ?? 0);
            $debito = (float) ($fila['debito_fiscal'] ?? 0);
            $percibido = (float) ($fila['iva_percibido'] ?? 0);
            $retenido = (float) ($fila['iva_retenido'] ?? 0);

            $totalesDetalle['gravadas'] += $gravadas;
            $totalesDetalle['debito_fiscal'] += $debito;
            $totalesDetalle['iva_percibido'] += $percibido;
            $totalesDetalle['iva_retenido'] += $retenido;

            $contribuyentes['gravadas'] += $gravadas;
            $contribuyentes['debito_fiscal'] += $debito;
            $contribuyentes['iva_percibido'] += $percibido;
            $contribuyentes['iva_retenido'] += $retenido;

            $ctaTerceros['gravadas'] += (float) ($fila['cta_terceros'] ?? 0);
            $ctaTerceros['debito_fiscal'] += (float) ($fila['debito_cta_terceros'] ?? 0);
        }

        // exportaciones / consumidor_final: sin fuente en este libro (solo Crédito fiscal).
        return [
            'totales_detalle' => $this->redondearResumen($totalesDetalle),
            'consumidor_final' => $vacio,
            'contribuyentes' => $this->redondearResumen($contribuyentes),
            'cta_terceros' => $this->redondearResumen($ctaTerceros),
        ];
    }

    /**
     * @param  array<string, float>  $bloque
     * @return array<string, float>
     */
    private function redondearResumen(array $bloque): array
    {
        foreach ($bloque as $clave => $valor) {
            $bloque[$clave] = round($valor, 2);
        }

        return $bloque;
    }

    public function map($item): array
    {
        $row = $this->mapVenta($item->registro, $this->index++, (int) $item->mult);
        $valores = [];
        foreach (self::CLAVES_FILA as $clave) {
            $valores[] = $row[$clave];
        }

        return $valores;
    }
}

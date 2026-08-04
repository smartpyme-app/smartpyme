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
 * Libro de ventas a consumidor final - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroConsumidoresExport implements FromCollection, WithMapping, WithHeadings, WithCustomStartCell, WithEvents
{
    public $request;

    /** CAI de la empresa, usado como respaldo cuando el documento no trae resolución. */
    public string $caiEmpresa = '';

    private int $index = 1;

    /** @var list<string> */
    public const TIPOS_CONSUMIDOR = ['Factura', 'Factura de exportación'];

    /** @var list<string> */
    private const CLAVES_FILA = [
        'no',
        'fecha',
        'factura_no',
        'cai_no',
        'maquina_registradora',
        'exentas',
        'exoneradas',
        'gravadas_15',
        'gravadas_18',
        'total_ventas',
        'cuenta_terceros',
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
                $sheet->setCellValue('A3', 'LIBRO DE VENTAS A CONSUMIDOR FINAL');
                $sheet->setCellValue(
                    'A4',
                    'MES: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F'))
                );
                $sheet->setCellValue(
                    'J4',
                    'AÑO: ' . Carbon::parse($this->request->inicio)->format('Y')
                );
                $sheet->setCellValue('A5', 'NIT: ' . ($empresa->nit ?? ''));
                $sheet->setCellValue('J5', 'NRC: ' . ($empresa->ncr ?? ''));
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'K';
                $headerRow = 7;
                $subHeaderRow = 8;

                // Fila secundaria del encabezado oficial (Ventas Gravadas → 15% | 18%).
                $sheet->insertNewRowBefore($subHeaderRow, 1);
                $sheet->setCellValue("H{$headerRow}", 'Ventas Gravadas');
                $sheet->setCellValue("H{$subHeaderRow}", '15%');
                $sheet->setCellValue("I{$subHeaderRow}", '18%');

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J', 'K'] as $col) {
                    $sheet->mergeCells("{$col}{$headerRow}:{$col}{$subHeaderRow}");
                }
                $sheet->mergeCells("H{$headerRow}:I{$headerRow}");
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
                $resumen = $api['resumen'];
                $sum = static fn (string $k) => round(array_sum(array_column($filas, $k)), 2);

                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("F{$totalRow}", $sum('exentas'));
                $sheet->setCellValue("G{$totalRow}", $sum('exoneradas'));
                $sheet->setCellValue("H{$totalRow}", $sum('gravadas_15'));
                $sheet->setCellValue("I{$totalRow}", $sum('gravadas_18'));
                $sheet->setCellValue("J{$totalRow}", $sum('total_ventas'));
                $sheet->setCellValue("K{$totalRow}", $sum('cuenta_terceros'));
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("F{$totalRow}:{$lastCol}{$totalRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $r = $totalRow + 2;
                $sheet->setCellValue("A{$r}", 'Resumen');
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                $r++;
                foreach ([
                    'Exentas' => 'total_exentas',
                    'Exoneradas' => 'total_exoneradas',
                    'Netas Gravadas 15%' => 'netas_15',
                    'Netas Gravadas 18%' => 'netas_18',
                    'Debito Fiscal' => 'debito_fiscal',
                    'Credito Fiscal' => 'credito_fiscal',
                ] as $label => $key) {
                    $sheet->setCellValue("A{$r}", $label);
                    $sheet->setCellValue("B{$r}", round((float) ($resumen[$key] ?? 0), 2));
                    $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $r++;
                }

                $r++;
                $sheet->setCellValue("A{$r}", '__________________________');
                $sheet->setCellValue('A' . ($r + 1), 'Nombre y Firma de Contador');
            },
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'Fecha',
            'Factura N°',
            'CAI N°',
            'N° de Maquina registradora',
            'Ventas Exentas',
            'Ventas Exoneradas',
            'Ventas Gravadas',
            '',
            'Total Ventas',
            'Ventas a Cuenta de Terceros',
        ];
    }

    public function collection()
    {
        return $this->registros();
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, resumen: array<string, float>}
     */
    public function rowsForApi(): array
    {
        $filas = $this->registros()->values()->map(function ($item, $index) {
            return $this->mapVenta($item->registro, $index + 1, (int) $item->mult);
        })->all();

        $exentas = 0.0;
        $exoneradas = 0.0;
        $netas15 = 0.0;
        $netas18 = 0.0;
        foreach ($filas as $fila) {
            $exentas += (float) $fila['exentas'];
            $exoneradas += (float) $fila['exoneradas'];
            $netas15 += (float) $fila['gravadas_15'];
            $netas18 += (float) $fila['gravadas_18'];
        }

        return [
            'filas' => $filas,
            'resumen' => [
                'total_exentas' => round($exentas, 2),
                'total_exoneradas' => round($exoneradas, 2),
                'netas_15' => round($netas15, 2),
                'netas_18' => round($netas18, 2),
                'debito_fiscal' => round($netas15 * 0.15 + $netas18 * 0.18, 2),
                // Libro de ventas: el crédito fiscal proviene de compras, sin fuente aquí.
                'credito_fiscal' => 0.0,
            ],
        ];
    }

    private function registros(): Collection
    {
        $request = $this->request;

        $this->caiEmpresa = (string) data_get(
            optional(Auth::user()?->empresa()->first())->custom_empresa,
            'configuraciones.factura_cai',
            ''
        );

        $ventas = Venta::with(['documento', 'detalles'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereHas('documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONSUMIDOR))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($v) => (object) ['registro' => $v, 'mult' => 1]);

        $devoluciones = DevolucionVenta::with(['documento', 'venta.documento'])
            ->where('enable', true)
            ->whereHas('venta.documento', fn ($q) => $q->whereIn('nombre', self::TIPOS_CONSUMIDOR))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn ($d) => (object) ['registro' => $d, 'mult' => -1]);

        return $ventas->merge($devoluciones)
            ->sortBy(fn ($x) => [(string) $x->registro->fecha, (string) ($x->registro->correlativo ?? '')])
            ->values();
    }

    private function mapVenta(object $registro, int $no, int $mult = 1): array
    {
        $documento = $registro->documento ?? null;
        $cai = trim((string) ($documento->resolucion ?? ''));
        if ($cai === '') {
            $cai = $this->caiEmpresa;
        }

        $bases = $this->basesPorTasa($registro->detalles ?? [], $mult);
        if ($bases['gravadas_15'] === 0.0 && $bases['gravadas_18'] === 0.0) {
            // detalles_devolucion_venta no guardan tasa por línea.
            // ponytail: toda la base gravada de la devolución se asume 15% (techo: agregar porcentaje_impuesto al detalle de devolución para el split real).
            $bases['gravadas_15'] = round(LibroIvaMontosHelper::ventasGravadas($registro) * $mult, 2);
        }

        return [
            'no' => $no,
            'fecha' => (string) $registro->fecha,
            'factura_no' => FormatoCorrelativoHn::format(
                $documento->numero_emision ?? null,
                $registro->correlativo
            ),
            'cai_no' => $cai,
            // ponytail: no existe fuente de N° de máquina registradora en el modelo (techo: agregar campo cuando exista POS fiscal).
            'maquina_registradora' => '',
            'exentas' => round(LibroIvaMontosHelper::ventasExentas($registro) * $mult, 2),
            'exoneradas' => round(LibroIvaMontosHelper::ventasNoSujetas($registro) * $mult, 2),
            'gravadas_15' => round($bases['gravadas_15'], 2),
            'gravadas_18' => round($bases['gravadas_18'], 2),
            'total_ventas' => round((float) ($registro->total ?? 0) * $mult, 2),
            'cuenta_terceros' => round((float) ($registro->cuenta_a_terceros ?? 0) * $mult, 2),
        ];
    }

    /**
     * @return array{gravadas_15: float, gravadas_18: float}
     */
    private function basesPorTasa(iterable $detalles, int $mult): array
    {
        $bases = ['gravadas_15' => 0.0, 'gravadas_18' => 0.0];
        foreach ($detalles as $detalle) {
            $tasa = (float) ($detalle->porcentaje_impuesto ?? 0);
            $base = (float) ($detalle->gravada ?? 0) * $mult;
            if (abs($tasa - 15.0) < 0.01) {
                $bases['gravadas_15'] += $base;
            } elseif (abs($tasa - 18.0) < 0.01) {
                $bases['gravadas_18'] += $base;
            }
        }

        return $bases;
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

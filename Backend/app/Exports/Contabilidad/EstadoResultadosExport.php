<?php

namespace App\Exports\Contabilidad;

use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EstadoResultadosExport implements WithMultipleSheets
{
    public function __construct(
        private array $estado,
        private string $empresa,
        private bool $comparar = false
    ) {
    }

    public function sheets(): array
    {
        return [
            new EstadoResultadosHojaCascada($this->estado, $this->empresa, $this->comparar),
            new EstadoResultadosHojaParametros($this->estado),
        ];
    }
}

class EstadoResultadosHojaCascada implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $estado,
        private string $empresa,
        private bool $comparar = false
    ) {
    }

    public function title(): string
    {
        return 'Estado resultados';
    }

    public function array(): array
    {
        return [['']];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $P = EstadoResultadosNiifSvPresenter::class;
                $c = $this->estado['cascada'] ?? [];
                $a = $this->comparar
                    ? ($this->estado['comparativa']['anterior']['cascada'] ?? null)
                    : null;
                $cmp = $this->comparar;
                $lastCol = $cmp ? 'D' : 'B';

                $g = function (?array $ar, $key) {
                    if (! $ar || ! array_key_exists($key, $ar)) {
                        return null;
                    }

                    return (float) $ar[$key];
                };

                $varFn = function (int $r) {
                    return '=IF(OR(ISBLANK(C' . $r . '),C' . $r . '=0),"",(B' . $r . '-C' . $r . ')/C' . $r . ')';
                };

                $write = function (int $r, string $label, $b, $cVal, bool $bold = false, bool $soloIndicador = false) use ($sheet, $varFn, $cmp) {
                    $sheet->setCellValue('A' . $r, $label);
                    if ($b === null) {
                        $sheet->setCellValue('B' . $r, '');
                    } else {
                        $sheet->setCellValue('B' . $r, $b);
                    }
                    if (! $cmp) {
                        if ($bold) {
                            $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
                        }

                        return;
                    }
                    if ($soloIndicador) {
                        $sheet->setCellValue('C' . $r, '');
                        $sheet->setCellValue('D' . $r, '');
                    } else {
                        if ($cVal === null) {
                            $sheet->setCellValue('C' . $r, '');
                        } else {
                            $sheet->setCellValue('C' . $r, $cVal);
                        }
                        if ($b === null && $cVal === null) {
                            $sheet->setCellValue('D' . $r, '');
                        } else {
                            $sheet->setCellValue('D' . $r, $varFn($r));
                        }
                    }
                    if ($bold) {
                        $sheet->getStyle("A{$r}:D{$r}")->getFont()->setBold(true);
                    }
                };

                $row = 1;
                $sheet->setCellValue('A' . $row, $this->empresa);
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $row++;
                $sheet->setCellValue('A' . $row, 'ESTADO DE RESULTADOS (NIIF PYMES — El Salvador) — en USD, sin IVA en ingresos/gastos');
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $row++;
                $sheet->setCellValue('A' . $row, (string) ($this->estado['periodo_titulo'] ?? ''));
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $row++;
                if ($cmp) {
                    $pt = (string) ($this->estado['comparativa']['periodo_anterior_titulo'] ?? '');
                    if ($pt !== '') {
                        $sheet->setCellValue('A' . $row, 'Comparado con: ' . $pt);
                        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                        $row++;
                    }
                }
                $nota = $cmp
                    ? 'Hoja "Parámetros": editar tasas. Columna "Var. %" mide el cambio respecto al periodo inmediatamente anterior de igual duración. Costo: PEPS (FIFO) NIC 2, cuando aplica.'
                    : 'Hoja "Parámetros": editar tasas fiscales de referencia. Sólo se muestran cifras del período indicado. Costo: PEPS (FIFO) NIC 2, cuando aplica.';
                $sheet->setCellValue('A' . $row, $nota);
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $row += 2;

                $h = $row;
                $sheet->setCellValue("A{$h}", 'Concepto');
                if ($cmp) {
                    $sheet->setCellValue("B{$h}", 'Período actual');
                    $sheet->setCellValue("C{$h}", 'Período ant.');
                    $sheet->setCellValue("D{$h}", 'Var. %');
                } else {
                    $sheet->setCellValue("B{$h}", 'Importe');
                }
                $sheet->getStyle("A{$h}:{$lastCol}{$h}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2E8F0'],
                    ],
                ]);
                $row = $h + 1;

                $section = function (string $t) use ($sheet, &$row, $lastCol) {
                    $sheet->setCellValue("A{$row}", $t);
                    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $row++;
                };

                $section('INGRESOS DE OPERACIÓN');
                $write($row, 'Ventas brutas de bienes y/o servicios (sin IVA)', $g($c, 'ventas_brutas'), $g($a, 'ventas_brutas'));
                $row++;
                $write($row, 'Menos: devoluciones sobre ventas', $g($c, 'devoluciones_ventas'), $g($a, 'devoluciones_ventas'));
                $row++;
                $write($row, 'Menos: descuentos sobre ventas', $g($c, 'descuentos_ventas'), $g($a, 'descuentos_ventas'));
                $row++;
                $write($row, '  ' . $P::LBL_VENTAS_NETAS, $g($c, $P::LBL_VENTAS_NETAS), $g($a, $P::LBL_VENTAS_NETAS), true);
                $row++;

                $section('COSTO DE VENTAS (PEPS / FIFO, NIC 2 — cuando aplica)');
                $write($row, 'Inventario inicial de mercaderías', $g($c, 'inventario_inicial'), $g($a, 'inventario_inicial'));
                $row++;
                $write($row, 'Compras brutas del período', $g($c, 'compras_brutas'), $g($a, 'compras_brutas'));
                $row++;
                $write($row, 'Fletes y seguros sobre compras', $g($c, 'fletes_compras'), $g($a, 'fletes_compras'));
                $row++;
                $write($row, 'Menos: devoluciones sobre compras', $g($c, 'devoluciones_compras'), $g($a, 'devoluciones_compras'));
                $row++;
                $write($row, 'Menos: descuentos sobre compras', $g($c, 'descuentos_compras'), $g($a, 'descuentos_compras'));
                $row++;
                $write($row, 'Menos: inventario final de mercaderías', $g($c, 'inventario_final'), $g($a, 'inventario_final'));
                $row++;
                $write($row, '  ' . $P::LBL_COGS, $g($c, $P::LBL_COGS), $g($a, $P::LBL_COGS), true);
                $row++;
                $write($row, '  ' . $P::LBL_UTILIDAD_BRUTA, $g($c, $P::LBL_UTILIDAD_BRUTA), $g($a, $P::LBL_UTILIDAD_BRUTA), true);
                $row++;

                $section('GASTOS DE VENTA');
                $detGv = (array) ($c['gastos_venta_detalle'] ?? []);
                $antGv = (array) ($a['gastos_venta_detalle'] ?? []);
                foreach ((array) ($c['gastos_venta_lineas'] ?? []) as $line) {
                    $k = (string) ($line['k'] ?? '');
                    $lab = (string) ($line['etiqueta'] ?? $k);
                    $write($row, '  ' . $lab, (float) ($detGv[$k] ?? 0.0), $a ? (float) ($antGv[$k] ?? 0.0) : null);
                    $row++;
                }
                $write($row, '  TOTAL GASTOS DE VENTA', (float) ($c['total_gastos_venta'] ?? 0), $a ? (float) ($a['total_gastos_venta'] ?? 0) : null, true);
                $row++;

                $section('GASTOS DE ADMINISTRACIÓN');
                $detGa = (array) ($c['gastos_admin_detalle'] ?? []);
                $antGa = (array) ($a['gastos_admin_detalle'] ?? []);
                foreach ((array) ($c['gastos_admin_lineas'] ?? []) as $line) {
                    $k = (string) ($line['k'] ?? '');
                    $lab = (string) ($line['etiqueta'] ?? $k);
                    $write($row, '  ' . $lab, (float) ($detGa[$k] ?? 0.0), $a ? (float) ($antGa[$k] ?? 0.0) : null);
                    $row++;
                }
                $write($row, '  TOTAL GASTOS DE ADMINISTRACIÓN', (float) ($c['total_gastos_admin'] ?? 0), $a ? (float) ($a['total_gastos_admin'] ?? 0) : null, true);
                $row++;
                $write($row, '  ' . $P::LBL_TOT_GASTOS_OP, $g($c, $P::LBL_TOT_GASTOS_OP), $g($a, $P::LBL_TOT_GASTOS_OP), true);
                $row++;
                $write($row, '  ' . $P::LBL_UTILIDAD_OP, $g($c, $P::LBL_UTILIDAD_OP), $g($a, $P::LBL_UTILIDAD_OP), true);
                $row++;

                $section('OTROS INGRESOS Y GASTOS (NO OPERATIVO / FINANCIERO)');
                $write($row, '  (+) Total otros ingresos (arrend., intereses, enajenación de activos, etc.)', (float) ($c['otros_ing'] ?? 0), $a ? (float) ($a['otros_ing'] ?? 0) : null);
                $row++;
                $write($row, '  (-) Total otros gastos (intereses, bancos, pérd. en activos, etc.)', (float) ($c['otros_gas'] ?? 0), $a ? (float) ($a['otros_gas'] ?? 0) : null);
                $row++;
                $write($row, '  ' . $P::LBL_TOT_OTROS, $g($c, $P::LBL_TOT_OTROS), $g($a, $P::LBL_TOT_OTROS), true);
                $row++;
                $write($row, '  ' . $P::LBL_UTIL_ANTES_RES, $g($c, $P::LBL_UTIL_ANTES_RES), $g($a, $P::LBL_UTIL_ANTES_RES), true);
                $row++;
                $write($row, '  ' . $P::LBL_RESERVA, (float) ($c['reserva_legal'] ?? 0), $a ? (float) ($a['reserva_legal'] ?? 0) : null, false);
                $row++;
                $write($row, '  ' . $P::LBL_UTIL_ANTES_ISR, $g($c, $P::LBL_UTIL_ANTES_ISR), $g($a, $P::LBL_UTIL_ANTES_ISR), true);
                $row++;
                $write($row, '  Tasa ISR aplicada (estim. umbral 150,000 US$)', (float) ($c['isr_tasa'] ?? 0), $a ? (float) ($a['isr_tasa'] ?? 0) : null);
                $row++;
                $write($row, '  ' . $P::LBL_ISR_EST, (float) ($c['isr_estimado'] ?? 0), $a ? (float) ($a['isr_estimado'] ?? 0) : null);
                $row++;
                $write($row, '  ' . $P::LBL_BASE_PAGO, (float) ($c['base_ingresos_brutos'] ?? 0), $a ? (float) ($a['base_ingresos_brutos'] ?? 0) : null);
                $row++;
                $write($row, '  ' . $P::LBL_PAGO_CTA, (float) ($c['pago_cuenta'] ?? 0), $a ? (float) ($a['pago_cuenta'] ?? 0) : null);
                $row++;
                $write($row, '  ' . $P::LBL_ISR_NETO, (float) ($c['isr_neto'] ?? 0), $a ? (float) ($a['isr_neto'] ?? 0) : null, true);
                $row++;
                $write($row, '  ' . $P::LBL_UTIL_NETA, $g($c, $P::LBL_UTIL_NETA), $g($a, $P::LBL_UTIL_NETA), true);
                $row += 2;

                $kpi = $this->estado['kpi'] ?? [];
                $section('KPIs (sobre ' . $P::LBL_VENTAS_NETAS . ')');
                $kpiFirst = $row;
                $kfn = function ($v) {
                    if ($v === null) {
                        return null;
                    }

                    return (float) $v;
                };
                $write($row, 'Margen bruto (Utilidad bruta / VN)', $kfn($kpi['margen_bruto'] ?? null), null, false, true);
                $row++;
                $write($row, 'Margen operativo (Utilidad oper. / VN)', $kfn($kpi['margen_operativo'] ?? null), null, false, true);
                $row++;
                $write($row, 'Margen neto (Utilidad neta / VN)', $kfn($kpi['margen_neto'] ?? null), null, false, true);
                $row++;
                if ($cmp) {
                    $write($row, 'Crecimiento ventas netas (actual / período inmediatamente anterior)', $kfn($kpi['crec_ventas'] ?? null), null, false, true);
                    $row++;
                }
                $write($row, 'Carga fiscal ISR (ISR est. / U. antes ISR)', $kfn($kpi['carga_fiscal_isr'] ?? null), null, false, true);
                $row++;
                $write($row, 'Costo de ventas % (cogs / VN)', $kfn($kpi['costo_ventas_pct'] ?? null), null, false, true);

                $maxRow = $row;
                $endCol = $lastCol;
                if ($kpiFirst > $h) {
                    $sheet->getStyle("B{$h}:{$endCol}" . ($kpiFirst - 1))->getNumberFormat()->setFormatCode('#,##0.00');
                }
                if ($kpiFirst <= $maxRow) {
                    $sheet->getStyle('B' . $kpiFirst . ':B' . $maxRow)->getNumberFormat()->setFormatCode('0.00%');
                }
                $sheet->getStyle("B{$h}:{$endCol}{$maxRow}")->getAlignment()->setHorizontal('right');
                $sheet->getStyle("A1:{$lastCol}4")->getAlignment()->setHorizontal('center');
            },
        ];
    }
}

class EstadoResultadosHojaParametros implements FromArray, WithTitle, WithEvents, ShouldAutoSize
{
    public function __construct(
        private array $estado
    ) {
    }

    public function title(): string
    {
        return 'Parámetros';
    }

    public function array(): array
    {
        return [['']];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();
                $c = $this->estado['cascada'] ?? [];
                $s->setCellValue('A1', 'Tasa reserva legal (Art. 123 C. Comercio) — edite la celda B1; reporte generado con 7%');
                $s->setCellValue('B1', 0.07);
                $s->setCellValue('A2', 'Tasa ISR (0,25 ing. grav. proyect. ≤ 150,000; 0,30 en caso contrario) — sugerida en cálculo:');
                $s->setCellValue('B2', (float) ($c['isr_tasa'] ?? 0.30));
                $s->setCellValue('A3', 'Tasa pago a cuenta (1,75% ing. brutos) — edite B3:');
                $s->setCellValue('B3', 0.0175);
                $s->setCellValue('A4', 'Umbral anual (USD) para 25% ISR:');
                $s->setCellValue('B4', 150000);
                $s->setCellValue('A5', 'Ingreso gravable proyectado a anual (del período):');
                $s->setCellValue('B5', (float) ($this->estado['L']['ingresos_gravables_proyectados'] ?? 0));
                $s->getStyle('B1:B3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('B4D4FF');
                $s->getStyle('B1:B5')->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }
}

<?php

namespace App\Services\Planilla;

use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Exports\PlanillaExport;
use App\Exports\Planillas\PlanillaDetallesExport;
use App\Exports\Planillas\DescuentosPatronalesExport;
use App\Exports\PlanillaExportTemplate;
use App\Models\Planilla\Empleado;
use App\Constants\PlanillaConstants;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Illuminate\Support\Facades\Log;

class PlanillaExportService
{
    /**
     * Exportar planilla a Excel
     */
    public function exportarExcel($id)
    {
        try {
            $planilla = Planilla::with(['detalles.empleado'])->findOrFail($id);

            return Excel::download(
                new PlanillaExport($planilla),
                'planilla_' . $planilla->codigo . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a Excel: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Exportar planilla a PDF
     */
    public function exportarPDF($id)
    {
        try {
            $planilla = Planilla::with(['detalles' => function($query) {
                    $query->where('estado', '!=', 0);
                }, 'detalles.empleado', 'empresa'])
                ->findOrFail($id);

            $pdf = PDF::loadView('pdf.planilla-detalle', [
                'planilla' => $planilla,
                'detalles' => $planilla->detalles,
                'empresa' => $planilla->empresa
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('planilla_' . $planilla->codigo . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generar boletas de pago en PDF
     */
    public function generarBoletas($id)
    {
        try {
            $planilla = Planilla::with(['detalles' => function($query) {
                    $query->where('estado', '!=', 0);
                }, 'detalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            $pdf = PDF::loadView('pdf.boletas-pago', [
                'planilla' => $planilla,
                'empresa' => $planilla->empresa,
                'sucursal' => $planilla->sucursal,
                'detalles' => $planilla->detalles
            ]);

            $pdf->setPaper('letter', 'portrait');
            $pdf->setOptions([
                'enable_php' => true,
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);

            return $pdf->stream("boletas_planilla_{$planilla->codigo}.pdf");
        } catch (\Exception $e) {
            Log::error('Error generando boletas: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generar boleta individual
     */
    public function generarBoletaIndividual($id_detalle)
    {
        try {
            $detalle = PlanillaDetalle::with(['empleado', 'planilla'])->findOrFail($id_detalle);

            $totalIngresos = $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos;

            $totalDescuentos = $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales;

            $pdf = PDF::loadView('pdf.boleta-pago-individual', [
                'detalle' => $detalle,
                'empleado' => $detalle->empleado,
                'planilla' => $detalle->planilla,
                'total_ingresos' => $totalIngresos,
                'total_descuentos' => $totalDescuentos,
                'sueldo_neto' => $detalle->sueldo_neto
            ]);

            $pdf->setPaper('letter', 'portrait');

            return $pdf->stream("boleta_{$detalle->empleado->codigo}_{$detalle->planilla->codigo}.pdf");
        } catch (\Exception $e) {
            Log::error('Error generando boleta individual: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener descuentos patronales
     */
    public function obtenerDescuentosPatronales($id)
    {
        try {
            $planilla = Planilla::with(['detalles.empleado', 'empresa'])->findOrFail($id);

            $descuentos = PlanillaDetalle::where('id_planilla', $id)
                ->where('estado', '!=', 0)
                ->selectRaw('
                    SUM(isss_patronal) as total_isss_patronal,
                    SUM(afp_patronal) as total_afp_patronal,
                    SUM(isss_patronal + afp_patronal) as total_descuentos_patronales
                ')
                ->first();

            return [
                'planilla' => $planilla,
                'descuentos' => $descuentos
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo descuentos patronales: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Exportar detalles de planilla
     */
    public function exportarDetalles($planillaId, $filtros = [])
    {
        try {
            $planilla = Planilla::with(['empresa', 'sucursal'])->findOrFail($planillaId);

            // Verificar permisos
            if ($planilla->id_empresa !== auth()->user()->id_empresa ||
                $planilla->id_sucursal !== auth()->user()->id_sucursal) {
                throw new \Exception('No tiene permisos para exportar esta planilla');
            }

            $vista = $filtros['vista'] ?? 'empleados';

            // Construir query base
            $query = PlanillaDetalle::where('id_planilla', $planilla->id)
                ->join('empleados', 'planilla_detalles.id_empleado', '=', 'empleados.id')
                ->leftJoin('cargos_de_empresa', 'empleados.id_cargo', '=', 'cargos_de_empresa.id')
                ->leftJoin('departamentos_empresa', 'empleados.id_departamento', '=', 'departamentos_empresa.id')
                ->where('planilla_detalles.estado', '!=', 0);

            // Aplicar filtros
            if (isset($filtros['buscador'])) {
                $buscador = $filtros['buscador'];
                $query->where(function($q) use ($buscador) {
                    $q->where('empleados.nombres', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.apellidos', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.codigo', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.dui', 'LIKE', "%{$buscador}%");
                });
            }

            if (isset($filtros['id_departamento'])) {
                $query->where('empleados.id_departamento', $filtros['id_departamento']);
            }

            if (isset($filtros['id_cargo'])) {
                $query->where('empleados.id_cargo', $filtros['id_cargo']);
            }

            if (isset($filtros['estado'])) {
                $query->where('planilla_detalles.estado', $filtros['estado']);
            }

            // Seleccionar campos según la vista
            if ($vista === 'descuentos_patronales') {
                $query->select([
                    'planilla_detalles.id',
                    'planilla_detalles.salario_base',
                    'planilla_detalles.salario_devengado',
                    'planilla_detalles.isss_patronal',
                    'planilla_detalles.afp_patronal',
                    'empleados.codigo as empleado_codigo',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.dui',
                    'empleados.nit',
                    'empleados.isss as empleado_isss',
                    'empleados.afp as empleado_afp',
                    'cargos_de_empresa.nombre as cargo_nombre',
                    'departamentos_empresa.nombre as departamento_nombre'
                ]);

                $exportClass = new DescuentosPatronalesExport($planilla, $query->orderBy('empleados.nombres')->get());
                $filename = 'descuentos_patronales_' . $planilla->codigo . '.xlsx';
            } else {
                $query->select([
                    'planilla_detalles.*',
                    'empleados.codigo as empleado_codigo',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.dui',
                    'empleados.nit',
                    'empleados.isss as empleado_isss',
                    'empleados.afp as empleado_afp',
                    'cargos_de_empresa.nombre as cargo_nombre',
                    'departamentos_empresa.nombre as departamento_nombre'
                ]);

                $exportClass = new PlanillaDetallesExport($planilla, $query->orderBy('empleados.nombres')->get());
                $filename = 'planilla_empleados_' . $planilla->codigo . '.xlsx';
            }

            return Excel::download($exportClass, $filename);
        } catch (\Exception $e) {
            Log::error('Error exportando detalles de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Descargar plantilla de importación
     */
    public function descargarPlantilla()
    {
        try {
            $headers = [
                'codigo' => 'Código Empleado',
                'nombres_y_apellidos' => 'Nombres y Apellidos',
                'salario_base' => 'Salario Base',
                'dias_laborados' => 'Días Laborados',
                'comisiones' => 'Comisiones',
                'horas_extra' => 'Horas Extra',
                'monto_horas_extra' => 'Monto Horas Extra',
                'total_horas_extras' => 'Total Horas Extras',
                'bonificaciones' => 'Bonificaciones',
                'otros_ingresos' => 'Otros Ingresos',
                'prestamos' => 'Préstamos',
                'anticipos' => 'Anticipos',
                'descuentos_judiciales' => 'Descuentos Judiciales',
                'sub_total' => 'Sub Total',
                'isss' => 'ISSS',
                'afp' => 'AFP',
                'renta' => 'RENTA',
                'otras_deducciones' => 'Otras Deducciones',
                'detalle_de_otras_deducciones' => 'Detalle de Otras Deducciones',
                'total_neto' => 'Total Neto',
                'firma' => 'Firma'
            ];

            $empleados = Empleado::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO)
                ->get();

            $data = [];
            foreach ($empleados as $empleado) {
                $data[] = [
                    $empleado->codigo,
                    $empleado->nombres . ' ' . $empleado->apellidos,
                    $empleado->salario_base,
                    30,
                    0, 0, 0, 0, 0, 0, 0, 0, 0,
                    $empleado->salario_base,
                    $this->calcularISSSEmpleado($empleado->salario_base),
                    $this->calcularAFPEmpleado($empleado->salario_base),
                    $this->calcularRentaImportacion($empleado->salario_base),
                    0, '', 0, ''
                ];
            }

            // Agregar fila de totales
            $data[] = [
                '', 'TOTAL',
                '=SUM(C2:C' . (count($data) + 1) . ')',
                '', '=SUM(E2:E' . (count($data) + 1) . ')',
                '=SUM(F2:F' . (count($data) + 1) . ')',
                '=SUM(G2:G' . (count($data) + 1) . ')',
                '=SUM(H2:H' . (count($data) + 1) . ')',
                '=SUM(I2:I' . (count($data) + 1) . ')',
                '=SUM(J2:J' . (count($data) + 1) . ')',
                '=SUM(K2:K' . (count($data) + 1) . ')',
                '=SUM(L2:L' . (count($data) + 1) . ')',
                '=SUM(M2:M' . (count($data) + 1) . ')',
                '=SUM(N2:N' . (count($data) + 1) . ')',
                '=SUM(O2:O' . (count($data) + 1) . ')',
                '=SUM(P2:P' . (count($data) + 1) . ')',
                '=SUM(Q2:Q' . (count($data) + 1) . ')',
                '=SUM(R2:R' . (count($data) + 1) . ')',
                '', '=SUM(T2:T' . (count($data) + 1) . ')', ''
            ];

            return Excel::download(
                new PlanillaExportTemplate($headers, $data),
                'plantilla_importacion_planillas.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('Error descargando plantilla: ' . $e->getMessage());
            throw $e;
        }
    }

    private function calcularRentaImportacion($salario)
    {
        $baseImponible = $salario -
            $this->calcularISSSEmpleado($salario) -
            $this->calcularAFPEmpleado($salario);

        if ($baseImponible <= PlanillaConstants::RENTA_MINIMA) {
            return 0;
        } elseif ($baseImponible <= PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) {
            return round((($baseImponible - PlanillaConstants::RENTA_MINIMA) *
                PlanillaConstants::PORCENTAJE_PRIMER_TRAMO) +
                PlanillaConstants::IMPUESTO_PRIMER_TRAMO, 2);
        } elseif ($baseImponible <= PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) {
            return round((($baseImponible - PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) *
                PlanillaConstants::PORCENTAJE_SEGUNDO_TRAMO) +
                PlanillaConstants::IMPUESTO_SEGUNDO_TRAMO, 2);
        } else {
            return round((($baseImponible - PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) *
                PlanillaConstants::PORCENTAJE_TERCER_TRAMO) +
                PlanillaConstants::IMPUESTO_TERCER_TRAMO, 2);
        }
    }

    private function calcularISSSEmpleado($salario)
    {
        $baseISSSEmpleado = min($salario, 1000);
        return round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
    }

    private function calcularAFPEmpleado($salario)
    {
        return round($salario * PlanillaConstants::DESCUENTO_AFP_EMPLEADO, 2);
    }
}


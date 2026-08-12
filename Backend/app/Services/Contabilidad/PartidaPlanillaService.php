<?php

namespace App\Services\Contabilidad;

use App\Helpers\CostaRicaCargasSocialesHelper;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle as DetallePartida;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartidaPlanillaService
{
    /**
     * Generar la partida contable automática de planilla en estado 'Pendiente'
     *
     * @param Planilla $planilla
     * @param int|null $idUsuario
     * @return Partida|null
     */
    public function generarPartidaPlanilla(Planilla $planilla, ?int $idUsuario = null): ?Partida
    {
        DB::beginTransaction();
        try {
            // Verificar si ya existe partida para esta planilla
            if (Partida::existeParaDocumentoOrigen('Planilla', $planilla->id)) {
                Log::info("La planilla {$planilla->codigo} ya tiene una partida contable asociada.");
                DB::rollBack();
                return Partida::where('referencia', 'Planilla')
                    ->where('id_referencia', $planilla->id)
                    ->first();
            }

            $empresa = $planilla->empresa;
            $codPais = EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa($empresa);
            $configContable = Configuracion::where('id_empresa', $planilla->id_empresa)->first();

            $detalles = PlanillaDetalle::where('id_planilla', $planilla->id)
                ->where('estado', '!=', 0)
                ->get();

            if ($detalles->isEmpty()) {
                DB::rollBack();
                return null;
            }

            // Totales base
            $totalSalarioBruto = round($detalles->sum('total_ingresos'), 2);
            $totalRenta = round($detalles->sum('renta'), 2);
            $totalSueldoNeto = round($detalles->sum('sueldo_neto'), 2);

            $totalCargasEmpleado = 0.0;
            $totalCargasPatronales = 0.0;
            $totalInsPatronal = 0.0;

            if ($codPais === 'CR') {
                foreach ($detalles as $det) {
                    $salarioBrutoDet = $det->total_ingresos ?? $det->salario_devengado ?? 0;
                    $cargas = CostaRicaCargasSocialesHelper::calcularCargasSociales(
                        $salarioBrutoDet,
                        $planilla->tipo_planilla ?? 'mensual'
                    );
                    $totalCargasEmpleado += $cargas['ccss_empleado'];
                    $totalCargasPatronales += $cargas['ccss_patronal'];
                    $totalInsPatronal += $cargas['ins_patronal'];
                }
            } else {
                // El Salvador
                $totalIsssEmp = $detalles->sum('isss_empleado');
                $totalAfpEmp = $detalles->sum('afp_empleado');
                $totalIsssPat = $detalles->sum('isss_patronal');
                $totalAfpPat = $detalles->sum('afp_patronal');

                $totalCargasEmpleado = $totalIsssEmp + $totalAfpEmp;
                $totalCargasPatronales = $totalIsssPat + $totalAfpPat;
            }

            $totalCargasEmpleado = round($totalCargasEmpleado, 2);
            $totalCargasPatronales = round($totalCargasPatronales, 2);
            $totalInsPatronal = round($totalInsPatronal, 2);

            // Opción A: Devengo Mensual de Provisión de Aguinaldo y Vacaciones (1/12 = 8.33% y ~4.17%)
            $provisionAguinaldo = round($totalSalarioBruto / 12.0, 2);
            $provisionVacaciones = round($totalSalarioBruto * (15.0 / 360.0), 2);

            // Crear encabezado de la Partida en estado 'Pendiente'
            $partida = new Partida();
            $partida->fecha = $planilla->fecha_fin ?? now()->format('Y-m-d');
            $partida->tipo = 'Diario';
            $partida->concepto = "Partida automática de Planilla {$planilla->codigo} ({$planilla->tipo_planilla}) - Período " .
                date('d/m/Y', strtotime($planilla->fecha_inicio)) . " al " . date('d/m/Y', strtotime($planilla->fecha_fin));
            $partida->estado = 'Pendiente'; // Estado "Pendiente" para revisión y aprobación por el Contador
            $partida->referencia = 'Planilla';
            $partida->id_referencia = $planilla->id;
            $partida->id_empresa = $planilla->id_empresa;
            $partida->id_usuario = $idUsuario ?? auth()->id() ?? 1;
            $partida->save();

            // Mapeo de cuentas (IDs parametrizados o nulos en catálogo)
            $cuentaGastoSalarios = $configContable->id_cuenta_gasto_salarios ?? null;
            $cuentaGastoCargasPatronales = $configContable->id_cuenta_gasto_cargas_patronales ?? null;
            $cuentaGastoAguinaldo = $configContable->id_cuenta_gasto_aguinaldo ?? null;
            $cuentaGastoVacaciones = $configContable->id_cuenta_gasto_vacaciones ?? null;

            $cuentaPasivoCargasSociales = $configContable->id_cuenta_pasivo_cargas_sociales ?? null;
            $cuentaPasivoIns = $configContable->id_cuenta_pasivo_ins ?? null;
            $cuentaPasivoRenta = $configContable->id_cuenta_pasivo_retencion_renta ?? null;
            $cuentaPasivoSalariosPorPagar = $configContable->id_cuenta_pasivo_salarios_por_pagar ?? null;
            $cuentaPasivoProvAguinaldo = $configContable->id_cuenta_pasivo_provision_aguinaldo ?? null;
            $cuentaPasivoProvVacaciones = $configContable->id_cuenta_pasivo_provision_vacaciones ?? null;

            // Arreglo de líneas de la partida
            $lineas = [
                // --- DEBE (Gastos y Provisiones) ---
                [
                    'concepto' => 'Gasto de Sueldos y Salarios',
                    'id_cuenta' => $cuentaGastoSalarios,
                    'debe' => $totalSalarioBruto,
                    'haber' => 0.00
                ],
                [
                    'concepto' => ($codPais === 'CR') ? 'Gasto de Cargas Sociales Patronales (CCSS + INS)' : 'Gasto Cargas Patronales (ISSS + AFP)',
                    'id_cuenta' => $cuentaGastoCargasPatronales,
                    'debe' => round($totalCargasPatronales + $totalInsPatronal, 2),
                    'haber' => 0.00
                ],
                [
                    'concepto' => 'Gasto Provisión de Aguinaldo (Devengo)',
                    'id_cuenta' => $cuentaGastoAguinaldo,
                    'debe' => $provisionAguinaldo,
                    'haber' => 0.00
                ],
                [
                    'concepto' => 'Gasto Provisión de Vacaciones (Devengo)',
                    'id_cuenta' => $cuentaGastoVacaciones,
                    'debe' => $provisionVacaciones,
                    'haber' => 0.00
                ],

                // --- HABER (Pasivos, Retenciones y Provisiones) ---
                [
                    'concepto' => ($codPais === 'CR') ? 'Retención y Cuota CCSS por Pagar (Obrero + Patronal)' : 'ISSS y AFP por Pagar (Obrero + Patronal)',
                    'id_cuenta' => $cuentaPasivoCargasSociales,
                    'debe' => 0.00,
                    'haber' => round($totalCargasEmpleado + $totalCargasPatronales, 2)
                ],
            ];

            if ($codPais === 'CR' && $totalInsPatronal > 0) {
                $lineas[] = [
                    'concepto' => 'INS Riesgos del Trabajo por Pagar',
                    'id_cuenta' => $cuentaPasivoIns ?? $cuentaPasivoCargasSociales,
                    'debe' => 0.00,
                    'haber' => $totalInsPatronal
                ];
            }

            $lineas[] = [
                'concepto' => 'Retención de Impuesto sobre la Renta por Pagar',
                'id_cuenta' => $cuentaPasivoRenta,
                'debe' => 0.00,
                'haber' => $totalRenta
            ];

            $lineas[] = [
                'concepto' => 'Salarios Netos por Pagar',
                'id_cuenta' => $cuentaPasivoSalariosPorPagar,
                'debe' => 0.00,
                'haber' => $totalSueldoNeto
            ];

            $lineas[] = [
                'concepto' => 'Provisión de Aguinaldo por Pagar',
                'id_cuenta' => $cuentaPasivoProvAguinaldo,
                'debe' => 0.00,
                'haber' => $provisionAguinaldo
            ];

            $lineas[] = [
                'concepto' => 'Provisión de Vacaciones por Pagar',
                'id_cuenta' => $cuentaPasivoProvVacaciones,
                'debe' => 0.00,
                'haber' => $provisionVacaciones
            ];

            // Cuadre exacto de centavos entre DEBE y HABER
            $sumDebe = array_sum(array_column($lineas, 'debe'));
            $sumHaber = array_sum(array_column($lineas, 'haber'));
            $diferencia = round($sumDebe - $sumHaber, 2);

            if ($diferencia != 0) {
                // Ajustar descuadre de 1 centavo en Salarios Netos por Pagar
                foreach ($lineas as &$linea) {
                    if ($linea['concepto'] === 'Salarios Netos por Pagar') {
                        $linea['haber'] = round($linea['haber'] + $diferencia, 2);
                        break;
                    }
                }
            }

            // Insertar líneas en partida_detalles
            foreach ($lineas as $l) {
                DetallePartida::create([
                    'id_partida' => $partida->id,
                    'concepto' => $l['concepto'],
                    'id_cuenta' => $l['id_cuenta'],
                    'debe' => $l['debe'],
                    'haber' => $l['haber'],
                ]);
            }

            DB::commit();

            Log::info("Partida contable automática creada exitosamente para la planilla {$planilla->codigo} (ID Partida: {$partida->id}, Estado: Pendiente)");

            return $partida->fresh(['detalles']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generando partida contable automática de planilla: ' . $e->getMessage(), [
                'planilla_id' => $planilla->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

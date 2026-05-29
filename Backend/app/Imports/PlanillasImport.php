<?php

namespace App\Imports;

use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\Empleado;
use App\Models\Planilla\DepartamentoEmpresa;
use App\Models\Planilla\CargoEmpresa;
use App\Constants\PlanillaConstants;
use App\Helpers\RentaHelper;
use App\Services\Planilla\ConfiguracionPlanillaService;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanillasImport implements ToCollection, WithHeadingRow, WithEvents
{
    protected $planilla;
    protected $data;
    protected $transactionStarted = false;
    protected $configuracionPlanillaService;

    public function __construct($data)
    {
        $this->data = $data;
        $this->configuracionPlanillaService = app(ConfiguracionPlanillaService::class);
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {
                // Iniciar transacción antes de procesar el archivo
                DB::beginTransaction();
                $this->transactionStarted = true;
                
                // Crear o obtener la planilla una sola vez al inicio
                $codigoPlanilla = $this->generarCodigoPlanilla();
                $this->planilla = Planilla::where('codigo', $codigoPlanilla)
                    ->where('id_empresa', $this->data['empresa_id'])
                    ->where('id_sucursal', $this->data['sucursal_id'])
                    ->first();
                
                // Si no existe, crear una nueva
                if (!$this->planilla) {
                    $this->planilla = $this->crearPlanilla();
                    Log::info('Planilla creada para importación', [
                        'id' => $this->planilla->id,
                        'codigo' => $this->planilla->codigo
                    ]);
                } else {
                    // Si la planilla ya existe, eliminar detalles existentes para evitar duplicados
                    // Esto permite re-importar la misma planilla limpiamente
                    $detallesEliminados = PlanillaDetalle::where('id_planilla', $this->planilla->id)->delete();
                    Log::info('Planilla existente encontrada para importación, detalles anteriores eliminados', [
                        'id' => $this->planilla->id,
                        'codigo' => $this->planilla->codigo,
                        'detalles_eliminados' => $detallesEliminados
                    ]);
                }
            },
            AfterImport::class => function(AfterImport $event) {
                try {
                    // Actualizar totales y hacer commit al finalizar toda la importación
                    if ($this->planilla) {
                        $this->actualizarTotalesPlanilla();
                        Log::info('Totales de planilla actualizados', [
                            'planilla_id' => $this->planilla->id,
                            'total_salarios' => $this->planilla->total_salarios,
                            'total_deducciones' => $this->planilla->total_deducciones,
                            'total_neto' => $this->planilla->total_neto
                        ]);
                    }
                    
                    if ($this->transactionStarted) {
                        DB::commit();
                        $this->transactionStarted = false;
                    }
                } catch (\Exception $e) {
                    if ($this->transactionStarted) {
                        DB::rollback();
                        $this->transactionStarted = false;
                    }
                    Log::error('Error en AfterImport de PlanillasImport', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            },
        ];
    }

    public function collection(Collection $rows)
    {
        try {
            // Asegurar que la planilla existe (debería haberse creado en BeforeImport)
            if (!$this->planilla) {
                // Fallback: crear planilla si no se creó en BeforeImport
                $codigoPlanilla = $this->generarCodigoPlanilla();
                $this->planilla = Planilla::where('codigo', $codigoPlanilla)
                    ->where('id_empresa', $this->data['empresa_id'])
                    ->where('id_sucursal', $this->data['sucursal_id'])
                    ->first();
                
                if (!$this->planilla) {
                    if (!$this->transactionStarted) {
                        DB::beginTransaction();
                        $this->transactionStarted = true;
                    }
                    $this->planilla = $this->crearPlanilla();
                }
            }

            // Procesar cada fila
            foreach ($rows as $row) {
                // Saltar fila si está vacía o es el total
                if ($this->isEmptyRow($row) || $this->isTotalRow($row)) {
                    continue;
                }
                
                // Validar que tenga nombre completo antes de procesar
                $nombreCompleto = $this->obtenerNombreCompleto($row);
                if (empty($nombreCompleto)) {
                    continue;
                }

                // Procesar y guardar detalle
                $detalle = $this->procesarDetalle($row);
            }
            
            return true;
        } catch (\Exception $e) {
            if ($this->transactionStarted) {
            DB::rollback();
                $this->transactionStarted = false;
            }
            Log::error('Error en collection de PlanillasImport', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function crearPlanilla()
    {
        return Planilla::create([
            'codigo' => $this->generarCodigoPlanilla(),
            'fecha_inicio' => $this->data['fecha_inicio'],
            'fecha_fin' => $this->data['fecha_fin'],
            'tipo_planilla' => $this->data['tipo_planilla'],
            'estado' => PlanillaConstants::PLANILLA_BORRADOR,
            'id_empresa' => $this->data['empresa_id'],
            'id_sucursal' => $this->data['sucursal_id'],
            'anio' => Carbon::parse($this->data['fecha_inicio'])->year,
            'mes' => Carbon::parse($this->data['fecha_inicio'])->month
        ]);
    }

    protected function procesarDetalle($row)
    {

        $this->logRowData($row);
        // Obtener nombre completo - puede venir como una columna o combinado
        $nombreCompleto = $this->obtenerNombreCompleto($row);
        
        // Obtener código de empleado si está disponible
        $codigoEmpleado = $this->obtenerValorColumna($row, ['codigo_empleado', 'codigo empleado', 'codigo'], null);
        
        // Buscar empleado por código primero, luego por nombre, si no existe lo crea
        $empleado = $this->buscarOcrearEmpleado($row, $nombreCompleto, $codigoEmpleado);

        // Procesar montos
        $salario_base = $this->limpiarMonto($this->obtenerValorColumna($row, ['salario_base', 'salario base'], 0));
        $dias_laborados = intval($this->obtenerValorColumna($row, ['dias_laborados', 'dias laborados', 'días_laborados', 'días laborados'], 0));
        
        // Ajustar días de referencia según el tipo de planilla
        $dias_referencia = 30; // Por defecto mensual
        $salario_base_ajustado = $salario_base;
        
        switch ($this->data['tipo_planilla']) {
            case 'quincenal':
                $dias_referencia = 15;
                // Si el salario base es mensual, dividirlo entre 2 para quincenal
                // Solo si no viene ya ajustado en el Excel
                if ($salario_base > 0 && $dias_laborados <= 15) {
                    $salario_base_ajustado = $salario_base / 2;
                }
                break;
            case 'semanal':
                $dias_referencia = 7;
                if ($salario_base > 0 && $dias_laborados <= 7) {
                    $salario_base_ajustado = $salario_base / 4.33;
                }
                break;
            default: // mensual
                $dias_referencia = 30;
                break;
        }
        
        // Calcular salario devengado según días de referencia
        if ($dias_laborados > 0) {
            $salario_devengado = ($salario_base_ajustado / $dias_referencia) * $dias_laborados;
        } else {
            // Si no hay días laborados, usar el salario base ajustado completo
            $salario_devengado = $salario_base_ajustado;
        }

        // Ingresos adicionales
        $comisiones = $this->limpiarMonto($row['comisiones'] ?? 0);
        $horas_extra = $this->limpiarMonto($row['horas_extra'] ?? 0);
        $monto_horas_extra = $this->limpiarMonto($row['monto_horas_extra'] ?? 0);
        $total_horas_extras = $this->limpiarMonto($row['total_horas_extras'] ?? 0);
        $bonificaciones = $this->limpiarMonto($row['bonificaciones'] ?? 0);
        $otros_ingresos = $this->limpiarMonto($row['otros_ingresos'] ?? 0);

        // Total ingresos (igual que en el controlador)
        $total_ingresos = $salario_devengado + $total_horas_extras +
            $comisiones + $bonificaciones + $otros_ingresos;

        // Otras deducciones (tomar del Excel)
        $prestamos = $this->limpiarMonto($row['prestamos'] ?? 0);
        $anticipos = $this->limpiarMonto($row['anticipos'] ?? 0);
        $descuentos_judiciales = $this->limpiarMonto($row['descuentos_judiciales'] ?? 0);
        $otros_descuentos = $this->limpiarMonto($row['otras_deducciones'] ?? 0);
        
        // Preparar datos del empleado para el servicio (igual que en crearDetallePlanilla)
        $datosEmpleado = [
            'salario_base' => $salario_base,
            'salario_devengado' => $salario_devengado,
            'dias_laborados' => $dias_laborados,
            'horas_extra' => $horas_extra,
            'monto_horas_extra' => $total_horas_extras,
            'comisiones' => $comisiones,
            'bonificaciones' => $bonificaciones,
            'otros_ingresos' => $otros_ingresos,
            'prestamos' => $prestamos,
            'anticipos' => $anticipos,
            'otros_descuentos' => $otros_descuentos,
            'descuentos_judiciales' => $descuentos_judiciales,
            'tipo_contrato' => $empleado->tipo_contrato ?? null,
        ];
        
        // Usar el mismo servicio que usa el controlador para calcular conceptos
        // Esto asegura consistencia con la creación manual de planillas
        $resultados = $this->configuracionPlanillaService->calcularConceptos(
            $datosEmpleado,
            $this->data['empresa_id'],
            $this->data['tipo_planilla']
        );
        
        // Obtener valores calculados del servicio
        $isss = $resultados['isss_empleado'] ?? 0;
        $afp = $resultados['afp_empleado'] ?? 0;
        $renta = $resultados['renta'] ?? 0;
        $isss_patronal = $resultados['isss_patronal'] ?? 0;
        $afp_patronal = $resultados['afp_patronal'] ?? 0;
        
        // Si vienen valores en el Excel, respetarlos (pero validar que sean correctos)
        // Esto permite importar planillas ya calculadas externamente
        $isss_excel = $this->limpiarMonto($row['isss'] ?? 0);
        $afp_excel = $this->limpiarMonto($row['afp'] ?? 0);
        $renta_excel = $this->limpiarMonto($row['renta'] ?? 0);
        
        // Si vienen valores en el Excel y son diferentes de los calculados, usar los del Excel
        // pero solo si son razonables (no cero cuando deberían tener valor)
        if ($isss_excel > 0 && abs($isss_excel - $isss) > 0.01) {
            Log::info('ISSS del Excel diferente al calculado, usando valor del Excel', [
                'isss_excel' => $isss_excel,
                'isss_calculado' => $isss,
                'empleado' => $empleado->codigo
            ]);
            $isss = $isss_excel;
        }
        
        if ($afp_excel > 0 && abs($afp_excel - $afp) > 0.01) {
            Log::info('AFP del Excel diferente al calculado, usando valor del Excel', [
                'afp_excel' => $afp_excel,
                'afp_calculado' => $afp,
                'empleado' => $empleado->codigo
            ]);
            $afp = $afp_excel;
        }
        
        // Para renta, SIEMPRE recalcular usando RentaHelper para asegurar que use las tablas correctas
        // según el tipo de planilla (quincenal/mensual). Esto es crítico porque las tablas son diferentes.
        // El servicio ya calculó la renta, pero la recalculamos aquí para asegurar que use los valores
        // finales de ISSS y AFP (que pueden haber sido tomados del Excel)
        $salario_gravado = RentaHelper::calcularSalarioGravado(
            $total_ingresos,
            $isss,
            $afp,
            $this->data['tipo_planilla'],
            $empleado->tipo_contrato ?? null
        );
        $renta = RentaHelper::calcularRetencionRenta(
            $salario_gravado,
            $this->data['tipo_planilla'],
            $empleado->tipo_contrato ?? null
        );
        
        // Recalcular los aportes patronales con los valores finales de ingresos
        // para asegurar consistencia (el servicio los calculó con salario_devengado, pero aquí
        // debemos usar total_ingresos que incluye horas extra, comisiones, etc.)
        $baseISSSPatronal = min($total_ingresos, 1000);
        $isss_patronal = round($baseISSSPatronal * PlanillaConstants::DESCUENTO_ISSS_PATRONO, 2);
        $afp_patronal = round($total_ingresos * PlanillaConstants::DESCUENTO_AFP_PATRONO, 2);

        // Total deducciones (redondear a 2 decimales)
        $total_deducciones = round($isss + $afp + $renta + $prestamos + $anticipos +
            $descuentos_judiciales + $otros_descuentos, 2);

        // Total neto (redondear a 2 decimales)
        $total_neto = round($total_ingresos - $total_deducciones, 2);

        // Verificar si ya existe un detalle para este empleado en esta planilla
        // Esto evita duplicados cuando se importa la misma planilla múltiples veces
        $detalleExistente = PlanillaDetalle::where('id_planilla', $this->planilla->id)
            ->where('id_empleado', $empleado->id)
            ->first();

        $datosDetalle = [
            'salario_base' => round($salario_base, 2),
            'dias_laborados' => $dias_laborados,
            'salario_devengado' => round($salario_devengado, 2),
            'horas_extra' => round($horas_extra, 2),
            'monto_horas_extra' => round($monto_horas_extra, 2),
            'comisiones' => round($comisiones, 2),
            'bonificaciones' => round($bonificaciones, 2),
            'otros_ingresos' => round($otros_ingresos, 2),
            'viaticos' => round($this->limpiarMonto($row['viaticos'] ?? 0), 2),
            'total_ingresos' => round($total_ingresos, 2),
            'isss_empleado' => round($isss, 2),
            'isss_patronal' => round($isss_patronal, 2),
            'afp_empleado' => round($afp, 2),
            'afp_patronal' => round($afp_patronal, 2),
            'renta' => round($renta, 2),
            'prestamos' => round($prestamos, 2),
            'anticipos' => round($anticipos, 2),
            'descuentos_judiciales' => round($descuentos_judiciales, 2),
            'otros_descuentos' => round($otros_descuentos, 2),
            'detalle_otras_deducciones' => $row['detalle_de_otras_deducciones'] ?? '',
            'total_descuentos' => $total_deducciones,
            'sueldo_neto' => $total_neto,
            'estado' => PlanillaConstants::PLANILLA_BORRADOR
        ];

        // Log para debug
        Log::debug('Datos de detalle calculados usando ConfiguracionPlanillaService', [
            'empleado' => $empleado->codigo . ' - ' . $empleado->nombres . ' ' . $empleado->apellidos,
            'tipo_planilla' => $this->data['tipo_planilla'],
            'tipo_contrato' => $empleado->tipo_contrato ?? 'N/A',
            'total_ingresos' => $datosDetalle['total_ingresos'],
            'total_descuentos' => $datosDetalle['total_descuentos'],
            'sueldo_neto' => $datosDetalle['sueldo_neto'],
            'calculo_renta' => [
                'salario_gravado' => $salario_gravado,
                'renta_calculada' => $renta,
                'tipo_planilla_usado' => $this->data['tipo_planilla'],
                'tablas_aplicadas' => $this->data['tipo_planilla'] === 'quincenal' ? 'Quincenal' : 'Mensual'
            ],
            'valores_finales' => [
                'isss' => $isss,
                'afp' => $afp,
                'renta' => $renta,
                'isss_patronal' => $isss_patronal,
                'afp_patronal' => $afp_patronal
            ],
            'otras_deducciones' => [
                'prestamos' => $prestamos,
                'anticipos' => $anticipos,
                'descuentos_judiciales' => $descuentos_judiciales,
                'otros_descuentos' => $otros_descuentos
            ]
        ]);

        if ($detalleExistente) {
            // Si ya existe un detalle para este empleado, actualizarlo en lugar de crear uno nuevo
            Log::info('Detalle ya existe para este empleado en esta planilla, actualizando', [
                'planilla_id' => $this->planilla->id,
                'empleado_id' => $empleado->id,
                'empleado_codigo' => $empleado->codigo,
                'empleado_nombre' => $empleado->nombres . ' ' . $empleado->apellidos,
                'detalle_id' => $detalleExistente->id
            ]);
            
            $detalleExistente->update($datosDetalle);
            return $detalleExistente;
        }

        // Si no existe, crear un nuevo detalle
        Log::info('Creando nuevo detalle de planilla', [
            'planilla_id' => $this->planilla->id,
            'empleado_id' => $empleado->id,
            'empleado_codigo' => $empleado->codigo,
            'empleado_nombre' => $empleado->nombres . ' ' . $empleado->apellidos
        ]);

        return PlanillaDetalle::create(array_merge([
            'id_planilla' => $this->planilla->id,
            'id_empleado' => $empleado->id
        ], $datosDetalle));
    }

    protected function logRowData($row)
    {
        Log::info('Row data:', [
            'columns' => array_keys($row->toArray()),
            'values' => $row->toArray()
        ]);
    }

    protected function acumularTotales(&$totales, $detalle)
    {
        $totales['salario_base'] += $detalle->salario_base;
        $totales['comisiones'] += $detalle->comisiones;
        $totales['total_horas_extras'] += $detalle->total_horas_extras;
        $totales['bonificaciones'] += $detalle->bonificaciones;
        $totales['otros_ingresos'] += $detalle->otros_ingresos;
        $totales['total_ingresos'] += $detalle->total_ingresos;
        $totales['isss'] += $detalle->isss_empleado;
        $totales['afp'] += $detalle->afp_empleado;
        $totales['renta'] += $detalle->renta;
        $totales['prestamos'] += $detalle->prestamos;
        $totales['anticipos'] += $detalle->anticipos;
        $totales['descuentos_judiciales'] += $detalle->descuentos_judiciales;
        $totales['otros_descuentos'] += $detalle->otros_descuentos;
        $totales['total_deducciones'] += $detalle->total_descuentos;
        $totales['total_neto'] += $detalle->sueldo_neto;
    }

    protected function actualizarTotalesPlanilla()
    {
        if (!$this->planilla) {
            return;
        }

        // Calcular totales desde la base de datos para asegurar precisión
        // Usar COALESCE para manejar valores NULL y asegurar que siempre haya un número
        $totales = PlanillaDetalle::where('id_planilla', $this->planilla->id)
            ->selectRaw('
                COALESCE(SUM(total_ingresos), 0) as total_ingresos,
                COALESCE(SUM(total_descuentos), 0) as total_deducciones,
                COALESCE(SUM(sueldo_neto), 0) as total_neto,
                COALESCE(SUM(viaticos), 0) as total_viaticos,
                COALESCE(SUM(isss_patronal), 0) as total_isss_patronal,
                COALESCE(SUM(afp_patronal), 0) as total_afp_patronal
            ')
            ->first();

        // Calcular total de aportes patronales
        $total_aportes_patronales = ($totales->total_isss_patronal ?? 0) + ($totales->total_afp_patronal ?? 0);

        // Actualizar totales de la planilla
        $this->planilla->update([
            'total_salarios' => round($totales->total_ingresos ?? 0, 2),
            'total_deducciones' => round($totales->total_deducciones ?? 0, 2),
            'total_neto' => round($totales->total_neto ?? 0, 2),
            'total_viaticos' => round($totales->total_viaticos ?? 0, 2),
            'total_aportes_patronales' => round($total_aportes_patronales, 2)
        ]);

        Log::info('Totales de planilla calculados y actualizados', [
            'planilla_id' => $this->planilla->id,
            'total_salarios' => $this->planilla->total_salarios,
            'total_deducciones' => $this->planilla->total_deducciones,
            'total_neto' => $this->planilla->total_neto,
            'total_aportes_patronales' => $this->planilla->total_aportes_patronales
        ]);
    }

    protected function buscarOcrearEmpleado($row, $nombreCompleto, $codigoEmpleado = null)
    {
        // Normalizar nombre completo (quitar espacios extras)
        $nombreCompleto = preg_replace('/\s+/', ' ', trim($nombreCompleto));
        
        // Primero intentar buscar el empleado
        $empleado = $this->buscarEmpleado($nombreCompleto, $codigoEmpleado);
        
        // Si hay código en el Excel pero no se encontró por código, o si se encontró por nombre pero el código no coincide, crear uno nuevo
        if (!empty($codigoEmpleado) && (!$empleado || $empleado->codigo !== trim($codigoEmpleado))) {
            Log::info('Código de empleado no coincide, creando nuevo empleado', [
                'codigo_excel' => $codigoEmpleado,
                'codigo_encontrado' => $empleado ? $empleado->codigo : 'N/A',
                'nombre_buscado' => $nombreCompleto
            ]);
            $empleado = $this->crearEmpleadoDesdeExcel($row, $nombreCompleto, $codigoEmpleado);
        } elseif (!$empleado) {
            // Si no se encontró, crear uno nuevo
            $empleado = $this->crearEmpleadoDesdeExcel($row, $nombreCompleto, $codigoEmpleado);
        }
        
        return $empleado;
    }
    
    protected function buscarEmpleado($nombreCompleto, $codigoEmpleado = null)
    {
        // Normalizar nombre completo (quitar espacios extras)
        $nombreCompleto = preg_replace('/\s+/', ' ', trim($nombreCompleto));
    
        // Si hay código, buscar SOLO por código (más confiable)
        if (!empty($codigoEmpleado)) {
            $codigoEmpleado = trim($codigoEmpleado);
            $empleado = Empleado::where('codigo', $codigoEmpleado)
                ->where('id_empresa', $this->data['empresa_id'])
                ->first();
            
            if ($empleado) {
                Log::info('Empleado encontrado por código', [
                    'codigo' => $codigoEmpleado,
                    'nombre_encontrado' => $empleado->nombre_completo
                ]);
                return $empleado;
            }
            
            Log::warning('Empleado no encontrado por código', [
                'codigo_buscado' => $codigoEmpleado,
                'empresa_id' => $this->data['empresa_id']
            ]);
            
            // Si hay código pero no se encontró, no buscar por nombre (el código es más confiable)
            return null;
        }
        
        // Si no hay código, buscar por nombre de manera más estricta
        if (empty($nombreCompleto) || strlen($nombreCompleto) < 3) {
            return null;
        }
        
        $partes = array_filter(explode(' ', $nombreCompleto), function($parte) {
            return strlen(trim($parte)) > 0;
        });
        $partes = array_values($partes); // Reindexar array
    
        $query = Empleado::where('id_empresa', $this->data['empresa_id']);
        
        // Búsqueda más estricta: buscar coincidencia exacta o muy cercana
        $empleado = $query->where(function ($q) use ($partes, $nombreCompleto) {
            // Búsqueda exacta del nombre completo (coincidencia exacta)
            $q->where(DB::raw("CONCAT(TRIM(nombres), ' ', TRIM(apellidos))"), '=', $nombreCompleto);
            $q->orWhere(DB::raw("CONCAT(TRIM(apellidos), ' ', TRIM(nombres))"), '=', $nombreCompleto);
            
            // Búsqueda normalizada exacta (sin acentos)
            $nombreNormalizado = $this->normalizarTexto($nombreCompleto);
            $q->orWhere(DB::raw("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(TRIM(nombres), ' ', TRIM(apellidos)), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))"), '=', strtolower($nombreNormalizado));
            
            // Si tiene al menos 2 partes, buscar por nombres y apellidos por separado (coincidencia exacta)
            if (count($partes) >= 2) {
                $posibleApellido = end($partes);
                $posiblesNombres = implode(' ', array_slice($partes, 0, -1));
                
                $q->orWhere(function($subQ) use ($posiblesNombres, $posibleApellido) {
                    $subQ->where('nombres', '=', $posiblesNombres)
                         ->where('apellidos', '=', $posibleApellido);
                });
                
                $primerNombre = $partes[0];
                $posiblesApellidos = implode(' ', array_slice($partes, 1));
                
                $q->orWhere(function($subQ) use ($primerNombre, $posiblesApellidos) {
                    $subQ->where('nombres', '=', $primerNombre)
                         ->where('apellidos', '=', $posiblesApellidos);
                });
            }
        })->first();
    
        if ($empleado) {
            Log::info('Empleado encontrado por nombre (búsqueda estricta)', [
                'nombre_buscado' => $nombreCompleto,
                'empleado_encontrado' => $empleado->nombre_completo,
                'codigo' => $empleado->codigo
            ]);
        }
    
        return $empleado;
    }
    
    protected function crearEmpleadoDesdeExcel($row, $nombreCompleto, $codigoEmpleado = null)
    {
        Log::info('Creando nuevo empleado desde Excel', [
            'nombre' => $nombreCompleto,
            'codigo' => $codigoEmpleado
        ]);
        
        // Extraer nombres y apellidos del nombre completo
        $partes = array_filter(explode(' ', trim($nombreCompleto)), function($parte) {
            return strlen(trim($parte)) > 0;
        });
        $partes = array_values($partes);
        
        // Intentar obtener nombres y apellidos desde columnas separadas primero
        $nombres = trim($this->obtenerValorColumna($row, ['nombres']) ?? '');
        $apellidos = trim($this->obtenerValorColumna($row, ['apellidos']) ?? '');
        
        // Si no están en columnas separadas, extraer del nombre completo
        if (empty($nombres) || empty($apellidos)) {
            if (count($partes) >= 2) {
                // Tomar el primer nombre como nombres, el resto como apellidos
                $nombres = $partes[0];
                $apellidos = implode(' ', array_slice($partes, 1));
            } elseif (count($partes) == 1) {
                // Solo hay un nombre, usarlo como nombre y apellido
                $nombres = $partes[0];
                $apellidos = $partes[0];
            } else {
                $nombres = 'Sin nombre';
                $apellidos = 'Sin apellido';
            }
        }
        
        // Obtener datos del Excel
        $dui = $this->obtenerValorColumna($row, ['documento_de_identidad', 'documento identidad', 'dui'], null);
        $nit = $this->obtenerValorColumna($row, ['nit'], null);
        $email = $this->obtenerValorColumna($row, ['correo', 'email'], null);
        $telefono = $this->obtenerValorColumna($row, ['telefono', 'teléfono'], null);
        $direccion = $this->obtenerValorColumna($row, ['direccion', 'dirección'], null);
        $fechaNacimiento = $this->obtenerValorColumna($row, ['fecha_nacimiento', 'fecha nacimiento'], null);
        $fechaInicio = $this->obtenerValorColumna($row, ['fecha_inicio', 'fecha inicio'], null);
        $salarioBase = $this->limpiarMonto($this->obtenerValorColumna($row, ['salario_base', 'salario base'], 0));
        $tipoJornada = $this->obtenerValorColumna($row, ['tipo_jornada', 'tipo jornada'], null);
        $tipoContrato = $this->obtenerValorColumna($row, ['tipo_contrato', 'tipo contrato'], null);
        
        // Generar código si no existe
        if (empty($codigoEmpleado)) {
            $codigoEmpleado = $this->generarCodigoEmpleado($nombres, $apellidos);
        }
        
        // Obtener o crear departamento por defecto
        $departamento = $this->obtenerOCrearDepartamento();
        
        // Obtener o crear cargo por defecto
        $cargo = $this->obtenerOCrearCargo($departamento->id);
        
        // Convertir tipo jornada y contrato
        $tipoJornadaId = $this->convertirTipoJornada($tipoJornada);
        $tipoContratoId = $this->convertirTipoContrato($tipoContrato);
        
        // Procesar fechas
        $fechaNacimientoFormateada = $this->procesarFecha($fechaNacimiento);
        $fechaInicioFormateada = $this->procesarFecha($fechaInicio) ?? Carbon::now()->format('Y-m-d');
        
        // Generar email si no existe
        if (empty($email)) {
            $email = $this->generarEmail($nombres, $apellidos, $codigoEmpleado);
        }
        
        // Verificar que el email sea único
        $email = $this->hacerEmailUnico($email);
        
        // Verificar que el DUI sea único o generar uno temporal
        if (!empty($dui)) {
            $dui = $this->hacerDuiUnico($dui);
        } else {
            $dui = $this->generarDuiTemporal($codigoEmpleado);
        }
        
        // Crear empleado
        $empleado = Empleado::create([
            'codigo' => $codigoEmpleado,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'dui' => $dui,
            'nit' => $nit ? $this->hacerNitUnico($nit) : null,
            'isss' => $this->obtenerValorColumna($row, ['isss'], null),
            'afp' => $this->obtenerValorColumna($row, ['afp'], null),
            'fecha_nacimiento' => $fechaNacimientoFormateada ?? Carbon::now()->subYears(25)->format('Y-m-d'),
            'direccion' => $direccion ?? 'Sin dirección',
            'telefono' => $telefono ?? '00000000',
            'email' => $email,
            'salario_base' => $salarioBase > 0 ? $salarioBase : 0,
            'tipo_contrato' => $tipoContratoId,
            'tipo_jornada' => $tipoJornadaId,
            'fecha_ingreso' => $fechaInicioFormateada,
            'estado' => PlanillaConstants::ESTADO_EMPLEADO_ACTIVO,
            'id_departamento' => $departamento->id,
            'id_cargo' => $cargo->id,
            'id_sucursal' => $this->data['sucursal_id'],
            'id_empresa' => $this->data['empresa_id'],
        ]);
        
        Log::info('Empleado creado exitosamente', [
            'id' => $empleado->id,
            'codigo' => $empleado->codigo,
            'nombre_completo' => $empleado->nombre_completo
        ]);
        
        return $empleado;
    }
    
    protected function normalizarTexto($texto)
    {
        // Quitar acentos y convertir a minúsculas para comparación
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
            $texto
        );
        return $texto;
    }

    protected function validarFormatoNombre($nombreCompleto)
    {
        if (strlen($nombreCompleto) < 5) { // Validar longitud mínima
            throw new \Exception("El nombre '$nombreCompleto' es demasiado corto.");
        }

        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombreCompleto)) {
            throw new \Exception("El nombre '$nombreCompleto' contiene caracteres no permitidos.");
        }

        return true;
    }

    protected function generarCodigoPlanilla()
    {
        $fecha = Carbon::parse($this->data['fecha_inicio']);
        $quincena = $fecha->day <= 15 ? '1' : '2';
        return 'PLA-' . $fecha->format('Ym') . $quincena . '-' . $this->data['sucursal_id'];
    }

    protected function limpiarMonto($monto)
    {
        if (empty($monto)) return 0;
        
        // Guardar valor original para logging
        $montoOriginal = $monto;
        
        // Convertir a string si no lo es
        $monto = (string) $monto;
        
        // Eliminar espacios y caracteres de moneda comunes
        $monto = trim($monto);
        $monto = str_replace(['$', '€', '£', ' ', "\t", "\n", "\r"], '', $monto);
        
        // Si está vacío después de limpiar, retornar 0
        if (empty($monto)) return 0;
        
        // Detectar si tiene coma y punto para determinar formato
        $tieneComa = strpos($monto, ',') !== false;
        $tienePunto = strpos($monto, '.') !== false;
        
        if ($tieneComa && $tienePunto) {
            // Tiene ambos: determinar cuál es separador de miles y cuál decimal
            $posComa = strpos($monto, ',');
            $posPunto = strpos($monto, '.');
            
            if ($posComa < $posPunto) {
                // Formato: 4,000.80 (coma para miles, punto para decimales)
                $monto = str_replace(',', '', $monto);
            } else {
                // Formato: 4.000,80 (punto para miles, coma para decimales)
                $monto = str_replace('.', '', $monto);
                $monto = str_replace(',', '.', $monto);
            }
        } elseif ($tieneComa) {
            // Solo tiene coma: verificar si es separador de miles o decimal
            // Si tiene más de 3 dígitos después de la coma, probablemente es decimal
            $partes = explode(',', $monto);
            if (count($partes) == 2 && strlen($partes[1]) <= 2) {
                // Probablemente es decimal (ej: 408,80)
                $monto = str_replace(',', '.', $monto);
            } else {
                // Probablemente es separador de miles (ej: 4,000)
                $monto = str_replace(',', '', $monto);
            }
        } elseif ($tienePunto) {
            // Solo tiene punto: verificar si es separador de miles o decimal
            $partes = explode('.', $monto);
            if (count($partes) == 2 && strlen($partes[1]) <= 2) {
                // Probablemente es decimal (ej: 408.80)
                // Ya está bien, no hacer nada
            } else {
                // Probablemente es separador de miles (ej: 4.000)
                $monto = str_replace('.', '', $monto);
            }
        }
        
        // Eliminar cualquier carácter que no sea número o punto
        $monto = preg_replace('/[^0-9.]/', '', $monto);
        
        // Si está vacío después de limpiar, retornar 0
        if (empty($monto)) return 0;
        
        // Convertir a float y validar
        $resultado = (float) $monto;
        
        // Validar que sea un número válido y positivo
        if (!is_numeric($resultado) || $resultado < 0) {
            Log::warning('Valor de monto inválido después de limpiar', [
                'monto_original' => $montoOriginal,
                'monto_limpio' => $monto,
                'resultado' => $resultado
            ]);
            return 0;
        }
        
        return $resultado;
    }

    protected function isEmptyRow($row)
    {
        $array = $row->toArray();
        // Filtrar valores vacíos y verificar si queda algo
        $filtered = array_filter($array, function($value) {
            return !empty(trim((string)$value));
        });
        return empty($filtered);
    }

    protected function isTotalRow($row)
    {
        $nombreCompleto = $this->obtenerNombreCompleto($row);
        return strtoupper(trim($nombreCompleto)) === 'TOTAL';
    }

    protected function obtenerNombreCompleto($row)
    {
        // Si existe la columna nombres_y_apellidos, usarla
        if (isset($row['nombres_y_apellidos']) && !empty($row['nombres_y_apellidos'])) {
            return trim($row['nombres_y_apellidos']);
        }
        
        // Si no, combinar nombres y apellidos
        $nombres = trim($this->obtenerValorColumna($row, ['nombres']) ?? '');
        $apellidos = trim($this->obtenerValorColumna($row, ['apellidos']) ?? '');
        
        if (empty($nombres) && empty($apellidos)) {
            return '';
        }
        
        return trim($nombres . ' ' . $apellidos);
    }

    /**
     * Obtiene el valor de una columna buscando diferentes variaciones del nombre
     * Laravel Excel puede convertir los encabezados de diferentes maneras
     */
    protected function obtenerValorColumna($row, array $nombresPosibles, $default = null)
    {
        $arrayRow = $row->toArray();
        
        // Primero intentar búsqueda exacta
        foreach ($nombresPosibles as $nombre) {
            // Buscar exactamente como está
            if (isset($arrayRow[$nombre]) && $arrayRow[$nombre] !== null && $arrayRow[$nombre] !== '') {
                return $arrayRow[$nombre];
            }
            
            // Buscar con espacio en lugar de guión bajo
            $nombreConEspacio = str_replace('_', ' ', $nombre);
            if (isset($arrayRow[$nombreConEspacio]) && $arrayRow[$nombreConEspacio] !== null && $arrayRow[$nombreConEspacio] !== '') {
                return $arrayRow[$nombreConEspacio];
            }
            
            // Buscar en minúsculas
            $nombreLower = strtolower($nombre);
            if (isset($arrayRow[$nombreLower]) && $arrayRow[$nombreLower] !== null && $arrayRow[$nombreLower] !== '') {
                return $arrayRow[$nombreLower];
            }
            
            // Buscar con espacio y minúsculas
            $nombreConEspacioLower = strtolower($nombreConEspacio);
            if (isset($arrayRow[$nombreConEspacioLower]) && $arrayRow[$nombreConEspacioLower] !== null && $arrayRow[$nombreConEspacioLower] !== '') {
                return $arrayRow[$nombreConEspacioLower];
            }
        }
        
        // Si no se encontró con búsqueda exacta, buscar case-insensitive en todas las claves
        $clavesArray = array_keys($arrayRow);
        foreach ($nombresPosibles as $nombre) {
            $nombreNormalizado = $this->normalizarTextoParaBusqueda($nombre);
            
            foreach ($clavesArray as $clave) {
                $claveNormalizada = $this->normalizarTextoParaBusqueda($clave);
                
                // Comparar nombres normalizados (sin acentos, minúsculas, sin espacios extras)
                if ($nombreNormalizado === $claveNormalizada) {
                    $valor = $arrayRow[$clave];
                    if ($valor !== null && $valor !== '') {
                        return $valor;
                    }
                }
            }
        }
        
        return $default;
    }
    
    protected function normalizarTextoParaBusqueda($texto)
    {
        // Convertir a minúsculas
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        // Quitar acentos
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $texto
        );
        // Normalizar espacios y guiones bajos
        $texto = preg_replace('/[\s_]+/', ' ', $texto);
        $texto = trim($texto);
        return $texto;
    }

    protected function generarCodigoEmpleado($nombres, $apellidos)
    {
        // Generar código con iniciales: Primer nombre + Primer apellido + número
        $inicialNombre = strtoupper(substr($nombres, 0, 1));
        $inicialApellido = strtoupper(substr($apellidos, 0, 1));
        
        // Obtener más letras si hay
        $partesApellido = explode(' ', $apellidos);
        $segundaInicial = '';
        if (count($partesApellido) > 1) {
            $segundaInicial = strtoupper(substr($partesApellido[1], 0, 1));
        }
        
        $baseCodigo = $inicialNombre . $inicialApellido . $segundaInicial;
        
        // Buscar un código único
        $contador = 1;
        $codigo = $baseCodigo . str_pad($contador, 2, '0', STR_PAD_LEFT);
        
        while (Empleado::where('codigo', $codigo)
            ->where('id_empresa', $this->data['empresa_id'])
            ->exists()) {
            $contador++;
            $codigo = $baseCodigo . str_pad($contador, 2, '0', STR_PAD_LEFT);
            
            if ($contador > 99) {
                $codigo = $baseCodigo . time(); // Usar timestamp si se agotan los números
                break;
            }
        }
        
        return $codigo;
    }
    
    protected function obtenerOCrearDepartamento()
    {
        // Buscar departamento "General" o "Sin asignar"
        $departamento = DepartamentoEmpresa::where('id_empresa', $this->data['empresa_id'])
            ->where('id_sucursal', $this->data['sucursal_id'])
            ->where(function($q) {
                $q->where('nombre', 'LIKE', '%General%')
                  ->orWhere('nombre', 'LIKE', '%Sin asignar%')
                  ->orWhere('nombre', 'LIKE', '%Default%');
            })
            ->first();
        
        if (!$departamento) {
            // Crear departamento por defecto
            $departamento = DepartamentoEmpresa::create([
                'nombre' => 'General',
                'descripcion' => 'Departamento creado automáticamente para importación',
                'activo' => true,
                'estado' => 1,
                'id_sucursal' => $this->data['sucursal_id'],
                'id_empresa' => $this->data['empresa_id'],
            ]);
            
            Log::info('Departamento creado automáticamente', [
                'id' => $departamento->id,
                'nombre' => $departamento->nombre
            ]);
        }
        
        return $departamento;
    }
    
    protected function obtenerOCrearCargo($departamentoId)
    {
        // Buscar cargo "General" o "Sin asignar"
        $cargo = CargoEmpresa::where('id_empresa', $this->data['empresa_id'])
            ->where('id_sucursal', $this->data['sucursal_id'])
            ->where('id_departamento', $departamentoId)
            ->where(function($q) {
                $q->where('nombre', 'LIKE', '%General%')
                  ->orWhere('nombre', 'LIKE', '%Sin asignar%')
                  ->orWhere('nombre', 'LIKE', '%Default%');
            })
            ->first();
        
        if (!$cargo) {
            // Crear cargo por defecto
            $cargo = CargoEmpresa::create([
                'nombre' => 'General',
                'descripcion' => 'Cargo creado automáticamente para importación',
                'salario_base' => 0,
                'activo' => true,
                'estado' => 1,
                'id_departamento' => $departamentoId,
                'id_sucursal' => $this->data['sucursal_id'],
                'id_empresa' => $this->data['empresa_id'],
            ]);
            
            Log::info('Cargo creado automáticamente', [
                'id' => $cargo->id,
                'nombre' => $cargo->nombre
            ]);
        }
        
        return $cargo;
    }
    
    protected function convertirTipoJornada($tipoJornada)
    {
        if (empty($tipoJornada)) {
            return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;
        }
        
        $tipoJornada = strtolower(trim($tipoJornada));
        
        if (strpos($tipoJornada, 'tiempo completo') !== false || 
            strpos($tipoJornada, 'completo') !== false ||
            $tipoJornada == '1') {
            return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;
        }
        
        if (strpos($tipoJornada, 'medio tiempo') !== false || 
            strpos($tipoJornada, 'medio') !== false ||
            $tipoJornada == '2') {
            return PlanillaConstants::TIPO_JORNADA_MEDIO_TIEMPO;
        }
        
        return PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO; // Por defecto
    }
    
    protected function convertirTipoContrato($tipoContrato)
    {
        if (empty($tipoContrato)) {
            return PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        }
        
        $tipoContrato = strtolower(trim($tipoContrato));
        
        if (strpos($tipoContrato, 'permanente') !== false || $tipoContrato == '1') {
            return PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        }
        
        if (strpos($tipoContrato, 'temporal') !== false || $tipoContrato == '2') {
            return PlanillaConstants::TIPO_CONTRATO_TEMPORAL;
        }
        
        if (strpos($tipoContrato, 'obra') !== false || $tipoContrato == '3') {
            return PlanillaConstants::TIPO_CONTRATO_POR_OBRA;
        }
        
        return PlanillaConstants::TIPO_CONTRATO_PERMANENTE; // Por defecto
    }
    
    protected function procesarFecha($fecha)
    {
        if (empty($fecha)) {
            return null;
        }
        
        try {
            // Intentar diferentes formatos comunes
            $formatos = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'Y/m/d'];
            
            foreach ($formatos as $formato) {
                try {
                    $fechaCarbon = Carbon::createFromFormat($formato, trim($fecha));
                    return $fechaCarbon->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Si no funciona con formatos específicos, intentar parse automático
            $fechaCarbon = Carbon::parse($fecha);
            return $fechaCarbon->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Error procesando fecha', [
                'fecha_original' => $fecha,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    protected function generarEmail($nombres, $apellidos, $codigo)
    {
        // Generar email basado en nombre y código
        $nombreLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto($nombres)));
        $apellidoLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $this->normalizarTexto($apellidos)));
        $codigoLimpio = strtolower(preg_replace('/[^a-z0-9]/', '', $codigo));
        
        $email = $nombreLimpio . '.' . $apellidoLimpio . '.' . $codigoLimpio . '@empresa.local';
        
        return $email;
    }
    
    protected function hacerEmailUnico($email)
    {
        $emailBase = $email;
        $contador = 1;
        
        while (Empleado::where('email', $email)
            ->where('id_empresa', $this->data['empresa_id'])
            ->exists()) {
            $partes = explode('@', $emailBase);
            $email = $partes[0] . $contador . '@' . ($partes[1] ?? 'empresa.local');
            $contador++;
        }
        
        return $email;
    }
    
    protected function hacerDuiUnico($dui)
    {
        // Limpiar DUI (quitar guiones y espacios)
        $duiLimpio = preg_replace('/[^0-9-]/', '', $dui);
        
        $duiBase = $duiLimpio;
        $duiFinal = $duiLimpio;
        $contador = 1;
        
        while (Empleado::where('dui', $duiFinal)
            ->where('id_empresa', $this->data['empresa_id'])
            ->exists()) {
            // Si el DUI ya existe, agregar un sufijo temporal
            $duiFinal = $duiBase . '-' . $contador;
            $contador++;
            
            if ($contador > 10) {
                // Si hay muchos duplicados, generar uno completamente nuevo
                $duiFinal = $this->generarDuiTemporal($duiBase);
                break;
            }
        }
        
        return $duiFinal;
    }
    
    protected function generarDuiTemporal($codigo)
    {
        // Generar DUI temporal basado en código y timestamp
        $timestamp = substr(time(), -6); // Últimos 6 dígitos del timestamp
        $codigoLimpio = preg_replace('/[^0-9]/', '', $codigo);
        $codigoLimpio = substr($codigoLimpio, 0, 3);
        
        $dui = str_pad($codigoLimpio . $timestamp, 9, '0', STR_PAD_LEFT);
        $dui = substr($dui, 0, 8) . '-' . substr($dui, -1);
        
        // Verificar que sea único
        return $this->hacerDuiUnico($dui);
    }
    
    protected function hacerNitUnico($nit)
    {
        // Limpiar NIT
        $nit = preg_replace('/[^0-9-]/', '', $nit);
        
        $nitBase = $nit;
        $contador = 1;
        
        while (Empleado::where('nit', $nit)
            ->where('id_empresa', $this->data['empresa_id'])
            ->exists()) {
            // Si el NIT ya existe, agregar un sufijo
            $nit = $nitBase . '-' . $contador;
            $contador++;
            
            if ($contador > 10) {
                // Si hay muchos duplicados, retornar null (NIT es nullable)
                return null;
            }
        }
        
        return $nit;
    }

    protected function calcularISSSPatronal($salario)
    {
        $baseISSSPatronal = min($salario, 1000);
        return $baseISSSPatronal * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
    }

    protected function calcularAFPPatronal($salario)
    {
        return $salario * PlanillaConstants::DESCUENTO_AFP_PATRONO;
    }
}

<?php

namespace App\Imports;

use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\Empleado;
use App\Constants\PlanillaConstants;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanillasImport implements ToCollection, WithHeadingRow
{
    protected $planilla;
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection(Collection $rows)
    {
        try {
            DB::beginTransaction();

            // Crear planilla principal
            $this->planilla = $this->crearPlanilla();

            // Variables para totales generales
            $totales = [
                'salario_base' => 0,
                'comisiones' => 0,
                'horas_extra' => 0,
                'total_horas_extras' => 0,
                'bonificaciones' => 0,
                'otros_ingresos' => 0,
                'total_ingresos' => 0,
                'isss' => 0,
                'afp' => 0,
                'renta' => 0,
                'prestamos' => 0,
                'anticipos' => 0,
                'descuentos_judiciales' => 0,
                'otros_descuentos' => 0,
                'total_deducciones' => 0,
                'total_neto' => 0
            ];

            // Procesar cada fila
            foreach ($rows as $row) {
                // Saltar fila si está vacía o es el total
                if ($this->isEmptyRow($row) || $this->isTotalRow($row)) {
                    continue;
                }

                // Procesar y guardar detalle
                $detalle = $this->procesarDetalle($row);

                // Acumular totales
                $this->acumularTotales($totales, $detalle);
            }

            // Actualizar totales de la planilla
            $this->planilla->update([
                'total_salarios' => $totales['total_ingresos'],
                'total_deducciones' => $totales['total_deducciones'],
                'total_neto' => $totales['total_neto']
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
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
        // Buscar empleado por código y nombre
        $empleado = $this->buscarEmpleado($row['nombres_y_apellidos']);

        // Procesar montos
        $salario_base = $this->limpiarMonto($row['salario_base']);
        $dias_laborados = intval($row['dias_laborados']);
        $salario_devengado = ($salario_base / 30) * $dias_laborados;

        // Ingresos adicionales
        $comisiones = $this->limpiarMonto($row['comisiones'] ?? 0);
        $horas_extra = $this->limpiarMonto($row['horas_extra'] ?? 0);
        $monto_horas_extra = $this->limpiarMonto($row['monto_horas_extra'] ?? 0);
        $total_horas_extras = $this->limpiarMonto($row['total_horas_extras'] ?? 0);
        $bonificaciones = $this->limpiarMonto($row['bonificaciones'] ?? 0);
        $otros_ingresos = $this->limpiarMonto($row['otros_ingresos'] ?? 0);

        // Total ingresos
        $total_ingresos = $salario_devengado + $comisiones + $total_horas_extras +
            $bonificaciones + $otros_ingresos;

        // Deducciones
        $isss = $this->limpiarMonto($row['isss'] ?? 0);
        $afp = $this->limpiarMonto($row['afp'] ?? 0);
        $renta = $this->limpiarMonto($row['renta'] ?? 0);
        $prestamos = $this->limpiarMonto($row['prestamos'] ?? 0);
        $anticipos = $this->limpiarMonto($row['anticipos'] ?? 0);
        $descuentos_judiciales = $this->limpiarMonto($row['descuentos_judiciales'] ?? 0);
        $otros_descuentos = $this->limpiarMonto($row['otras_deducciones'] ?? 0);

        // Total deducciones
        $total_deducciones = $isss + $afp + $renta + $prestamos + $anticipos +
            $descuentos_judiciales + $otros_descuentos;

        // Total neto
        $total_neto = $total_ingresos - $total_deducciones;

        // Crear detalle
        return PlanillaDetalle::create([
            'id_planilla' => $this->planilla->id,
            'id_empleado' => $empleado->id,
            'salario_base' => $salario_base,
            'dias_laborados' => $dias_laborados,
            'salario_devengado' => $salario_devengado,
            'horas_extra' => $horas_extra,
            'monto_horas_extra' => $monto_horas_extra,
            'total_horas_extras' => $total_horas_extras,
            'comisiones' => $comisiones,
            'bonificaciones' => $bonificaciones,
            'otros_ingresos' => $otros_ingresos,
            'total_ingresos' => $total_ingresos,
            'isss_empleado' => $isss,
            'isss_patronal' => $this->calcularISSSPatronal($total_ingresos),
            'afp_empleado' => $afp,
            'afp_patronal' => $this->calcularAFPPatronal($total_ingresos),
            'renta' => $renta,
            'prestamos' => $prestamos,
            'anticipos' => $anticipos,
            'descuentos_judiciales' => $descuentos_judiciales,
            'otros_descuentos' => $otros_descuentos,
            'detalle_otras_deducciones' => $row['detalle_de_otras_deducciones'] ?? '',
            'total_descuentos' => $total_deducciones,
            'sueldo_neto' => $total_neto,
            'estado' => PlanillaConstants::PLANILLA_BORRADOR
        ]);
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

    protected function buscarEmpleado($nombreCompleto)
    {
        $this->validarFormatoNombre($nombreCompleto);
        $partes = explode(' ', trim($nombreCompleto));
    
        $empleado = Empleado::where(function ($query) use ($partes, $nombreCompleto) {  // Añadido $nombreCompleto aquí
            $query->where(DB::raw("CONCAT(TRIM(nombres), ' ', TRIM(apellidos))"), 'LIKE', "%$nombreCompleto%");
            $query->orWhere(DB::raw("CONCAT(TRIM(apellidos), ' ', TRIM(nombres))"), 'LIKE', "%$nombreCompleto%");
    
            if (count($partes) >= 2) {
                $posibleApellido = end($partes);
                $posiblesNombres = implode(' ', array_slice($partes, 0, -1));
                
                $query->orWhere(function($q) use ($posiblesNombres, $posibleApellido) {
                    $q->where('nombres', 'LIKE', "%$posiblesNombres%")
                      ->where('apellidos', 'LIKE', "%$posibleApellido%");
                });
                
                $primerNombre = $partes[0];
                $posiblesApellidos = implode(' ', array_slice($partes, 1));
                
                $query->orWhere(function($q) use ($primerNombre, $posiblesApellidos) {
                    $q->where('nombres', 'LIKE', "%$primerNombre%")
                      ->where('apellidos', 'LIKE', "%$posiblesApellidos%");
                });
            }
    
            foreach ($partes as $parte) {
                if (strlen($parte) > 2) {
                    $query->orWhere('nombres', 'LIKE', "%$parte%")
                          ->orWhere('apellidos', 'LIKE', "%$parte%");
                }
            }
        })
        ->where('id_empresa', $this->data['empresa_id'])
        ->first();
    
        if (!$empleado) {
            Log::warning('Empleado no encontrado', [
                'nombre_buscado' => $nombreCompleto,
                'partes' => $partes,
                'empresa_id' => $this->data['empresa_id']
            ]);
            
            throw new \Exception("Empleado no encontrado: $nombreCompleto. Por favor verifique que el nombre coincida exactamente con el registro en el sistema.");
        }
    
        return $empleado;
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
        return (float) preg_replace('/[^0-9.]/', '', $monto);
    }

    protected function isEmptyRow($row)
    {
        return empty(array_filter($row->toArray()));
    }

    protected function isTotalRow($row)
    {
        return strtoupper(trim($row['nombres_y_apellidos'] ?? '')) === 'TOTAL';
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

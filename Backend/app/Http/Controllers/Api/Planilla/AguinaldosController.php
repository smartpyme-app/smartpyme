<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Helpers\AguinaldoHelper;
use App\Http\Controllers\Controller;
use App\Models\Planilla\Aguinaldo;
use App\Models\Planilla\AguinaldoDetalle;
use App\Models\Planilla\Empleado;
use App\Models\Compras\Gastos\Categoria;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class AguinaldosController extends Controller
{
    /**
     * Listar aguinaldos
     */
    public function index(Request $request)
    {
        $query = Aguinaldo::with(['aguinaldoDetalles.empleado'])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->where('id_sucursal', auth()->user()->id_sucursal);

        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscador')) {
            $busqueda = $request->buscador;
            $query->whereHas('aguinaldoDetalles.empleado', function ($q) use ($busqueda) {
                $q->where('nombres', 'LIKE', "%$busqueda%")
                    ->orWhere('apellidos', 'LIKE', "%$busqueda%")
                    ->orWhere('codigo', 'LIKE', "%$busqueda%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($request->get('paginate', 10));
    }

    /**
     * Crear aguinaldo vacío para un año
     */
    public function store(Request $request)
    {
        $request->validate([
            'anio' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'fecha_calculo' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            // Verificar que no exista un aguinaldo para ese año
            $aguinaldoExistente = Aguinaldo::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('anio', $request->anio)
                ->first();

            if ($aguinaldoExistente) {
                return response()->json([
                    'error' => 'Ya existe un aguinaldo para el año ' . $request->anio
                ], 422);
            }

            // Por defecto, la fecha de cálculo es el 12 de diciembre del año (por ley)
            $fechaCalculo = $request->fecha_calculo ?? Carbon::create($request->anio, 12, 12)->format('Y-m-d');

            $aguinaldo = Aguinaldo::create([
                'id_empresa' => auth()->user()->id_empresa,
                'id_sucursal' => auth()->user()->id_sucursal,
                'anio' => $request->anio,
                'fecha_calculo' => $fechaCalculo,
                'total_aguinaldos' => 0,
                'total_retenciones' => 0,
                'estado' => PlanillaConstants::AGUINALDO_BORRADOR,
                'observaciones' => $request->observaciones ?? null
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Aguinaldo creado exitosamente',
                'aguinaldo' => $aguinaldo
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creando aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear el aguinaldo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar empleado con monto manual
     */
    public function agregarEmpleado(Request $request, $id)
    {
        $request->validate([
            'id_empleado' => 'required|exists:empleados,id',
            'monto_aguinaldo_bruto' => 'required|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $aguinaldo = Aguinaldo::findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para modificar este aguinaldo'
                ], 403);
            }

            // Verificar que esté en borrador
            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se pueden agregar empleados a aguinaldos en estado borrador'
                ], 422);
            }

            // Verificar que el empleado no esté ya agregado
            $detalleExistente = AguinaldoDetalle::where('id_aguinaldo', $aguinaldo->id)
                ->where('id_empleado', $request->id_empleado)
                ->first();

            if ($detalleExistente) {
                return response()->json([
                    'error' => 'El empleado ya está agregado a este aguinaldo'
                ], 422);
            }

            // Verificar elegibilidad del empleado
            $empleado = Empleado::findOrFail($request->id_empleado);
            $validacion = AguinaldoHelper::validarElegibilidadAguinaldo($empleado, $aguinaldo->anio);

            if (!$validacion['elegible']) {
                return response()->json([
                    'error' => $validacion['razon']
                ], 422);
            }

            // Crear detalle
            $detalle = new AguinaldoDetalle([
                'id_aguinaldo' => $aguinaldo->id,
                'id_empleado' => $request->id_empleado,
                'monto_aguinaldo_bruto' => $request->monto_aguinaldo_bruto,
                'notas' => $request->notas ?? null
            ]);

            // Calcular valores automáticamente
            $detalle->calcularValores();
            $detalle->save();

            // Actualizar totales del aguinaldo
            $this->actualizarTotales($aguinaldo->id);

            DB::commit();

            return response()->json([
                'message' => 'Empleado agregado exitosamente',
                'detalle' => $detalle->load('empleado')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error agregando empleado a aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al agregar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar monto y recalcular
     */
    public function actualizarEmpleado(Request $request, $id)
    {
        $request->validate([
            'monto_aguinaldo_bruto' => 'required|numeric|min:0',
            'notas' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $detalle = AguinaldoDetalle::with('aguinaldo')->findOrFail($id);
            $aguinaldo = $detalle->aguinaldo;

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para modificar este aguinaldo'
                ], 403);
            }

            // Verificar que esté en borrador
            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se pueden modificar aguinaldos en estado borrador'
                ], 422);
            }

            // Actualizar monto bruto
            $detalle->monto_aguinaldo_bruto = $request->monto_aguinaldo_bruto;
            if ($request->has('notas')) {
                $detalle->notas = $request->notas;
            }

            // Recalcular valores
            $detalle->calcularValores();
            $detalle->save();

            // Actualizar totales del aguinaldo
            $this->actualizarTotales($aguinaldo->id);

            DB::commit();

            return response()->json([
                'message' => 'Empleado actualizado exitosamente',
                'detalle' => $detalle->load('empleado')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando empleado en aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quitar empleado del aguinaldo
     */
    public function eliminarEmpleado($id)
    {
        try {
            DB::beginTransaction();

            $detalle = AguinaldoDetalle::with('aguinaldo')->findOrFail($id);
            $aguinaldo = $detalle->aguinaldo;

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para modificar este aguinaldo'
                ], 403);
            }

            // Verificar que esté en borrador
            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se pueden eliminar empleados de aguinaldos en estado borrador'
                ], 422);
            }

            $idAguinaldo = $aguinaldo->id;
            $detalle->delete();

            // Actualizar totales del aguinaldo
            $this->actualizarTotales($idAguinaldo);

            DB::commit();

            return response()->json([
                'message' => 'Empleado eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error eliminando empleado de aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle con todos los empleados
     */
    public function show($id)
    {
        try {
            $aguinaldo = Aguinaldo::with(['aguinaldoDetalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No autorizado'
                ], 403);
            }

            // Obtener detalles con información del empleado
            $detalles = $aguinaldo->aguinaldoDetalles()
                ->join('empleados', 'aguinaldo_detalles.id_empleado', '=', 'empleados.id')
                ->select(
                    'aguinaldo_detalles.*',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.codigo',
                    'empleados.dui'
                )
                ->orderBy('empleados.apellidos')
                ->orderBy('empleados.nombres')
                ->get();

            return response()->json([
                'aguinaldo' => $aguinaldo,
                'detalles' => $detalles,
                'resumen' => [
                    'total_empleados' => $detalles->count(),
                    'total_aguinaldos' => $aguinaldo->total_aguinaldos,
                    'total_retenciones' => $aguinaldo->total_retenciones,
                    'total_neto' => $aguinaldo->total_neto
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo detalle de aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el detalle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar fecha de cálculo del aguinaldo
     */
    public function actualizarFechaCalculo(Request $request, $id)
    {
        $request->validate([
            'fecha_calculo' => 'required|date'
        ]);

        try {
            $aguinaldo = Aguinaldo::findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para modificar este aguinaldo'
                ], 403);
            }

            // Verificar que esté en borrador
            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se puede modificar la fecha de cálculo en aguinaldos en estado borrador'
                ], 422);
            }

            $aguinaldo->fecha_calculo = $request->fecha_calculo;
            $aguinaldo->save();

            return response()->json([
                'message' => 'Fecha de cálculo actualizada exitosamente',
                'aguinaldo' => $aguinaldo
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando fecha de cálculo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar la fecha de cálculo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar pago (copiar lógica de PlanillasController)
     */
    public function processPayment($id)
    {
        try {
            DB::beginTransaction();

            $aguinaldo = Aguinaldo::with(['aguinaldoDetalles.empleado', 'empresa'])->findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para procesar este aguinaldo'
                ], 403);
            }

            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se pueden pagar aguinaldos en estado borrador'
                ], 422);
            }

            // 1. Registrar gastos en contabilidad ANTES de cambiar estado
            $resultadoGastos = $this->registrarGastosAguinaldo($aguinaldo);

            if (!$resultadoGastos) {
                throw new \Exception('Error al registrar los gastos de aguinaldo');
            }

            // 2. Actualizar estado del aguinaldo
            $aguinaldo->estado = PlanillaConstants::AGUINALDO_PAGADO;
            $aguinaldo->save();

            DB::commit();

            return response()->json([
                'message' => 'Pago de aguinaldo procesado exitosamente',
                'aguinaldo' => $aguinaldo
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error procesando pago de aguinaldo: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al procesar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar gastos de aguinaldo en contabilidad
     * Similar a registrarGastosPlanilla pero adaptado para aguinaldos
     */
    private function registrarGastosAguinaldo(Aguinaldo $aguinaldo)
    {
        try {
            // Obtener o crear la categoría de gastos de aguinaldo
            $categoria = Categoria::firstOrCreate(
                [
                    'nombre' => 'Gastos de Aguinaldo',
                    'id_empresa' => $aguinaldo->id_empresa
                ]
            );

            // Obtener o crear el proveedor para aguinaldos
            $proveedor = Proveedor::firstOrCreate(
                [
                    'tipo' => 'Empresa',
                    'nombre_empresa' => 'Aguinaldos - Empleados',
                    'id_empresa' => $aguinaldo->id_empresa,
                    'id_usuario' => auth()->user()->id
                ],
                [
                    'tipo_contribuyente' => 'Otros',
                    'estado' => 'Activo',
                    'id_sucursal' => $aguinaldo->id_sucursal
                ]
            );

            $gastosCreados = 0;
            $fecha_pago = now();

            // Crear un gasto por cada empleado con su aguinaldo neto
            foreach ($aguinaldo->aguinaldoDetalles as $detalle) {
                // Verificar si tiene empleado asociado
                if (!isset($detalle->empleado)) {
                    Log::warning('Detalle de aguinaldo sin empleado asociado', ['detalle_id' => $detalle->id]);
                    continue;
                }

                // Obtener el nombre completo del empleado
                $nombreEmpleado = $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos;

                // Aguinaldo neto (después de retención de renta)
                $aguinaldoNeto = round(floatval($detalle->aguinaldo_neto ?? 0), 2);

                // Solo crear gasto si el aguinaldo neto es mayor a cero
                if ($aguinaldoNeto > 0) {
                    $gastoEmpleado = Gasto::create([
                        'fecha' => $fecha_pago,
                        'fecha_pago' => $fecha_pago,
                        'tipo_documento' => 'Aguinaldo',
                        'referencia' => 'AGU-' . $aguinaldo->anio,
                        'concepto' => "Aguinaldo neto - {$nombreEmpleado}",
                        'tipo' => 'Aguinaldos',
                        'estado' => 'Pagado',
                        'forma_pago' => 'Transferencia',
                        'total' => $aguinaldoNeto,
                        'id_proveedor' => $proveedor->id,
                        'id_categoria' => $categoria->id,
                        'id_usuario' => auth()->id(),
                        'id_empresa' => $aguinaldo->id_empresa,
                        'id_sucursal' => $aguinaldo->id_sucursal,
                        'nota' => "Pago de aguinaldo neto a {$nombreEmpleado} - Aguinaldo {$aguinaldo->anio}"
                    ]);

                    $gastosCreados++;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error registrando gastos de aguinaldo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Exportar a Excel
     */
    public function exportExcel($id)
    {
        try {
            $aguinaldo = Aguinaldo::with(['aguinaldoDetalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para exportar este aguinaldo'
                ], 403);
            }

            // TODO: Crear clase AguinaldoExport similar a PlanillaExport
            // Por ahora retornamos un mensaje
            return response()->json([
                'message' => 'Exportación a Excel pendiente de implementar',
                'aguinaldo_id' => $id
            ]);

            // Cuando esté implementado:
            // return Excel::download(
            //     new AguinaldoExport($aguinaldo),
            //     'aguinaldo_' . $aguinaldo->anio . '.xlsx'
            // );
        } catch (\Exception $e) {
            Log::error('Error exportando aguinaldo a Excel: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar a PDF
     */
    public function exportPDF($id)
    {
        try {
            $aguinaldo = Aguinaldo::with(['aguinaldoDetalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para exportar este aguinaldo'
                ], 403);
            }

            // Obtener detalles ordenados
            $detalles = $aguinaldo->aguinaldoDetalles()
                ->join('empleados', 'aguinaldo_detalles.id_empleado', '=', 'empleados.id')
                ->select('aguinaldo_detalles.*')
                ->orderBy('empleados.apellidos')
                ->orderBy('empleados.nombres')
                ->get();

            // TODO: Crear vista PDF similar a planilla-detalle
            // Por ahora retornamos un mensaje
            return response()->json([
                'message' => 'Exportación a PDF pendiente de implementar',
                'aguinaldo_id' => $id
            ]);

            // Cuando esté implementado:
            // $pdf = PDF::loadView('pdf.aguinaldo-detalle', [
            //     'aguinaldo' => $aguinaldo,
            //     'detalles' => $detalles,
            //     'empresa' => $aguinaldo->empresa
            // ]);
            // $pdf->setPaper('a4', 'landscape');
            // return $pdf->download('aguinaldo_' . $aguinaldo->anio . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error exportando aguinaldo a PDF: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar aguinaldo
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $aguinaldo = Aguinaldo::findOrFail($id);

            // Verificar permisos
            if ($aguinaldo->id_empresa !== auth()->user()->id_empresa ||
                $aguinaldo->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para eliminar este aguinaldo'
                ], 403);
            }

            if (!$aguinaldo->esBorrador()) {
                return response()->json([
                    'error' => 'Solo se pueden eliminar aguinaldos en estado borrador'
                ], 422);
            }

            // Eliminar detalles
            AguinaldoDetalle::where('id_aguinaldo', $id)->delete();

            // Eliminar aguinaldo
            $aguinaldo->delete();

            DB::commit();

            return response()->json([
                'message' => 'Aguinaldo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error eliminando aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar el aguinaldo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener sugerencia de aguinaldo para un empleado
     */
    public function obtenerSugerenciaAguinaldo(Request $request)
    {
        $request->validate([
            'id_empleado' => 'required|exists:empleados,id',
            'anio' => 'required|integer',
            'fecha_calculo' => 'nullable|date' // Fecha de cálculo (opcional, por defecto 12 de diciembre)
        ]);

        try {
            $empleado = Empleado::findOrFail($request->id_empleado);

            // Verificar permisos
            if ($empleado->id_empresa !== auth()->user()->id_empresa ||
                $empleado->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para ver este empleado'
                ], 403);
            }

            // Obtener fecha de cálculo (por defecto 12 de diciembre del año)
            $fechaCalculo = $request->fecha_calculo 
                ? Carbon::parse($request->fecha_calculo)
                : Carbon::create($request->anio, 12, 12);

            // Calcular años de laborar y meses trabajados
            $fechaIngreso = \Carbon\Carbon::parse($empleado->fecha_ingreso);
            $aniosLaborar = AguinaldoHelper::calcularAniosLaborar($fechaIngreso, $request->anio, $fechaCalculo);
            $mesesTrabajados = AguinaldoHelper::calcularMesesTrabajados($fechaIngreso, $request->anio, $fechaCalculo);

            // Calcular sugerencia basada en años de laborar
            $sugerencia = AguinaldoHelper::calcularSugerenciaAguinaldo(
                $empleado->salario_base,
                $fechaIngreso,
                $request->anio,
                $fechaCalculo
            );

            // Determinar días de aguinaldo según años de laborar
            $diasAguinaldo = 0;
            if ($aniosLaborar < 1) {
                // Menos de 1 año: proporcional según días trabajados / 365
                $inicioAnio = \Carbon\Carbon::create($request->anio, 1, 1);
                $fechaInicio = $fechaIngreso->year == $request->anio ? $fechaIngreso : $inicioAnio;
                
                // Si ingresó después de la fecha de cálculo, no tiene derecho
                if ($fechaInicio->gt($fechaCalculo)) {
                    $diasAguinaldo = 0;
                } else {
                    $diasTrabajados = $fechaInicio->diffInDays($fechaCalculo) + 1;
                    
                    if ($diasTrabajados >= 30) {
                        // Calcular días proporcionales: (días trabajados / 365) * 15 días base
                        $diasAguinaldo = round(($diasTrabajados / 365) * 15, 2);
                    } else {
                        $diasAguinaldo = 0; // Menos de 30 días, no tiene derecho
                    }
                }
            } elseif ($aniosLaborar >= 1 && $aniosLaborar < 3) {
                // De 1 a más, pero menos de 3 años: 15 días
                $diasAguinaldo = 15;
            } elseif ($aniosLaborar >= 3 && $aniosLaborar < 10) {
                // De 3 a más, pero menos de 10 años: 19 días
                $diasAguinaldo = 19;
            } else {
                // 10 años o más: 21 días
                $diasAguinaldo = 21;
            }

            return response()->json([
                'sugerencia' => $sugerencia,
                'anios_laborar' => round($aniosLaborar, 2),
                'meses_trabajados' => $mesesTrabajados,
                'dias_aguinaldo' => $diasAguinaldo,
                'salario_base' => $empleado->salario_base,
                'fecha_ingreso' => $empleado->fecha_ingreso,
                'tipo_contrato' => $empleado->tipo_contrato
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo sugerencia de aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener sugerencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular preview de aguinaldo (deducciones en tiempo real)
     */
    public function calcularPreview(Request $request)
    {
        $request->validate([
            'monto_bruto' => 'required|numeric|min:0',
            'anio' => 'required|integer',
            'tipo_contrato' => 'nullable|integer'
        ]);

        try {
            $calculos = AguinaldoHelper::calcularDeduccionesAguinaldo(
                $request->monto_bruto,
                $request->anio,
                $request->tipo_contrato
            );

            return response()->json([
                'monto_bruto' => round($request->monto_bruto, 2),
                'monto_exento' => $calculos['monto_exento'],
                'monto_gravado' => $calculos['monto_gravado'],
                'retencion_renta' => $calculos['retencion_renta'],
                'aguinaldo_neto' => $calculos['aguinaldo_neto']
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculando preview de aguinaldo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al calcular preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método privado para actualizar totales del aguinaldo
     */
    private function actualizarTotales($idAguinaldo)
    {
        try {
            $aguinaldo = Aguinaldo::findOrFail($idAguinaldo);
            $aguinaldo->actualizarTotales();
        } catch (\Exception $e) {
            Log::error('Error actualizando totales de aguinaldo: ' . $e->getMessage());
            throw $e;
        }
    }
}

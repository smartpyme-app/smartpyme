<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Planilla\Empleado;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\PrestamoEmpleado;
use App\Models\Planilla\PrestamoMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamosController extends Controller
{
    /**
     * Listado de préstamos (opcional: filtrar por empleado o estado).
     */
    public function index(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $query = PrestamoEmpleado::with(['empleado'])
            ->porEmpresa($idEmpresa)
            ->orderBy('id', 'desc');

        if ($request->filled('id_empleado')) {
            $query->porEmpleado($request->id_empleado);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $paginate = $request->get('paginate', 15);
        return $query->paginate($paginate);
    }

    /**
     * Préstamos activos de un empleado (para selector al asignar abono desde planilla).
     */
    public function prestamosActivosPorEmpleado(Request $request, $id)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $prestamos = PrestamoEmpleado::with('empleado')
            ->porEmpresa($idEmpresa)
            ->porEmpleado((int) $id)
            ->activos()
            ->where('saldo_actual', '>', 0)
            ->orderBy('numero_prestamo')
            ->get();

        return response()->json($prestamos);
    }

    /**
     * Crear préstamo (desembolso inicial).
     */
    public function store(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $request->validate([
            'id_empleado' => 'required|integer|exists:empleados,id',
            'monto_inicial' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:500',
            'fecha_desembolso' => 'required|date',
        ]);

        $empleado = Empleado::where('id', $request->id_empleado)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $numeroPrestamo = PrestamoEmpleado::siguienteNumeroParaEmpleado((int) $empleado->id);

            $prestamo = PrestamoEmpleado::create([
                'id_empleado' => $empleado->id,
                'id_empresa' => $idEmpresa,
                'numero_prestamo' => $numeroPrestamo,
                'monto_inicial' => $request->monto_inicial,
                'saldo_actual' => $request->monto_inicial,
                'descripcion' => $request->descripcion ?? 'Préstamo personal autorizado',
                'fecha_desembolso' => $request->fecha_desembolso,
                'estado' => PrestamoEmpleado::ESTADO_ACTIVO,
            ]);

            PrestamoMovimiento::create([
                'id_prestamo' => $prestamo->id,
                'tipo' => PrestamoMovimiento::TIPO_DESEMBOLSO,
                'monto' => $prestamo->monto_inicial,
                'saldo_despues' => $prestamo->saldo_actual,
                'descripcion' => $request->descripcion ?? 'Préstamo personal autorizado',
                'fecha' => $prestamo->fecha_desembolso,
                'id_planilla_detalle' => null,
            ]);

            DB::commit();
            $prestamo->load('empleado');
            return response()->json($prestamo, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Registrar abono a un préstamo (efectivo o desde planilla).
     */
    public function abono(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $request->validate([
            'id_prestamo' => 'required|integer|exists:prestamos_empleados,id',
            'monto' => 'required|numeric|min:0.01',
            'tipo' => 'required|in:abono_planilla,abono_efectivo',
            'descripcion' => 'nullable|string|max:500',
            'fecha' => 'required|date',
            'id_planilla_detalle' => 'nullable|integer|exists:planilla_detalles,id',
        ]);

        $prestamo = PrestamoEmpleado::porEmpresa($idEmpresa)->findOrFail($request->id_prestamo);

        $saldoActual = (float) $prestamo->saldo_actual;
        $monto = (float) $request->monto;

        if ($monto > $saldoActual) {
            return response()->json([
                'error' => 'El monto del abono no puede ser mayor al saldo pendiente ($' . number_format($saldoActual, 2) . ').',
            ], 422);
        }

        if ($request->tipo === PrestamoMovimiento::TIPO_ABONO_PLANILLA && $request->filled('id_planilla_detalle')) {
            $detalle = PlanillaDetalle::find($request->id_planilla_detalle);
            if (!$detalle || $detalle->id_empleado != $prestamo->id_empleado) {
                return response()->json(['error' => 'El detalle de planilla no corresponde al empleado del préstamo.'], 422);
            }
        }

        DB::beginTransaction();
        try {
            $nuevoSaldo = round($saldoActual - $monto, 2);
            $estado = $nuevoSaldo <= 0 ? PrestamoEmpleado::ESTADO_LIQUIDADO : PrestamoEmpleado::ESTADO_ACTIVO;

            PrestamoMovimiento::create([
                'id_prestamo' => $prestamo->id,
                'tipo' => $request->tipo,
                'monto' => $monto,
                'saldo_despues' => $nuevoSaldo,
                'descripcion' => $request->descripcion,
                'fecha' => $request->fecha,
                'id_planilla_detalle' => $request->id_planilla_detalle,
            ]);

            $prestamo->saldo_actual = $nuevoSaldo;
            $prestamo->estado = $estado;
            $prestamo->save();

            DB::commit();
            $prestamo->load('empleado');
            return response()->json($prestamo);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Estado de cuenta por empleado: movimientos de todos sus préstamos para el reporte.
     */
    public function estadoCuenta(Request $request)
    {
        $idEmpresa = auth()->user()->id_empresa;

        $request->validate([
            'id_empleado' => 'required|integer|exists:empleados,id',
        ]);

        $empleado = Empleado::where('id', $request->id_empleado)
            ->where('id_empresa', $idEmpresa)
            ->firstOrFail();

        $prestamos = PrestamoEmpleado::porEmpleado($empleado->id)
            ->porEmpresa($idEmpresa)
            ->with(['movimientos.planillaDetalle.planilla'])
            ->orderBy('numero_prestamo')
            ->get();

        $nombreEmpleado = trim($empleado->nombres . ' ' . $empleado->apellidos);
        $filas = [];
        $saldoTotal = 0;

        foreach ($prestamos as $prestamo) {
            $etiquetaPrestamo = 'Préstamo #' . $prestamo->numero_prestamo;

            foreach ($prestamo->movimientos as $mov) {
                $montoDesembolso = $mov->esDesembolso() ? (float) $mov->monto : 0;
                $montoAbono = $mov->esAbono() ? (float) $mov->monto : 0;

                $filas[] = [
                    'deuda_a_cuenta_empleado' => $nombreEmpleado,
                    'deuda_a_cuenta_prestamo' => $etiquetaPrestamo,
                    'monto' => round($montoDesembolso, 2),
                    'abono' => round($montoAbono, 2),
                    'total' => round((float) $mov->saldo_despues, 2),
                    'descripcion' => $mov->descripcion ?? $this->descripcionMovimiento($mov),
                    'fecha' => $mov->fecha->format('Y-m-d'),
                ];
            }

            $saldoPrestamo = (float) $prestamo->saldo_actual;
            $saldoTotal += $saldoPrestamo;

            $filas[] = [
                'deuda_a_cuenta_empleado' => $nombreEmpleado,
                'deuda_a_cuenta_prestamo' => $etiquetaPrestamo,
                'monto' => 0,
                'abono' => 0,
                'total' => round($saldoPrestamo, 2),
                'descripcion' => 'saldo',
                'fecha' => null,
                'es_resumen_prestamo' => true,
            ];
        }

        return response()->json([
            'empleado' => [
                'id' => $empleado->id,
                'nombres' => $empleado->nombres,
                'apellidos' => $empleado->apellidos,
                'codigo' => $empleado->codigo ?? '',
            ],
            'filas' => $filas,
            'saldo_total' => round($saldoTotal, 2),
            'prestamos' => $prestamos->map(fn ($p) => [
                'id' => $p->id,
                'numero_prestamo' => $p->numero_prestamo,
                'monto_inicial' => (float) $p->monto_inicial,
                'saldo_actual' => (float) $p->saldo_actual,
                'estado' => $p->estado,
            ]),
        ]);
    }

    private function descripcionMovimiento(PrestamoMovimiento $mov): string
    {
        if ($mov->tipo === PrestamoMovimiento::TIPO_DESEMBOLSO) {
            return 'Préstamo personal autorizado';
        }
        if ($mov->tipo === PrestamoMovimiento::TIPO_ABONO_PLANILLA && $mov->planillaDetalle) {
            $planilla = $mov->planillaDetalle->planilla;
            $periodo = $planilla ? $planilla->fecha_inicio->format('d/m/Y') . ' - ' . $planilla->fecha_fin->format('d/m/Y') : '';
            return 'Descuento en planilla ' . ($periodo ?: '');
        }
        if ($mov->tipo === PrestamoMovimiento::TIPO_ABONO_EFECTIVO) {
            return 'Abono según recibo efectivo';
        }
        return 'Abono';
    }
}

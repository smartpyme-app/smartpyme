<?php

namespace App\Http\Controllers\Api\Ventas\Creditos;

use App\Http\Controllers\Controller;
use App\Models\CreditosClientes\CreditoContrato;
use App\Models\CreditosClientes\CreditoCuota;
use App\Services\CreditosClientes\ColaCuotas;
use App\Services\CreditosClientes\CrearCreditoContratoService;
use App\Services\CreditosClientes\TipoDocumentoCredito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditosClientesController extends Controller
{
    public function __construct(private CrearCreditoContratoService $crearCredito)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $contratos = CreditoContrato::with('cliente')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->input('paginate', 25), 1), 100));

        return response()->json($contratos, 200);
    }

    public function cola(Request $request): JsonResponse
    {
        $hoy = now()->toDateString();
        $hasta = now()->addDays(ColaCuotas::VENTANA_DIAS)->toDateString();
        $filtroEstado = $request->input('estado');

        $query = CreditoCuota::query()
            ->with(['contrato.cliente'])
            ->whereNull('id_venta')
            ->whereDate('fecha_vencimiento', '<=', $hasta)
            ->whereHas('contrato')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id');

        if ($request->filled('id_cliente')) {
            $query->whereHas('contrato', fn ($q) => $q->where('id_cliente', $request->id_cliente));
        }

        $items = $query->get()
            ->map(function (CreditoCuota $cuota) use ($hoy, $filtroEstado) {
                $fecha = $cuota->fecha_vencimiento
                    ? $cuota->fecha_vencimiento->toDateString()
                    : '';
                $estado = ColaCuotas::estadoCola($cuota->id_venta, $fecha, $hoy);
                if ($estado === null) {
                    return null;
                }
                if ($filtroEstado && $estado !== $filtroEstado) {
                    return null;
                }

                return [
                    'id' => $cuota->id,
                    'id_contrato' => $cuota->id_contrato,
                    'numero' => $cuota->numero,
                    'monto' => (float) $cuota->monto,
                    'fecha_vencimiento' => $fecha,
                    'estado_cola' => $estado,
                    'cliente' => $cuota->contrato?->cliente,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $contrato = CreditoContrato::with(['cliente', 'cuotas'])->findOrFail($id);
        $hoy = now()->toDateString();
        $contrato->cuotas->each(function (CreditoCuota $cuota) use ($hoy) {
            $fecha = $cuota->fecha_vencimiento
                ? $cuota->fecha_vencimiento->toDateString()
                : '';
            $cuota->setAttribute(
                'estado_visible',
                ColaCuotas::estadoVisible($cuota->id_venta, $fecha, $hoy)
            );
        });

        return response()->json($contrato, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_cliente' => 'required|integer',
            'tipo' => 'required|in:bien,servicio,prestamo',
            'monto' => 'required|numeric|gt:0',
            'n_cuotas' => 'required|integer|min:2',
            'fecha_inicio' => 'required|date',
            'tasa_interes' => 'nullable|numeric|min:0',
            'tasa_mora' => 'nullable|numeric|min:0',
            'concepto' => 'nullable|string|max:255',
        ]);

        $contrato = $this->crearCredito->crear($request->user(), $data);

        return response()->json($contrato, 201);
    }

    public function prefill(int $id): JsonResponse
    {
        $cuota = CreditoCuota::with(['contrato.cliente'])->findOrFail($id);
        $contrato = $cuota->contrato;
        if (!$contrato) {
            return response()->json(['error' => 'La cuota no pertenece a un crédito válido.'], 422);
        }
        if (!TipoDocumentoCredito::puedeFacturar($cuota->id_venta)) {
            return response()->json(['error' => 'Esta cuota ya fue facturada.'], 422);
        }

        $fecha = $cuota->fecha_vencimiento
            ? $cuota->fecha_vencimiento->toDateString()
            : '';
        $descripcion = 'Cuota '.$cuota->numero
            .($contrato->concepto ? ' — '.$contrato->concepto : '');

        return response()->json([
            'id_cuota' => $cuota->id,
            'id_contrato' => $contrato->id,
            'id_cliente' => $contrato->id_cliente,
            'cliente' => $contrato->cliente,
            'monto' => (float) $cuota->monto,
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'id_documento' => $contrato->id_documento ? (int) $contrato->id_documento : null,
            'documento_bloqueado' => TipoDocumentoCredito::documentoBloqueado($contrato->id_documento),
        ]);
    }

    public function porVenta(int $idVenta): JsonResponse
    {
        $cuota = CreditoCuota::query()->where('id_venta', $idVenta)->first();
        if (!$cuota) {
            return response()->json(null, 200);
        }

        return response()->json([
            'id_cuota' => $cuota->id,
            'id_contrato' => $cuota->id_contrato,
            'numero' => $cuota->numero,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use App\Services\PrestamosEmpresa\AnularUltimoPagoService;
use App\Services\PrestamosEmpresa\CrearPrestamoService;
use App\Services\PrestamosEmpresa\PlanAmortizacion;
use App\Services\PrestamosEmpresa\RegistrarPagoService;
use App\Services\PrestamosEmpresa\ResumenPrestamos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PrestamosEmpresaController extends Controller
{
    public function __construct(
        private CrearPrestamoService $crear,
        private RegistrarPagoService $pagar,
        private AnularUltimoPagoService $anular,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        ResumenPrestamos::marcarAtrasadas();

        $query = PrestamoEmpresa::query();
        if ($request->filled('estado') && in_array($request->estado, ['activo', 'pagado'], true)) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo_acreedor') && in_array($request->tipo_acreedor, ['institucion', 'persona'], true)) {
            $query->where('tipo_acreedor', $request->tipo_acreedor);
        }
        if ($request->filled('buscador')) {
            $term = $request->input('buscador');
            $query->where(function ($inner) use ($term) {
                $inner->where('acreedor', 'like', '%'.$term.'%')
                    ->orWhere('concepto', 'like', '%'.$term.'%');
            });
        }

        $resumen = ResumenPrestamos::de(clone $query);

        $orden = $request->input('orden', 'id');
        $columnas = ['id', 'acreedor', 'monto', 'saldo', 'fecha_desembolso', 'estado', 'tipo_acreedor'];
        if (!in_array($orden, $columnas, true)) {
            $orden = 'id';
        }
        $direccion = strtolower((string) $request->input('direccion', 'desc')) === 'asc' ? 'asc' : 'desc';

        $page = $query
            ->orderBy($orden, $direccion)
            ->paginate(min(max((int) $request->input('paginate', 10), 1), 100));

        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'resumen' => $resumen,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'n_cuotas' => 'required|integer|min:2',
            'fecha_primera_cuota' => 'required|date',
            'frecuencia' => 'required|in:mensual,quincenal,semanal',
            'genera_interes' => 'sometimes|boolean',
            'tasa_interes' => 'nullable|numeric|min:0',
        ]);

        $plan = PlanAmortizacion::generar(
            (bool) ($data['genera_interes'] ?? false),
            (float) $data['monto'],
            (float) ($data['tasa_interes'] ?? 0),
            (int) $data['n_cuotas'],
            $data['fecha_primera_cuota'],
            $data['frecuencia']
        );

        return response()->json(['cuotas' => $plan]);
    }

    public function show(int $id): JsonResponse
    {
        ResumenPrestamos::marcarAtrasadas();
        $prestamo = PrestamoEmpresa::with(['cuotas', 'pagos'])->findOrFail($id);

        return response()->json($prestamo);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_acreedor' => 'required|in:institucion,persona',
            'acreedor' => 'required|string|max:191',
            'concepto' => 'nullable|string|max:191',
            'historico' => 'sometimes|boolean',
            'monto_original' => 'nullable|numeric|min:0',
            'monto' => 'required|numeric|min:0.01',
            'genera_interes' => 'sometimes|boolean',
            'tasa_interes' => 'nullable|numeric|min:0',
            'n_cuotas' => 'required|integer|min:2',
            'frecuencia' => 'required|in:mensual,quincenal,semanal',
            'fecha_desembolso' => 'required|date',
            'fecha_primera_cuota' => 'required|date',
            'id_cuenta_banco' => 'nullable|integer',
            'generar_asiento_desembolso' => 'sometimes|boolean',
            'cuotas' => 'nullable|array',
        ]);

        try {
            $user = auth()->user();
            $prestamo = $this->crear->crear($data, (int) $user->id_empresa, (int) $user->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($prestamo, 201);
    }

    public function updateCuotas(Request $request, int $id): JsonResponse
    {
        $prestamo = PrestamoEmpresa::with('cuotas')->findOrFail($id);
        $data = $request->validate([
            'cuotas' => 'required|array|min:1',
        ]);

        return response()->json($this->crear->actualizarCuotasPendientes($prestamo, $data['cuotas']));
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $prestamo = PrestamoEmpresa::with('cuotas')->findOrFail($id);
        $data = $request->validate([
            'fecha' => 'required|date',
            'metodo' => 'nullable|string|max:191',
            'referencia' => 'nullable|string|max:191',
            'detalle_banco' => 'nullable|string|max:191',
            'n_cuotas' => 'nullable|integer|min:1',
        ]);

        try {
            $pago = $this->pagar->registrar($prestamo, $data, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($pago, 201);
    }

    public function anularUltimoPago(int $id): JsonResponse
    {
        $prestamo = PrestamoEmpresa::with(['cuotas', 'pagos'])->findOrFail($id);

        try {
            $prestamo = $this->anular->anular($prestamo);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($prestamo);
    }
}

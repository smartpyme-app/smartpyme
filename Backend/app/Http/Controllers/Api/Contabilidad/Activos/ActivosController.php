<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\StoreActivoBajaRequest;
use App\Http\Requests\Contabilidad\StoreActivoRequest;
use App\Models\Compras\Detalle;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Contabilidad\Activo;
use App\Services\Contabilidad\BajaActivoService;
use App\Services\Contabilidad\DepreciacionService;
use Illuminate\Http\Request;
use JWTAuth;
use RuntimeException;

class ActivosController extends Controller
{
    public function __construct(
        private DepreciacionService $depreciacionService,
        private BajaActivoService $bajaActivoService,
    ) {}
    public function index(Request $request)
    {
        $activos = Activo::with(['categoria:id,nombre', 'sucursal:id,nombre', 'responsable:id,name'])
            ->when($request->buscador, function ($query) use ($request) {
                $term = '%'.$request->buscador.'%';

                return $query->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', $term)
                        ->orWhere('referencia', 'like', $term)
                        ->orWhere('numero_de_serie', 'like', $term);
                });
            })
            ->when($request->id_categoria, fn ($q) => $q->where('id_categoria', $request->id_categoria))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->when($request->estado, fn ($q) => $q->where('estado', $request->estado))
            ->when($request->estado_registro, fn ($q) => $q->where('estado_registro', $request->estado_registro))
            ->when($request->inicio, fn ($q) => $q->where('fecha_compra', '>=', $request->inicio))
            ->when($request->fin, fn ($q) => $q->where('fecha_compra', '<=', $request->fin))
            ->orderBy($request->input('orden', 'fecha_compra'), $request->input('direccion', 'desc'))
            ->orderBy('id', 'desc')
            ->paginate($request->input('paginate', 10));

        return response()->json($activos, 200);
    }

    public function filter(Request $request)
    {
        return $this->index($request);
    }

    public function read($id)
    {
        $activo = Activo::with(['categoria', 'sucursal', 'responsable'])->findOrFail($id);

        return response()->json($activo, 200);
    }

    public function pendientesCompra($compraId)
    {
        $lineas = Detalle::with('producto')
            ->where('id_compra', $compraId)
            ->where('es_activo_fijo', true)
            ->where('pendiente_capitalizacion', true)
            ->whereNull('id_activo')
            ->get()
            ->map(fn (Detalle $d) => [
                'id' => $d->id,
                'nombre' => $d->nombre_producto,
                'total' => (float) $d->total,
                'cantidad' => (float) $d->cantidad,
            ]);

        return response()->json($lineas, 200);
    }

    public function prefillFromCompraDetalle($id)
    {
        $detalle = Detalle::with(['producto', 'compra'])->findOrFail($id);

        if ($detalle->id_activo) {
            return response()->json(['error' => 'Esta línea de compra ya fue capitalizada.'], 422);
        }

        if (! $detalle->es_activo_fijo) {
            return response()->json(['error' => 'La línea no está marcada como activo fijo.'], 422);
        }

        $compra = $detalle->compra;
        if (! $compra || in_array($compra->estado, ['Anulada', 'Cancelada'], true)) {
            return response()->json(['error' => 'No se puede capitalizar una compra anulada o cancelada.'], 422);
        }

        return response()->json([
            'id_compra_detalle' => $detalle->id,
            'id_compra' => $compra->id,
            'nombre' => $detalle->nombre_producto,
            'valor_compra' => (float) $detalle->total,
            'fecha_compra' => $compra->fecha instanceof \DateTimeInterface
                ? $compra->fecha->format('Y-m-d')
                : $compra->fecha,
            'referencia' => $compra->referencia,
            'id_sucursal' => $compra->id_sucursal,
            'descripcion' => $compra->notas ?? $compra->observaciones,
        ], 200);
    }

    public function prefillFromEgreso($id)
    {
        $egreso = Gasto::findOrFail($id);

        if ($egreso->id_activo) {
            return response()->json(['error' => 'Este gasto ya fue capitalizado.'], 422);
        }

        if (in_array($egreso->estado, ['Anulada', 'Anulado', 'Cancelado'], true)) {
            return response()->json(['error' => 'No se puede capitalizar un gasto anulado o cancelado.'], 422);
        }

        return response()->json([
            'id_egreso' => $egreso->id,
            'nombre' => $egreso->concepto,
            'valor_compra' => (float) $egreso->total,
            'fecha_compra' => $egreso->fecha instanceof \DateTimeInterface
                ? $egreso->fecha->format('Y-m-d')
                : $egreso->fecha,
            'referencia' => $egreso->referencia,
            'id_sucursal' => $egreso->id_sucursal,
            'descripcion' => $egreso->nota,
        ], 200);
    }

    public function store(StoreActivoRequest $request)
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        $activo = $request->id
            ? Activo::findOrFail($request->id)
            : new Activo;

        $idEgreso = $request->input('id_egreso');
        if ($idEgreso) {
            $egreso = Gasto::findOrFail($idEgreso);
            if ($egreso->id_activo && (int) $egreso->id_activo !== (int) $request->id) {
                return response()->json(['error' => 'Este gasto ya fue capitalizado.'], 422);
            }
            if (in_array($egreso->estado, ['Anulada', 'Anulado', 'Cancelado'], true)) {
                return response()->json(['error' => 'No se puede capitalizar un gasto anulado o cancelado.'], 422);
            }
        }

        $idCompraDetalle = $request->input('id_compra_detalle');
        if ($idCompraDetalle) {
            $detalle = Detalle::with('compra')->findOrFail($idCompraDetalle);
            if ($detalle->id_activo && (int) $detalle->id_activo !== (int) $request->id) {
                return response()->json(['error' => 'Esta línea de compra ya fue capitalizada.'], 422);
            }
            $compra = $detalle->compra;
            if (! $compra || in_array($compra->estado, ['Anulada', 'Cancelada'], true)) {
                return response()->json(['error' => 'No se puede capitalizar una compra anulada o cancelada.'], 422);
            }
        }

        $activo->fill($request->validated());
        $activo->id_empresa = $usuario->id_empresa;
        $activo->id_usuario = $usuario->id;
        if (! $request->id) {
            $activo->estado_registro = 'activo';
        }
        $activo->estado = $activo->estado ?: 'En uso';
        $activo->depreciacion_acumulada = $activo->depreciacion_acumulada ?? 0;
        $activo->recalcularValorEnLibros();

        if ($idEgreso) {
            $activo->id_egreso = (int) $idEgreso;
        }

        if ($idCompraDetalle) {
            $activo->id_compra_detalle = (int) $idCompraDetalle;
        }

        if (! $activo->fecha_inicio_depreciacion) {
            $activo->fecha_inicio_depreciacion = $activo->fecha_compra;
        }

        $activo->save();
        $activo->load(['categoria', 'sucursal', 'responsable']);

        if ($activo->id_egreso) {
            Gasto::where('id', $activo->id_egreso)->update([
                'id_activo' => $activo->id,
                'pendiente_capitalizacion' => false,
            ]);
        }

        if ($activo->id_compra_detalle) {
            Detalle::where('id', $activo->id_compra_detalle)->update([
                'id_activo' => $activo->id,
                'pendiente_capitalizacion' => false,
            ]);
        }

        if ($activo->estado_registro === 'activo') {
            $this->depreciacionService->generarCronograma($activo);
        }

        return response()->json($activo, 200);
    }

    public function baja(StoreActivoBajaRequest $request, $id)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $activo = Activo::findOrFail($id);

        try {
            $activo = $this->bajaActivoService->registrar($activo, $request->validated(), $usuario->id);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($activo, 200);
    }

    public function delete($id)
    {
        $activo = Activo::findOrFail($id);
        $activo->delete();

        return response()->json($activo, 201);
    }
}

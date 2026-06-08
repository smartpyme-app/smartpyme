<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Restaurante\DivisionCuenta;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PreCuentaController extends Controller
{
    /**
     * Propina según porcentaje de empresa (sin override por mesa). Base: subtotal de consumo (sin IVA).
     * IVA se calcula por línea según porcentaje del producto o de la empresa.
     *
     * @return array{subtotal: float, impuesto: float, propina_monto: float, propina_porcentaje_aplicado: float, total: float}
     */
    private function totalesPreCuentaConPropina(float $subtotalConsumo, float $impuesto, ?Empresa $empresa): array
    {
        $sub = round($subtotalConsumo, 2);
        $imp = round($impuesto, 2);
        $pct = $empresa ? max(0, (float) ($empresa->propina_porcentaje ?? 0)) : 0;
        $propina = round($sub * ($pct / 100), 2);

        return [
            'subtotal' => $sub,
            'impuesto' => $imp,
            'propina_monto' => $propina,
            'propina_porcentaje_aplicado' => $pct,
            'total' => round($sub + $imp + $propina, 2),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection|iterable  $items  OrdenDetalle con producto cargado
     */
    private function calcularImpuestoItems(iterable $items, ?Empresa $empresa): float
    {
        $ivaEmpresa = $empresa ? max(0, (float) ($empresa->iva ?? 0)) : 0;
        $impuesto = 0.0;

        foreach ($items as $item) {
            if (method_exists($item, 'loadMissing')) {
                $item->loadMissing('producto');
            }
            $pct = $item->producto->porcentaje_impuesto ?? $ivaEmpresa;
            $pct = max(0, (float) $pct);
            if ($pct <= 0) {
                continue;
            }
            $cant = $this->cantidadLineaParaPreCuenta($item);
            $base = $cant * (float) $item->precio_unitario;
            $impuesto += $base * ($pct / 100);
        }

        return round($impuesto, 2);
    }

    /**
     * @param  \Illuminate\Support\Collection|iterable  $items
     */
    private function calcularSubtotalItems(iterable $items): float
    {
        return round(collect($items)->sum(fn ($i) => $this->cantidadLineaParaPreCuenta($i) * (float) $i->precio_unitario), 2);
    }

    /**
     * Cantidad a cobrar en esta pre-cuenta para la línea (pivot o línea completa).
     */
    private function cantidadLineaParaPreCuenta($ordenDetalleRow): float
    {
        $pivot = $ordenDetalleRow->pivot ?? null;
        if ($pivot && ($pivot->cantidad !== null && $pivot->cantidad !== '')) {
            return round((float) $pivot->cantidad, 4);
        }

        return round((float) $ordenDetalleRow->cantidad, 4);
    }

    /**
     * Tras facturar una pre-cuenta con ítems vinculados, descuenta o elimina líneas de orden cobradas.
     */
    private function liquidarOrdenTrasFacturarPreCuenta(PreCuenta $preCuenta): void
    {
        $preCuenta->loadMissing('ordenDetalles');
        if ($preCuenta->ordenDetalles->isEmpty()) {
            return;
        }

        $sesionId = $preCuenta->sesion_id;
        foreach ($preCuenta->ordenDetalles as $od) {
            $item = OrdenDetalle::where('sesion_id', $sesionId)->whereKey($od->id)->first();
            if (!$item) {
                continue;
            }
            $liq = $this->cantidadLineaParaPreCuenta($od);
            if ($liq <= 0) {
                continue;
            }
            $actual = round((float) $item->cantidad, 4);
            if ($liq + 0.0001 >= $actual) {
                $item->delete();

                continue;
            }
            $nueva = round($actual - $liq, 2);
            if ($nueva <= 0.009) {
                $item->delete();
            } else {
                $item->update(['cantidad' => $nueva]);
            }
        }
    }

    /**
     * Elimina pre-cuentas pendientes y divisiones huérfanas para evitar varias pre-cuentas
     * abiertas al volver a solicitar la cuenta en la misma sesión.
     */
    private function anularPreCuentasPendientesDeSesion(int $sesionId): void
    {
        $pendientes = PreCuenta::where('sesion_id', $sesionId)->where('estado', 'pendiente')->get();
        foreach ($pendientes as $pc) {
            PreCuentaOrdenDetalle::where('pre_cuenta_id', $pc->id)->delete();
            $pc->delete();
        }

        DivisionCuenta::where('sesion_id', $sesionId)
            ->whereDoesntHave('preCuentas')
            ->delete();
    }

    /**
     * Una sola línea por producto + precio + notas (ticket / facturación).
     *
     * @param  \Illuminate\Support\Collection|iterable  $items
     */
    private function lineasAgrupadasParaVista(iterable $items): array
    {
        return collect($items)
            ->groupBy(function ($i) {
                $n = $i->notas ?? '';
                $nk = trim((string) $n) === '' ? '' : trim((string) $n);

                return $i->producto_id.'|'.round((float) $i->precio_unitario, 2).'|'.$nk;
            })
            ->map(function ($grupo) {
                $first = $grupo->sortBy('id')->first();
                $cant = (float) $grupo->sum(fn ($x) => $this->cantidadLineaParaPreCuenta($x));

                return (object) [
                    'cantidad' => $cant,
                    'precio_unitario' => $first->precio_unitario,
                    'notas' => $first->notas,
                    'producto' => $first->producto ?? null,
                    'producto_id' => $first->producto_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Valida el cuerpo `dividir` para generar pre-cuentas sin paso intermedio.
     *
     * @return array{tipo: string, num_pagadores: int, asignaciones?: array<int, array<string, mixed>>}
     */
    private function validarPayloadDividir(array $dividir): array
    {
        return Validator::make($dividir, [
            'tipo' => 'required|in:equitativa,por_items',
            'num_pagadores' => 'required|integer|min:2',
            'asignaciones' => 'required_if:tipo,por_items|array',
            'asignaciones.*.orden_detalle_id' => 'required_with:asignaciones|integer',
            'asignaciones.*.pagador_index' => 'required_with:asignaciones|integer|min:1',
            'asignaciones.*.cantidad' => 'nullable|numeric|min:0.0001',
        ])->validate();
    }

    /**
     * Crea división y pre-cuentas a partir del consumo actual (sin pre-cuenta “puente”).
     *
     * @param  array{tipo: string, num_pagadores: int, asignaciones?: array}  $validated
     * @return Collection<int, PreCuenta>
     */
    private function ejecutarDivisionDesdeItems(SesionMesa $sesion, Collection $items, ?Empresa $empresa, array $validated): Collection
    {
        $sesion->loadMissing('mesa');
        $total = $items->sum(fn ($i) => $i->cantidad * $i->precio_unitario);
        $n = $validated['num_pagadores'];

        $division = DivisionCuenta::create([
            'sesion_id' => $sesion->id,
            'tipo' => $validated['tipo'],
            'num_pagadores' => $n,
        ]);

        if ($validated['tipo'] === 'equitativa') {
            $subtotalTotal = $this->calcularSubtotalItems($items);
            $impuestoTotal = $this->calcularImpuestoItems($items, $empresa);
            $montoPorPersona = round($total / $n, 2);
            $impuestoAcum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $sub = $i === $n - 1 ? round($total - ($montoPorPersona * ($n - 1)), 2) : $montoPorPersona;
                if ($i === $n - 1) {
                    $imp = round($impuestoTotal - $impuestoAcum, 2);
                } else {
                    $imp = $subtotalTotal > 0 ? round($impuestoTotal * ($sub / $subtotalTotal), 2) : 0;
                    $impuestoAcum += $imp;
                }
                $t = $this->totalesPreCuentaConPropina((float) $sub, (float) $imp, $empresa);
                PreCuenta::create([
                    'sesion_id' => $sesion->id,
                    'division_cuenta_id' => $division->id,
                    'subtotal' => $t['subtotal'],
                    'descuento' => 0,
                    'impuesto' => $t['impuesto'],
                    'propina_monto' => $t['propina_monto'],
                    'propina_porcentaje_aplicado' => $t['propina_porcentaje_aplicado'],
                    'total' => $t['total'],
                    'estado' => 'pendiente',
                    'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-".($i + 1),
                ]);
            }
        } else {
            $asignaciones = collect($validated['asignaciones'] ?? []);
            if ($asignaciones->isEmpty()) {
                throw ValidationException::withMessages(['asignaciones' => 'Indique cómo reparte cada ítem entre las personas.']);
            }

            $groupedByOd = $asignaciones->groupBy(fn ($a) => (int) ($a['orden_detalle_id'] ?? 0));

            foreach ($groupedByOd as $ordenDetalleId => $rows) {
                if ($ordenDetalleId <= 0) {
                    throw ValidationException::withMessages(['asignaciones' => 'Ítem de orden inválido.']);
                }
                $item = OrdenDetalle::where('sesion_id', $sesion->id)->find($ordenDetalleId);
                if (! $item) {
                    throw ValidationException::withMessages(['asignaciones' => 'La línea de orden no pertenece a esta mesa.']);
                }
                $item->loadMissing('producto');
                $lineQty = round((float) $item->cantidad, 4);
                $rowCount = $rows->count();
                $sumQty = 0.0;
                if ($rowCount === 1) {
                    $only = $rows->first();
                    $c = $only['cantidad'] ?? null;
                    if ($c === null || $c === '') {
                        $sumQty = $lineQty;
                    } else {
                        $sumQty = round((float) $c, 4);
                    }
                } else {
                    foreach ($rows as $r) {
                        $c = $r['cantidad'] ?? null;
                        if ($c === null || $c === '') {
                            throw ValidationException::withMessages(['asignaciones' => 'Cuando reparte una línea entre varias personas, indique la cantidad para cada asignación.']);
                        }
                        $sumQty += round((float) $c, 4);
                    }
                }
                if (abs($sumQty - $lineQty) > 0.021) {
                    $nom = $item->producto->nombre ?? 'ítem';
                    throw ValidationException::withMessages([
                        'asignaciones' => "Las cantidades para «{$nom}» deben sumar {$lineQty} (actual: {$sumQty}).",
                    ]);
                }
            }

            foreach ($items as $sessItem) {
                if (! $groupedByOd->has((int) $sessItem->id)) {
                    $sessItem->loadMissing('producto');
                    $nom = $sessItem->producto->nombre ?? 'ítem';
                    throw ValidationException::withMessages([
                        'asignaciones' => "Falta repartir «{$nom}» entre las personas.",
                    ]);
                }
            }

            $totales = array_fill(0, $n, 0);
            $preCuentasCreadas = [];
            for ($i = 0; $i < $n; $i++) {
                $pc = PreCuenta::create([
                    'sesion_id' => $sesion->id,
                    'division_cuenta_id' => $division->id,
                    'subtotal' => 0,
                    'descuento' => 0,
                    'impuesto' => 0,
                    'total' => 0,
                    'estado' => 'pendiente',
                    'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-".($i + 1),
                ]);
                $preCuentasCreadas[$i] = $pc;
            }

            $aggregate = [];
            foreach ($asignaciones as $asig) {
                $item = OrdenDetalle::where('sesion_id', $sesion->id)->find((int) ($asig['orden_detalle_id'] ?? 0));
                if (! $item) {
                    continue;
                }
                $idx = (int) ($asig['pagador_index'] ?? 1) - 1;
                if ($idx < 0 || $idx >= $n) {
                    throw ValidationException::withMessages(['asignaciones' => 'Índice de persona inválido.']);
                }
                $rowsForLine = $groupedByOd->get((int) $item->id);
                $rowCount = $rowsForLine ? $rowsForLine->count() : 0;
                $c = $asig['cantidad'] ?? null;
                if ($rowCount === 1 && ($c === null || $c === '')) {
                    $qty = round((float) $item->cantidad, 4);
                } else {
                    $qty = round((float) $c, 4);
                }
                if ($qty <= 0) {
                    continue;
                }
                $key = $idx.'_'.$item->id;
                if (! isset($aggregate[$key])) {
                    $aggregate[$key] = ['idx' => $idx, 'item' => $item, 'qty' => 0.0];
                }
                $aggregate[$key]['qty'] += $qty;
            }

            foreach ($aggregate as $row) {
                $qty = round($row['qty'], 4);
                if ($qty <= 0) {
                    continue;
                }
                $item = $row['item'];
                $idx = $row['idx'];
                $totales[$idx] += $qty * (float) $item->precio_unitario;
                PreCuentaOrdenDetalle::create([
                    'pre_cuenta_id' => $preCuentasCreadas[$idx]->id,
                    'orden_detalle_id' => $item->id,
                    'cantidad' => $qty,
                ]);
            }

            foreach ($preCuentasCreadas as $i => $pc) {
                $pc->load('ordenDetalles.producto');
                $imp = $this->calcularImpuestoItems($pc->ordenDetalles, $empresa);
                $t = $this->totalesPreCuentaConPropina((float) $totales[$i], (float) $imp, $empresa);
                $pc->update([
                    'subtotal' => $t['subtotal'],
                    'impuesto' => $t['impuesto'],
                    'propina_monto' => $t['propina_monto'],
                    'propina_porcentaje_aplicado' => $t['propina_porcentaje_aplicado'],
                    'total' => $t['total'],
                ]);
            }
        }

        return PreCuenta::where('division_cuenta_id', $division->id)
            ->with(['sesion.ordenDetalle.producto', 'sesion.mesa'])
            ->get();
    }

    public function generar(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->with(['mesa'])
            ->findOrFail($id);

        if (!in_array($sesion->estado, ['abierta', 'pre_cuenta'])) {
            return response()->json(['error' => 'La sesión no está abierta'], 422);
        }

        DB::beginTransaction();
        try {
            $this->anularPreCuentasPendientesDeSesion($sesion->id);

            $items = OrdenDetalle::where('sesion_id', $sesion->id)->with('producto')->get();
            if ($items->isEmpty()) {
                DB::rollBack();

                return response()->json(['error' => 'No hay ítems en la orden'], 422);
            }

            $empresa = Empresa::find($user->id_empresa);
            $dividirPayload = $request->input('dividir');

            if (is_array($dividirPayload) && isset($dividirPayload['tipo'])) {
                $validatedDiv = $this->validarPayloadDividir($dividirPayload);
                $preCuentas = $this->ejecutarDivisionDesdeItems($sesion, $items, $empresa, $validatedDiv);
                DB::commit();

                return response()->json($preCuentas, 201);
            }

            $subtotal = $this->calcularSubtotalItems($items);
            $impuesto = $this->calcularImpuestoItems($items, $empresa);
            $t = $this->totalesPreCuentaConPropina($subtotal, $impuesto, $empresa);
            $numero = PreCuenta::where('sesion_id', $sesion->id)->count() + 1;

            $preCuenta = PreCuenta::create([
                'sesion_id' => $sesion->id,
                'subtotal' => $t['subtotal'],
                'descuento' => 0,
                'impuesto' => $t['impuesto'],
                'propina_monto' => $t['propina_monto'],
                'propina_porcentaje_aplicado' => $t['propina_porcentaje_aplicado'],
                'total' => $t['total'],
                'estado' => 'pendiente',
                'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-{$numero}",
            ]);

            foreach ($items as $item) {
                PreCuentaOrdenDetalle::create([
                    'pre_cuenta_id' => $preCuenta->id,
                    'orden_detalle_id' => $item->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $preCuenta->load(['sesion.ordenDetalle.producto', 'sesion.mesa', 'sesion.mesero']);

        return response()->json($preCuenta, 201);
    }

    public function dividir(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with('sesion.mesa')
            ->findOrFail($id);

        if ($preCuenta->estado !== 'pendiente') {
            return response()->json(['error' => 'Solo se puede dividir una pre-cuenta pendiente.'], 422);
        }

        $validated = $this->validarPayloadDividir($request->all());

        $sesion = $preCuenta->sesion;
        $items = OrdenDetalle::where('sesion_id', $sesion->id)->with('producto')->get();
        $empresa = Empresa::find($user->id_empresa);

        DB::beginTransaction();
        try {
            PreCuentaOrdenDetalle::where('pre_cuenta_id', $preCuenta->id)->delete();
            $preCuenta->delete();
            $preCuentas = $this->ejecutarDivisionDesdeItems($sesion, $items, $empresa, $validated);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json($preCuentas, 201);
    }

    public function prepararFactura(int $id): JsonResponse
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with(['sesion.ordenDetalle.producto', 'sesion.mesa', 'sesion.mesero', 'ordenDetalles.producto'])
            ->findOrFail($id);

        if ($preCuenta->estado === 'facturada') {
            return response()->json(['error' => 'La pre-cuenta ya fue facturada'], 422);
        }

        $items = $preCuenta->ordenDetalles->isNotEmpty()
            ? $preCuenta->ordenDetalles
            : $preCuenta->sesion->ordenDetalle;

        $agrupadas = $this->lineasAgrupadasParaVista($items);
        $empresa = Empresa::find(auth()->user()->id_empresa);
        $ivaEmpresa = $empresa ? max(0, (float) ($empresa->iva ?? 0)) : 0;
        $detalles = collect($agrupadas)->map(function ($i) use ($ivaEmpresa) {
            $pct = $i->producto->porcentaje_impuesto ?? $ivaEmpresa;
            $pct = max(0, (float) $pct);
            $precioSinIva = (float) $i->precio_unitario;
            $precioConIva = $pct > 0 ? round($precioSinIva * (1 + $pct / 100), 4) : $precioSinIva;

            return [
                'id_producto' => $i->producto_id,
                'cantidad' => $i->cantidad,
                'precio' => $precioSinIva,
                'precio_con_iva' => $precioConIva,
                'porcentaje_impuesto' => $pct,
                'descripcion' => $i->producto->nombre ?? '',
            ];
        })->values()->toArray();

        return response()->json([
            'pre_cuenta_id' => $preCuenta->id,
            'sesion_id' => $preCuenta->sesion_id,
            'mesa_numero' => $preCuenta->sesion->mesa->numero,
            'subtotal' => $preCuenta->subtotal,
            'impuesto' => (float) ($preCuenta->impuesto ?? 0),
            'propina_monto' => (float) ($preCuenta->propina_monto ?? 0),
            'propina_porcentaje_aplicado' => (float) ($preCuenta->propina_porcentaje_aplicado ?? 0),
            'total' => $preCuenta->total,
            'precios_sin_iva' => true,
            'detalles' => $detalles,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with(['sesion.ordenDetalle.producto', 'sesion.mesa', 'sesion.mesero', 'ordenDetalles.producto'])
            ->findOrFail($id);

        return response()->json($preCuenta);
    }

    public function imprimir(int $id)
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with(['sesion.mesa', 'sesion.mesero', 'sesion.ordenDetalle.producto', 'ordenDetalles.producto'])
            ->findOrFail($id);

        $items = $preCuenta->ordenDetalles->isNotEmpty()
            ? $preCuenta->ordenDetalles
            : $preCuenta->sesion->ordenDetalle;

        $itemsAgrupados = $this->lineasAgrupadasParaVista($items);

        $empresa = Empresa::find($user->id_empresa);

        return response()->view('restaurante.pre-cuenta-ticket', [
            'preCuenta' => $preCuenta,
            'items' => $itemsAgrupados,
            'empresa' => $empresa,
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function marcarFacturada(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with(['sesion.mesa'])
            ->findOrFail($id);

        if ($preCuenta->estado === 'facturada') {
            return response()->json(['error' => 'La pre-cuenta ya fue facturada'], 422);
        }

        $validated = $request->validate([
            'factura_id' => 'required|integer|exists:ventas,id',
        ]);

        $todasFacturadas = false;
        DB::beginTransaction();
        try {
            $preCuenta->update([
                'estado' => 'facturada',
                'factura_id' => $validated['factura_id'],
            ]);

            $preCuenta->load('ordenDetalles');
            $this->liquidarOrdenTrasFacturarPreCuenta($preCuenta);

            $sesion = $preCuenta->sesion;
            $todasFacturadas = PreCuenta::where('sesion_id', $sesion->id)
                ->where('estado', '!=', 'facturada')
                ->doesntExist();

            if ($todasFacturadas) {
                $sesion->update([
                    'estado' => 'cerrada',
                    'closed_at' => now(),
                ]);
                $sesion->mesa?->update(['estado' => 'libre']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'pre_cuenta' => $preCuenta->fresh(),
            'sesion_cerrada' => $todasFacturadas,
        ]);
    }
}

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
use Illuminate\Support\Facades\DB;

class PreCuentaController extends Controller
{
    public function generar(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->with(['ordenDetalle.producto', 'mesa'])
            ->findOrFail($id);

        if (!in_array($sesion->estado, ['abierta', 'pre_cuenta'])) {
            return response()->json(['error' => 'La sesión no está abierta'], 422);
        }

        $items = $sesion->ordenDetalle;
        if ($items->isEmpty()) {
            return response()->json(['error' => 'No hay ítems en la orden'], 422);
        }

        $subtotal = $items->sum(fn ($i) => $i->cantidad * $i->precio_unitario);
        $numero = PreCuenta::where('sesion_id', $sesion->id)->count() + 1;

        $preCuenta = PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => $subtotal,
            'descuento' => 0,
            'impuesto' => 0,
            'total' => $subtotal,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-{$numero}",
        ]);

        foreach ($items as $item) {
            PreCuentaOrdenDetalle::create([
                'pre_cuenta_id' => $preCuenta->id,
                'orden_detalle_id' => $item->id,
            ]);
        }

        $sesion->update(['estado' => 'pre_cuenta']);
        $sesion->mesa->update(['estado' => 'pendiente_pago']);

        $preCuenta->load(['sesion.ordenDetalle.producto', 'sesion.mesa', 'sesion.mesero']);
        return response()->json($preCuenta, 201);
    }

    public function dividir(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with('sesion.ordenDetalle.producto')
            ->findOrFail($id);

        $validated = $request->validate([
            'tipo' => 'required|in:equitativa,por_items',
            'num_pagadores' => 'required|integer|min:2',
            'asignaciones' => 'required_if:tipo,por_items|array',
            'asignaciones.*.orden_detalle_id' => 'required_with:asignaciones|integer',
            'asignaciones.*.pagador_index' => 'required_with:asignaciones|integer|min:1',
        ]);

        $sesion = $preCuenta->sesion;
        $items = $sesion->ordenDetalle;
        $total = $items->sum(fn ($i) => $i->cantidad * $i->precio_unitario);
        $n = $validated['num_pagadores'];

        DB::beginTransaction();
        try {
            $division = DivisionCuenta::create([
                'sesion_id' => $sesion->id,
                'tipo' => $validated['tipo'],
                'num_pagadores' => $n,
            ]);

            $preCuenta->update(['division_cuenta_id' => $division->id]);

            if ($validated['tipo'] === 'equitativa') {
                $montoPorPersona = round($total / $n, 2);
                for ($i = 0; $i < $n; $i++) {
                    $subtotal = $i === $n - 1 ? round($total - ($montoPorPersona * ($n - 1)), 2) : $montoPorPersona;
                    PreCuenta::create([
                        'sesion_id' => $sesion->id,
                        'division_cuenta_id' => $division->id,
                        'subtotal' => $subtotal,
                        'descuento' => 0,
                        'impuesto' => 0,
                        'total' => $subtotal,
                        'estado' => 'pendiente',
                        'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-" . ($i + 1),
                    ]);
                }
                PreCuentaOrdenDetalle::where('pre_cuenta_id', $preCuenta->id)->delete();
                $preCuenta->delete();
            } else {
                $totales = array_fill(0, $n, 0);
                $preCuentasCreadas = [];
                PreCuentaOrdenDetalle::where('pre_cuenta_id', $preCuenta->id)->delete();
                $preCuenta->delete();

                for ($i = 0; $i < $n; $i++) {
                    $pc = PreCuenta::create([
                        'sesion_id' => $sesion->id,
                        'division_cuenta_id' => $division->id,
                        'subtotal' => 0,
                        'descuento' => 0,
                        'impuesto' => 0,
                        'total' => 0,
                        'estado' => 'pendiente',
                        'numero_pre_cuenta' => "PC-{$sesion->mesa->numero}-" . ($i + 1),
                    ]);
                    $preCuentasCreadas[$i] = $pc;
                }

                foreach ($validated['asignaciones'] ?? [] as $asig) {
                    $item = OrdenDetalle::where('sesion_id', $sesion->id)->find($asig['orden_detalle_id'] ?? 0);
                    $idx = isset($asig['pagador_index']) ? (int) $asig['pagador_index'] - 1 : 0;
                    if ($item && $idx >= 0 && $idx < $n) {
                        $monto = $item->cantidad * $item->precio_unitario;
                        $totales[$idx] += $monto;
                        PreCuentaOrdenDetalle::create([
                            'pre_cuenta_id' => $preCuentasCreadas[$idx]->id,
                            'orden_detalle_id' => $item->id,
                        ]);
                    }
                }

                foreach ($preCuentasCreadas as $i => $pc) {
                    $pc->update([
                        'subtotal' => round($totales[$i], 2),
                        'total' => round($totales[$i], 2),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $preCuentas = PreCuenta::where('division_cuenta_id', $division->id)
            ->with(['sesion.ordenDetalle.producto', 'sesion.mesa'])
            ->get();

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

        $detalles = $items->map(fn ($i) => [
            'id_producto' => $i->producto_id,
            'cantidad' => $i->cantidad,
            'precio' => $i->precio_unitario,
            'descripcion' => $i->producto->nombre ?? '',
        ])->values()->toArray();

        return response()->json([
            'pre_cuenta_id' => $preCuenta->id,
            'sesion_id' => $preCuenta->sesion_id,
            'mesa_numero' => $preCuenta->sesion->mesa->numero,
            'subtotal' => $preCuenta->subtotal,
            'total' => $preCuenta->total,
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

        $empresa = Empresa::find($user->id_empresa);

        return response()->view('restaurante.pre-cuenta-ticket', [
            'preCuenta' => $preCuenta,
            'items' => $items,
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

        DB::beginTransaction();
        try {
            $preCuenta->update([
                'estado' => 'facturada',
                'factura_id' => $validated['factura_id'],
            ]);

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

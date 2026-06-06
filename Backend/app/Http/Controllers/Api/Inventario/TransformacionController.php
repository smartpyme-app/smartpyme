<?php

namespace App\Http\Controllers\Api\Inventario;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Transformacion;
use App\Models\Inventario\TransformacionDetalle;
use App\Models\Inventario\Inventario;

class TransformacionController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        if (!$empresa || !$empresa->isTransformacionProductosActivo()) {
            return response()->json([
                'success' => false,
                'message' => 'El módulo de transformación de productos no está habilitado para esta empresa.',
            ], 403);
        }

        $request->validate([
            'id_bodega' => 'required|integer',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.tipo' => 'required|in:ENTRADA,SALIDA',
            'observacion' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $Transformacion = Transformacion::create([
                'id_usuario' => Auth::id() ?? 1,
                'id_bodega' => $request->id_bodega,
                'fecha' => now(),
                'observacion' => $request->observacion,
            ]);

            foreach ($request->detalles as $detalle) {
                // Crear el registro detalle
                $transDetalle = TransformacionDetalle::create([
                    'id_transformacion' => $Transformacion->id,
                    'id_producto' => $detalle['id_producto'],
                    'cantidad' => $detalle['cantidad'],
                    'tipo' => $detalle['tipo'],
                ]);

                // Buscar Inventario
                $inventario = Inventario::where('id_producto', $detalle['id_producto'])
                    ->where('id_bodega', $request->id_bodega)
                    ->first();

                if ($detalle['tipo'] === 'ENTRADA') {
                    if (!$inventario) {
                        $inventario = Inventario::create([
                            'id_producto' => $detalle['id_producto'],
                            'id_bodega' => $request->id_bodega,
                            'stock' => 0,
                            'stock_minimo' => 0,
                            'stock_maximo' => 0,
                        ]);
                    }
                    $inventario->stock += $detalle['cantidad'];
                } elseif ($detalle['tipo'] === 'SALIDA') {
                    if (!$inventario || $inventario->stock < $detalle['cantidad']) {
                        throw new \Exception('Stock insuficiente para el producto ID: ' . $detalle['id_producto']);
                    }
                    $inventario->stock -= $detalle['cantidad'];
                }

                $inventario->save();

                // Registrar en kardex
                $cantidadKardex = $detalle['tipo'] === 'ENTRADA' ? $detalle['cantidad'] : -$detalle['cantidad'];
                $inventario->kardex($Transformacion, $cantidadKardex);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transformación procesada exitosamente',
                'Transformacion' => $Transformacion->load('detalles'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la transformación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Compras\Retaceo;


use App\Models\Inventario\Producto;
use App\Models\Compras\Detalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Compras\Compra;
use App\Models\Compras\Retaceo\Retaceo;
use App\Models\Compras\Retaceo\RetaceoDistribucion;
use App\Models\Compras\Retaceo\RetaceoGasto;
use App\Models\Inventario\Inventario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RetaceoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $retaceos = Retaceo::when($request->has('inicio') && !empty($request->inicio), function ($query) use ($request) {
            return $query->where('fecha', '>=', $request->inicio);
        })
        ->when($request->has('fin') && !empty($request->fin), function ($query) use ($request) {
            return $query->where('fecha', '<=', $request->fin);
        })
        ->when($request->id_usuario, function ($query) use ($request) {
            return $query->where('id_usuario', $request->id_usuario);
        })
        ->when($request->id_sucursal, function ($query) use ($request) {
            return $query->where('id_sucursal', $request->id_sucursal);
        })
        ->when($request->id_bodega, function ($query) use ($request) {
            return $query->where('id_bodega', $request->id_bodega);
        })
        ->when($request->estado, function ($query) use ($request) {
            return $query->where('estado', $request->estado);
        })
        ->when($request->has('busqueda') && !empty($request->busqueda), function ($query) use ($request) {
            $busqueda = $request->busqueda;
            return $query->where(function ($q) use ($busqueda) {
                $q->where('numero_duca', 'like', '%' . $busqueda . '%')
                  ->orWhere('numero_factura', 'like', '%' . $busqueda . '%')
                  ->orWhereHas('compra', function($subq) use ($busqueda) {
                      $subq->where('codigo', 'like', '%' . $busqueda . '%');
                  });
            });
        })
        ->with(['compra', 'gastos', 'distribucion'])  // Eliminé la carga de 'cliente'
        ->orderBy($request->orden, $request->direccion)
        ->orderBy('id', 'desc')
        ->paginate($request->paginate);

        return Response()->json($retaceos, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validar datos
        $validator = Validator::make($request->all(), [
            'id_compra' => 'required|exists:compras,id',
            'fecha' => 'required|date',
            'total_gastos' => 'required|numeric|min:0',
            'gastos' => 'required|array|min:1',
            'distribucion' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
      //  dd($request->all());
      Log::info($request->id_usuario);

        try {
            DB::beginTransaction();


            // Crear el retaceo
            $compra = Compra::findOrFail($request->id_compra);
            $retaceo = new Retaceo();
            $retaceo->codigo = 'RET-' . date('Ymd') . '-' . rand(1000, 9999);
            $retaceo->id_compra = $request->id_compra;
            $retaceo->numero_duca = $request->numero_duca;
            $retaceo->tasa_dai = $request->tasa_dai;
            $retaceo->numero_factura = $compra->referencia;
            $retaceo->incoterm = $request->incoterm;
            $retaceo->fecha = $request->fecha;
            $retaceo->observaciones = $request->observaciones;
            $retaceo->total_gastos = $request->total_gastos;
            $retaceo->total_retaceado = $request->total_retaceado;
            $retaceo->id_empresa = $request->id_empresa;
            $retaceo->id_sucursal = $request->id_sucursal;
            $retaceo->id_bodega = $compra->id_bodega;
            $retaceo->id_usuario = $request->id_usuario;
            $retaceo->save();

            // Guardar los gastos
            foreach ($request->gastos as $gasto) {
                if (!empty($gasto['id_gasto']) && $gasto['monto'] > 0) {
                    $retaceoGasto = new RetaceoGasto();
                    $retaceoGasto->id_retaceo = $retaceo->id;
                    $retaceoGasto->id_gasto = $gasto['id_gasto'];
                    $retaceoGasto->tipo_gasto = $gasto['tipo_gasto'];
                    $retaceoGasto->monto = $gasto['monto'];
                    $retaceoGasto->save();
                }
            }

            // Guardar la distribución
            foreach ($request->distribucion as $item) {
                $distribucion = new RetaceoDistribucion();
                $distribucion->id_retaceo = $retaceo->id;
                $distribucion->id_producto = $item['id_producto'];
                $distribucion->id_detalle_compra = $item['id_detalle_compra'];
                $distribucion->cantidad = $item['cantidad'];
                $distribucion->costo_original = $item['costo_original'];
                $distribucion->valor_fob = $item['valor_fob'];
                $distribucion->porcentaje_distribucion = $item['porcentaje_distribucion'];
                $distribucion->monto_transporte = $item['monto_transporte'];
                $distribucion->monto_seguro = $item['monto_seguro'];
                $distribucion->monto_dai = $item['monto_dai'];
                $distribucion->monto_otros = $item['monto_otros'];
                $distribucion->costo_landed = $item['costo_landed'];
                $distribucion->costo_retaceado = $item['costo_retaceado'];
                $distribucion->porcentaje_dai = $item['porcentaje_dai'];
                $distribucion->save();

                // Actualizar el costo del producto
                $producto = Producto::find($item['id_producto']);
                if ($producto) {
                    $producto->costo = $item['costo_retaceado'];
                    $producto->save();
                    $inventario = Inventario::where('id_producto', $item['id_producto'])->where('id_bodega', $compra->id_bodega)->first();
                    if ($inventario) {
                        $producto->id_usuario = Auth::id();
                        $inventario->kardex($producto, 0, $producto->precio, $producto->costo);
                    }
                }

                // Actualizar el costo en el detalle de la compra
                $detalleCompra = Detalle::find($item['id_detalle_compra']);
                if ($detalleCompra) {
                    $detalleCompra->costo = $item['costo_retaceado'];
                    $detalleCompra->save();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Retaceo creado correctamente',
                'retaceo' => $retaceo
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $retaceo = Retaceo::findOrFail($id);

        // Cargar relaciones
        $retaceo->load('gastos', 'distribucion');

        return $retaceo;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // No permitimos actualizar un retaceo ya aplicado
        return response()->json(['error' => 'No se permite actualizar un retaceo ya aplicado'], 403);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $retaceo = Retaceo::findOrFail($id);

            // Obtener la distribución para restaurar los costos originales
            $distribucion = RetaceoDistribucion::where('id_retaceo', $id)->get();

            foreach ($distribucion as $item) {
                // Restaurar el costo original del producto
                $producto = Producto::find($item->id_producto);
                if ($producto) {
                    $producto->costo = $item->costo_original;
                    $producto->save();
                }

                // Restaurar el costo original en el detalle de la compra
                $detalleCompra = Detalle::find($item->id_detalle_compra);
                if ($detalleCompra) {
                    $detalleCompra->costo = $item->costo_original;
                    $detalleCompra->save();
                }
            }

            // Eliminar registros relacionados
            RetaceoGasto::where('id_retaceo', $id)->delete();
            RetaceoDistribucion::where('id_retaceo', $id)->delete();

            // Eliminar el retaceo
            $retaceo->delete();

            DB::commit();

            return response()->json(['message' => 'Retaceo eliminado correctamente'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function historial(Request $request) {}

    /**
     * Actualizar el estado del retaceo
     */
    public function actualizarEstado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:retaceos,id',
            'estado' => 'required|in:Borrador,Aplicado,Anulado',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $retaceo = Retaceo::findOrFail($request->id);

            // Validar transiciones de estado
            if ($retaceo->estado === 'Aplicado' && $request->estado === 'Borrador') {
                return response()->json(['error' => 'No se puede cambiar de Aplicado a Borrador'], 422);
            }

            $estadoAnterior = $retaceo->estado;
            $retaceo->estado = $request->estado;
            $retaceo->save();

            // Si se está aplicando el retaceo, marcar como estado = 'Aplicado'
            if ($request->estado === 'Aplicado' && $estadoAnterior !== 'Aplicado') {
                // El retaceo ya está marcado como aplicado,
                // los costos ya fueron actualizados en el método store()
            }

            // Si se está anulando el retaceo, restaurar costos originales
            if ($request->estado === 'Anulado' && $estadoAnterior === 'Aplicado') {
                $distribucion = RetaceoDistribucion::where('id_retaceo', $retaceo->id)->get();

                foreach ($distribucion as $item) {
                    // Restaurar el costo original del producto
                    $producto = Producto::find($item->id_producto);
                    if ($producto) {
                        $producto->costo = $item->costo_original;
                        $producto->save();
                    }

                    // Restaurar el costo original en el detalle de la compra
                    $detalleCompra = Detalle::find($item->id_detalle_compra);
                    if ($detalleCompra) {
                        $detalleCompra->costo = $item->costo_original;
                        $detalleCompra->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'retaceo' => $retaceo
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function retaceoGastos(Request $request)
    {


        $retaceoGastos = RetaceoGasto::where('id_retaceo', $request->id_retaceo)->get();

        return $retaceoGastos;
    }

    public function retaceoDistribucion(Request $request)
    {
        $retaceoDistribucion = RetaceoDistribucion::where('id_retaceo', $request->id_retaceo)->get();

        return $retaceoDistribucion;
    }

    public function calcularDistribucion(Request $request)
    {
        // Validar datos
        $validator = Validator::make($request->all(), [
            'gastos' => 'required|array',
            'detalles' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Obtener los gastos
            $gastoTransporte = $request->gastos['transporte'] ?? 0;
            $gastoSeguro = $request->gastos['seguro'] ?? 0;
            $gastoDAI = $request->gastos['dai'] ?? 0;
            $gastoOtros = $request->gastos['otros'] ?? 0;

            $totalGastos = $gastoTransporte + $gastoSeguro + $gastoDAI + $gastoOtros;

            // Calcular el valor FOB total
            $valorFobTotal = 0;
            foreach ($request->detalles as $detalle) {
                $valorFobTotal += ($detalle['costo_original'] * $detalle['cantidad']);
            }

            if ($valorFobTotal <= 0) {
                return response()->json(['error' => 'El valor FOB total debe ser mayor que cero'], 422);
            }

            // Calcular la distribución
            $distribucion = [];
            foreach ($request->detalles as $detalle) {
                $valorFob = $detalle['costo_original'] * $detalle['cantidad'];
                $porcentajeDistribucion = ($valorFob / $valorFobTotal) * 100;

                $montoTransporte = ($porcentajeDistribucion / 100) * $gastoTransporte;
                $montoSeguro = ($porcentajeDistribucion / 100) * $gastoSeguro;
                $montoDAI = ($porcentajeDistribucion / 100) * $gastoDAI;
                $montoOtros = ($porcentajeDistribucion / 100) * $gastoOtros;

                $costoLanded = $valorFob + $montoTransporte + $montoSeguro + $montoDAI + $montoOtros;
                $costoRetaceado = $detalle['cantidad'] > 0 ? $costoLanded / $detalle['cantidad'] : 0;

                $distribucion[] = [
                    'id_producto' => $detalle['id_producto'],
                    'id_detalle_compra' => $detalle['id'],
                    'cantidad' => $detalle['cantidad'],
                    'costo_original' => $detalle['costo_original'],
                    'valor_fob' => $valorFob,
                    'porcentaje_distribucion' => $porcentajeDistribucion,
                    'monto_transporte' => $montoTransporte,
                    'monto_seguro' => $montoSeguro,
                    'monto_dai' => $montoDAI,
                    'monto_otros' => $montoOtros,
                    'costo_landed' => $costoLanded,
                    'costo_retaceado' => $costoRetaceado,
                ];
            }

            return response()->json([
                'distribucion' => $distribucion,
                'total_gastos' => $totalGastos,
                'total_retaceado' => array_sum(array_column($distribucion, 'costo_landed'))
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

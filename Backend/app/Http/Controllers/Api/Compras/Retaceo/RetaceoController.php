<?php

namespace App\Http\Controllers\Api\Compras\Retaceo;


use App\Models\Inventario\Producto;
use App\Models\Compras\Detalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Compras\Retaceo\Retaceo;
use App\Models\Compras\Retaceo\RetaceoDistribucion;
use App\Models\Compras\Retaceo\RetaceoGasto;

class RetaceoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Retaceo::query();

        // Filtros
        if ($request->has('fecha_desde') && !empty($request->fecha_desde)) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta') && !empty($request->fecha_hasta)) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->has('busqueda') && !empty($request->busqueda)) {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('numero_duca', 'like', '%' . $busqueda . '%')
                    ->orWhere('numero_factura', 'like', '%' . $busqueda . '%')
                    ->orWhere('id', 'like', '%' . $busqueda . '%');
            });
        }

        // Paginación
        $limite = $request->limite ?? 10;

        return $query->orderBy('id', 'desc')->paginate($limite);
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

        try {
            DB::beginTransaction();

            // Crear el retaceo
            $retaceo = new Retaceo();
            $retaceo->codigo = 'RET-' . date('Ymd') . '-' . rand(1000, 9999);
            $retaceo->id_compra = $request->id_compra;
            $retaceo->numero_duca = $request->numero_duca;
            $retaceo->tasa_dai = $request->tasa_dai;
            $retaceo->numero_factura = $request->numero_factura;
            $retaceo->incoterm = $request->incoterm;
            $retaceo->fecha = $request->fecha;
            $retaceo->observaciones = $request->observaciones;
            $retaceo->total_gastos = $request->total_gastos;
            $retaceo->total_retaceado = $request->total_retaceado;
            $retaceo->id_empresa = $request->id_empresa;
            $retaceo->id_sucursal = $request->id_sucursal;
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
                $distribucion->save();

                // Actualizar el costo del producto
                $producto = Producto::find($item['id_producto']);
                if ($producto) {
                    $producto->costo = $item['costo_retaceado'];
                    $producto->save();
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

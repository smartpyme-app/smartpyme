<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Devoluciones\DetalleCompuesto;
use App\Exports\DevolucionesVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Creditos\Credito;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use JWTAuth;
use Auth;
use Illuminate\Support\Str;

class DevolucionVentasController extends Controller
{
    

    public function index(Request $request) {
       
        $ventas = Devolucion::when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->estado !== null, function($q) use ($request){
                            $q->where('enable', !!$request->estado);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->whereHas('documento', function ($q) use ($request) {
                                $q->where('nombre', $request->tipo_documento);
                            });
                        })
                        ->when($request->id_documento, function ($query) use ($request) {
                            // Buscar el documento por ID (respetando el scope de empresa)
                            $documento = Documento::find($request->id_documento);
                            
                            if ($documento) {
                                // Filtrar por todos los documentos que tengan el mismo nombre (case insensitive)
                                return $query->whereHas('documento', function ($q) use ($documento) {
                                    $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                                });
                            } else {
                                // Si no se encuentra el documento, filtrar por ID directo
                                return $query->where('id_documento', $request->id_documento);
                            }
                        })
                        ->when($request->buscador, function($query) use ($request){
                        return $query->whereHas('cliente', function($q) use ($request){
                                    $q->where('nombre', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nombre_empresa', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('ncr', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nit', 'like' ,"%" . $request->buscador . "%");
                                 })->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Devolucion::where('id', $id)->with('detalles.composiciones', 'detalles.producto', 'venta', 'cliente')->first();
        return Response()->json($venta, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'enable'            => 'required',
            'observaciones'     => 'required',
            'tipo'              => 'required|in:devolucion,descuento_ajuste,anulacion_factura',
            // 'id_cliente'        => 'required',
            'id_usuario'        => 'required',
        ]);

        if($request->id)
            $venta = Devolucion::findOrFail($request->id);
        else
            $venta = new Devolucion;

        // Solo ajustar stocks si el tipo de nota de crédito afecta inventario
        if ($request->tipo !== 'descuento_ajuste') {
            // Ajustar stocks
            foreach ($venta->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();
                
                $empresa = Empresa::find($venta->id_empresa);
                $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
                
                // Anular y regresar stock
                if(($venta->enable != '0') && ($request['enable'] == '0')){
                    // Si el producto tiene lotes y el detalle tiene lote_id, regresar stock al lote
                    if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
                        $lote = Lote::find($detalle->lote_id);
                        if ($lote) {
                            $lote->stock += $detalle->cantidad;
                            $lote->save();
                        }
                    }

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad * -1);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {
                        $productoCompuesto = Producto::find($comp->id_producto);
                        $cantidadComp = $detalle->cantidad * $comp->cantidad;
                        
                        // Si el producto compuesto tiene lotes y lotes están activos, actualizar stock del lote
                        if ($productoCompuesto && $productoCompuesto->inventario_por_lotes && $lotesActivo) {
                            // Buscar lote del producto compuesto (si existe en el detalle compuesto)
                            // Por ahora, buscamos el lote más antiguo con stock disponible
                            $loteCompuesto = Lote::where('id_producto', $comp->id_producto)
                                ->where('id_bodega', $venta->id_bodega)
                                ->where('stock', '>', 0)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if ($loteCompuesto) {
                                $loteCompuesto->stock -= $cantidadComp;
                                $loteCompuesto->save();
                            }
                        }
                        
                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_bodega', $venta->id_bodega)->first();

                        if ($inventario) {
                            $inventario->stock -= $cantidadComp;
                            $inventario->save();
                            $inventario->kardex($venta, $cantidadComp * -1);
                        }
                    }

                }
                // Cancelar anulación y descargar stock
                if(($venta->enable == '0') && ($request['enable'] != '0')){
                    // Si el producto tiene lotes y el detalle tiene lote_id, descontar del lote
                    if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
                        $lote = Lote::find($detalle->lote_id);
                        if ($lote && $lote->stock >= $detalle->cantidad) {
                            $lote->stock -= $detalle->cantidad;
                            $lote->save();
                        }
                    }
                    
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {
                        $productoCompuesto = Producto::find($comp->id_producto);
                        $cantidadComp = $detalle->cantidad * $comp->cantidad;
                        
                        // Si el producto compuesto tiene lotes y lotes están activos, actualizar stock del lote
                        if ($productoCompuesto && $productoCompuesto->inventario_por_lotes && $lotesActivo) {
                            // Buscar lote del producto compuesto (si existe en el detalle compuesto)
                            // Por ahora, buscamos el lote más antiguo con stock disponible
                            $loteCompuesto = Lote::where('id_producto', $comp->id_producto)
                                ->where('id_bodega', $venta->id_bodega)
                                ->where('stock', '>', 0)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if ($loteCompuesto) {
                                $loteCompuesto->stock += $cantidadComp;
                                $loteCompuesto->save();
                            }
                        }
                        
                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_bodega', $venta->id_bodega)->first();

                        if ($inventario) {
                            $inventario->stock += $cantidadComp;
                            $inventario->save();
                            $inventario->kardex($venta, $cantidadComp);
                        }
                    }

                }
            }
        }
        
        $venta->fill($request->all());
        $venta->save();        

        return Response()->json($venta, 200);

    }

    public function update(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
            'id_documento' => 'nullable|exists:documentos,id',
            'correlativo' => 'nullable|string|max:255',
            'id_usuario' => 'required|exists:users,id',
            'observaciones' => 'required|string|max:255',
        ], [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'observaciones.required' => 'Las observaciones son requeridas.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 255 caracteres.',
        ]);

        DB::beginTransaction();

        try {
            $devolucion = Devolucion::findOrFail($request->id);
            
            // Solo actualizar los campos permitidos
            $devolucion->fecha = $request->fecha;
            $devolucion->id_documento = $request->id_documento;
            $devolucion->correlativo = $request->correlativo;
            $devolucion->id_usuario = $request->id_usuario;
            $devolucion->observaciones = $request->observaciones;
            
            $devolucion->save();

            DB::commit();
            
            return response()->json([
                'message' => 'Devolución actualizada correctamente',
                'data' => $devolucion
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Error al actualizar la devolución: ' . $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $venta = Devolucion::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);

    }


    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'tipo'              => 'required|in:devolucion,descuento_ajuste,anulacion_factura',
            // 'id_documento'      => 'required|max:255',
            // 'id_cliente'        => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'observaciones'     => 'required|max:255',
            'id_venta'          => 'required|numeric',
            // 'id_caja'           => 'required|numeric',
            // 'id_corte'          => 'required|numeric',
            'id_usuario'        => 'required|numeric',
            'id_bodega'       => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
            'id_empresa'       => 'required|numeric',
        ],[
            'detalles.required' => 'Tienes que ingresar los detalles a devolver.'
        ]);

        // Validar que la diferencia entre notas de crédito y notas de débito no supere el total de la venta
            $venta = Venta::findOrFail($request->id_venta);

            $devolucionesActivas = Devolucion::where('id_venta', $request->id_venta)
                ->where('enable', true)
                ->when($request->id, function ($query) use ($request) {
                    $query->where('id', '!=', $request->id);
                })
                ->with('documento')
                ->get();

            $totalCreditos = 0;
            $totalDebitos = 0;

            foreach ($devolucionesActivas as $devolucionExistente) {
                $nombreDocumentoExistente = optional($devolucionExistente->documento)->nombre;

                if ($nombreDocumentoExistente == 'Nota de crédito') {
                    $totalCreditos += $devolucionExistente->total;
                } elseif ($nombreDocumentoExistente == 'Nota de débito') {
                    $totalDebitos += $devolucionExistente->total;
                }
            }

            $documentoNuevo = $request->id_documento ? Documento::find($request->id_documento) : null;
            $nombreDocumentoNuevo = optional($documentoNuevo)->nombre;

            if ($nombreDocumentoNuevo == 'Nota de crédito') {
                $totalCreditos += $request->total;
            } elseif ($nombreDocumentoNuevo == 'Nota de débito') {
                $totalDebitos += $request->total;
            }

            $diferencia = abs($totalCreditos - $totalDebitos);
            $totalVenta = $venta->total;

            if ($diferencia > $totalVenta) {
                return Response()->json([
                    'error' => 'No se puede registrar la devolución. La diferencia entre notas de crédito y notas de débito (' .
                              number_format($diferencia, 2) .
                              ') supera el total de la venta (' . number_format($totalVenta, 2) . ').'
                ], 400);
            }

        DB::beginTransaction();
         
        try {
        
        // Guardamos la devolucion
            if($request->id)
                $devolucion = Devolucion::findOrFail($request->id);
            else
                $devolucion = new Devolucion;
            
            $devolucion->fill($request->all());
            $devolucion->save();

            // $venta = Venta::findOrFail($request['id_venta']);
            // $venta->estado = 'Anulada';
            // $venta->save();
            
        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['id_devolucion_venta'] = $devolucion->id;
                $detalle->fill($det);
                $detalle->save();

                // Si es compuesto
                if (isset($det['composiciones'])) {
                    foreach ($det['composiciones'] as $item) {
                        $cd = new DetalleCompuesto;
                        $cd->id_producto = $item['id_producto'];
                        $cd->cantidad   = $item['cantidad'];
                        $cd->id_detalle = $detalle->id;
                        $cd->save();

                    }
                }

                // Solo afectar inventario si el tipo de nota de crédito lo requiere
                if ($request->tipo !== 'descuento_ajuste') {
                    $producto = Producto::find($det['id_producto']);
                    
                    $empresa = Empresa::find($devolucion->id_empresa);
                    $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
                    
                    // Si el producto tiene lotes y se especificó un lote, actualizar el stock del lote
                    if ($producto && $producto->inventario_por_lotes && $lotesActivo && isset($det['lote_id']) && $det['lote_id']) {
                        $lote = Lote::find($det['lote_id']);
                        if ($lote) {
                            // Verificar que el lote pertenezca al producto y bodega correctos
                            if ($lote->id_producto == $det['id_producto'] && $lote->id_bodega == $request->id_bodega) {
                                $lote->stock += $det['cantidad'];
                                $lote->save();
                            }
                        }
                    }
                    
                    // Actualizar inventario tradicional
                    $inventario = Inventario::where('id_producto', $det['id_producto'])
                                        ->where('id_bodega', $request->id_bodega)->first();

                    if ($inventario) {
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($devolucion, $det['cantidad']);
                    }

                    // Inventario compuestos
                    if (isset($det['composiciones'])) {
                        foreach ($det['composiciones'] as $comp) {
                            $productoCompuesto = Producto::find($comp['id_producto']);
                            $cantidadComp = $det['cantidad'] * $comp['cantidad'];
                            
                            // Si el producto compuesto tiene lotes y lotes están activos, actualizar stock del lote
                            if ($productoCompuesto && $productoCompuesto->inventario_por_lotes && $lotesActivo) {
                                // Buscar lote del producto compuesto
                                // Si viene especificado en la composición, usarlo; si no, buscar el más antiguo
                                $loteCompuesto = null;
                                if (isset($comp['lote_id']) && $comp['lote_id']) {
                                    $loteCompuesto = Lote::find($comp['lote_id']);
                                } else {
                                    // Buscar el lote más antiguo con stock disponible
                                    $loteCompuesto = Lote::where('id_producto', $comp['id_producto'])
                                        ->where('id_bodega', $devolucion->id_bodega)
                                        ->where('stock', '>', 0)
                                        ->orderBy('created_at', 'asc')
                                        ->first();
                                }
                                
                                if ($loteCompuesto) {
                                    $loteCompuesto->stock += $cantidadComp;
                                    $loteCompuesto->save();
                                }
                            }
                            
                            $inventario = Inventario::where('id_producto', $comp['id_producto'])
                                        ->where('id_bodega', $devolucion->id_bodega)->first();

                            if ($inventario) {
                                $inventario->stock += $cantidadComp;
                                $inventario->save();
                                $inventario->kardex($devolucion, $cantidadComp);
                            }
                        }
                    }
                }

                // Si es paquete cambiar estado
                $paquetes = Paquete::where('id_venta', $devolucion->id_venta)->get();
                foreach ($paquetes as $paquete) {
                    $paquete->estado = 'En bodega';
                    $paquete->id_venta = NULL;
                    $paquete->id_venta_detalle = NULL;
                    $paquete->save();
                }
                
            }
            
        // Incrementar el correlarivo
        if ($devolucion->id_documento) {
            Documento::where('id', $devolucion->id_documento)->increment('correlativo');
        }
        
        DB::commit();
        return Response()->json($devolucion, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        

    }

    public function generarDoc($id){
        $venta = Devolucion::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        if(Auth::user()->id_empresa == 187 && $venta->nombre_documento == "Nota de crédito"){//187  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.NC-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('US Letter', 'portrait'); 
        }
        else if(Auth::user()->id_empresa == 250 && $venta->nombre_documento == "Nota de crédito"){//250  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.NC-Full-Solutions', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('Legal', 'portrait'); 
        }
        else if(Auth::user()->id_empresa == 128 && $venta->nombre_documento == "Nota de crédito"){//250  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.NC-Kiero', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('Legal', 'portrait'); 
        }else{
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.nota-credito', compact('venta'));
            $pdf->setPaper('US Letter', 'portrait');
        }

        return $pdf->stream('nota-credito-' . $venta->id . '.pdf');

    }

    public function export(Request $request){
        $ventas = new DevolucionesVentasExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas.xlsx');
    }


}

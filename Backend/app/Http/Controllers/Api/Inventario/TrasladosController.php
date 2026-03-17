<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\TrasladosExport;
use Maatwebsite\Excel\Facades\Excel;
class TrasladosController extends Controller
{   


    public function read($id) {
        $traslado = Traslado::where('id', $id)
            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario', 'lote', 'loteDestino'])
            ->firstOrFail();

        return Response()->json($traslado, 200);
    }

    public function index(Request $request) {
       
        $traslados = Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->whereHas('origen', function($q) use ($request){
                                    $q->where('id_bodega_de', $request->id_bodega_de);
                                });
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->whereHas('destino', function($q) use ($request){
                                    $q->where('id_bodega', $request->id_bodega_para);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                            })
                            ->when($request->concepto, function($query) use ($request){
                                return $query->where('concepto', 'like', '%' . $request->concepto . '%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden, $request->direccion)
                            ->paginate($request->paginate);


        return Response()->json($traslados, 200);
    }

    public function store(Request $request){

        // Si viene un array de detalles, procesar múltiples productos
        if ($request->has('detalles') && is_array($request->detalles) && count($request->detalles) > 0) {
            return $this->storeConDetalles($request);
        }

        // Procesamiento tradicional (un solo producto)
        $request->validate([
          // 'fecha'         => 'required',
          'estado'          => 'required',
          'id_producto'     => 'required',
          'id_bodega_de' => 'required|numeric',
          'id_bodega'     => 'required|numeric',
          'concepto'        => 'required',
          'cantidad'      => 'required|numeric',
          'id_usuario'      => 'required|numeric',
          'lote_id'       => 'nullable|numeric|exists:lotes,id',
          'lote_id_destino' => 'nullable|numeric|exists:lotes,id',
        ]);

        $traslado = new Traslado();
        $traslado->fill($request->all());

        DB::beginTransaction();
         
        try {

            if ($request->id_bodega == $request->id_bodega_de) {
            throw new \Exception('Has seleccionado la misma sucursal.');
        }

        $producto = Producto::where('id', $request->id_producto)->with('composiciones')->firstOrFail();
        
        if ($producto->inventario_por_lotes && !$request->lote_id) {
            throw new \Exception('Debe seleccionar un lote para este producto.');
        }
        
        // Si tiene lote_id, verificar y procesar el lote
        if ($request->lote_id && $producto->inventario_por_lotes) {
            // Refrescar el lote desde la base de datos para obtener el stock actualizado
            $loteOrigen = Lote::findOrFail($request->lote_id);
            $loteOrigen->refresh(); // Asegurar que tenemos los datos más recientes
            
            if ($loteOrigen->id_bodega != $request->id_bodega_de) {
                throw new \Exception('El lote seleccionado no pertenece a la bodega de origen.');
            }
            
            $stockDisponible = (float) $loteOrigen->stock;
            $cantidadRequerida = (float) $request->cantidad;
            
            if ($stockDisponible < $cantidadRequerida) {
                throw new \Exception('El lote no tiene stock suficiente. Stock disponible: ' . number_format($stockDisponible, 2) . ', Cantidad requerida: ' . number_format($cantidadRequerida, 2));
            }
            
            // Descontar del lote de origen (usar las variables ya convertidas)
            $loteOrigen->stock = max(0, $stockDisponible - $cantidadRequerida);
            $loteOrigen->save();
            
            // Procesar lote en destino
            if ($request->lote_id_destino) {
                // Si se especificó un lote destino, validar y sumar a ese lote
                $loteDestino = Lote::findOrFail($request->lote_id_destino);
                
                if ($loteDestino->id_bodega != $request->id_bodega) {
                    throw new \Exception('El lote de destino no pertenece a la bodega de destino.');
                }
                
                if ($loteDestino->id_producto != $producto->id) {
                    throw new \Exception('El lote de destino no corresponde al producto.');
                }
                
                $loteDestino->stock += $request->cantidad;
                $loteDestino->save();
            } else {
                // Si no se especificó lote destino, buscar o crear uno con el mismo número
                $loteDestino = Lote::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->id_bodega)
                    ->where('numero_lote', $loteOrigen->numero_lote)
                    ->first();
                
                if ($loteDestino) {
                    $loteDestino->stock += $request->cantidad;
                    $loteDestino->save();
                } else {
                    // Crear nuevo lote en destino con el mismo número
                    $loteDestino = Lote::create([
                        'id_producto' => $producto->id,
                        'id_bodega' => $request->id_bodega,
                        'numero_lote' => $loteOrigen->numero_lote,
                        'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
                        'fecha_fabricacion' => $loteOrigen->fecha_fabricacion,
                        'stock' => $request->cantidad,
                        'stock_inicial' => $request->cantidad,
                        'id_empresa' => Auth::user()->id_empresa,
                    ]);
                }
            }
        }
        
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega)->first();

        if ($origen->stock < $request->cantidad) {
            throw new \Exception('La sucursal no tiene el stock suficiente.');
        }

        
        if ($origen && $destino) {
            $traslado->save();
            
            $origen->stock -= $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock += $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

        }else{
            throw new \Exception('Una de las sucursales no tiene inventario.');
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega)->first();

            if ($origen->stock < $request->cantidad) {
                return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
            }

            
            if ($origen && $destino) {
                $cantidad = $traslado->cantidad * $comp->cantidad;

                $origen->stock -= $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);

                $destino->stock += $cantidad;
                $destino->save();
                $destino->kardex($traslado, $cantidad);

            }else{
                throw new \Exception('Una de las sucursales no tiene inventario.');
            }
        }
      
        DB::commit();
        return Response()->json($traslado, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    /**
     * Procesar traslado con múltiples detalles (array de productos)
     */
    private function storeConDetalles(Request $request)
    {
        $request->validate([
            'estado' => 'required',
            'origen_id' => 'required|numeric',
            'destino_id' => 'required|numeric',
            'detalles' => 'required|array',
            'detalles.*.producto_id' => 'required|numeric',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.lote_id' => 'nullable|numeric|exists:lotes,id',
            'detalles.*.lote_id_destino' => 'nullable|numeric|exists:lotes,id',
        ]);

        if ($request->origen_id == $request->destino_id) {
            return Response()->json(['error' => 'Has seleccionado la misma bodega.', 'code' => 400], 400);
        }
        
        // Mapear origen_id y destino_id a los nombres que espera el modelo
        $request->merge([
            'id_bodega_de' => $request->origen_id,
            'id_bodega' => $request->destino_id,
        ]);

        DB::beginTransaction();
        
        try {
            foreach ($request->detalles as $detalleData) {
                $producto = Producto::where('id', $detalleData['producto_id'])->with('composiciones')->firstOrFail();
                
                // Si el producto tiene inventario por lotes, el lote_id es requerido
                if ($producto->inventario_por_lotes && (!isset($detalleData['lote_id']) || !$detalleData['lote_id'])) {
                    throw new \Exception("Debe seleccionar un lote para el producto {$producto->nombre}.");
                }
                
                // Si tiene lote_id, verificar y procesar el lote
                if (isset($detalleData['lote_id']) && $detalleData['lote_id'] && $producto->inventario_por_lotes) {
                    // Refrescar el lote desde la base de datos para obtener el stock actualizado
                    $loteOrigen = Lote::findOrFail($detalleData['lote_id']);
                    $loteOrigen->refresh(); // Asegurar que tenemos los datos más recientes
                    
                    if ($loteOrigen->id_bodega != $request->origen_id) {
                        throw new \Exception("El lote seleccionado no pertenece a la bodega de origen.");
                    }
                    
                    // Convertir a float para comparación más precisa
                    $stockDisponible = (float) $loteOrigen->stock;
                    $cantidadRequerida = (float) $detalleData['cantidad'];
                    
                    if ($stockDisponible < $cantidadRequerida) {
                        throw new \Exception("El lote no tiene stock suficiente para el producto {$producto->nombre}. Stock disponible: " . number_format($stockDisponible, 2) . ", Cantidad requerida: " . number_format($cantidadRequerida, 2));
                    }
                    
            // Descontar del lote de origen (usar las variables ya convertidas)
            $loteOrigen->stock = max(0, $stockDisponible - $cantidadRequerida);
            $loteOrigen->save();
                    
                    // Procesar lote en destino
                    if (isset($detalleData['lote_id_destino']) && $detalleData['lote_id_destino']) {
                        // Si se especificó un lote destino, validar y sumar a ese lote
                        $loteDestino = Lote::findOrFail($detalleData['lote_id_destino']);
                        
                        if ($loteDestino->id_bodega != $request->destino_id) {
                            throw new \Exception("El lote de destino no pertenece a la bodega de destino.");
                        }
                        
                        if ($loteDestino->id_producto != $producto->id) {
                            throw new \Exception("El lote de destino no corresponde al producto.");
                        }
                        
                        $loteDestino->stock += $detalleData['cantidad'];
                        $loteDestino->save();
                    } else {
                        // Si no se especificó lote destino, buscar o crear uno con el mismo número
                        $loteDestino = Lote::where('id_producto', $producto->id)
                            ->where('id_bodega', $request->destino_id)
                            ->where('numero_lote', $loteOrigen->numero_lote)
                            ->first();
                        
                        if ($loteDestino) {
                            $loteDestino->stock += $detalleData['cantidad'];
                            $loteDestino->save();
                        } else {
                            // Crear nuevo lote en destino con el mismo número
                            $loteDestino = Lote::create([
                                'id_producto' => $producto->id,
                                'id_bodega' => $request->destino_id,
                                'numero_lote' => $loteOrigen->numero_lote,
                                'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
                                'fecha_fabricacion' => $loteOrigen->fecha_fabricacion,
                                'stock' => $detalleData['cantidad'],
                                'stock_inicial' => $detalleData['cantidad'],
                                'id_empresa' => Auth::user()->id_empresa,
                            ]);
                        }
                    }
                }
                
                // Procesar inventario tradicional
                $origen = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->origen_id)
                    ->first();
                $destino = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->destino_id)
                    ->first();
                
                if (!$origen || $origen->stock < $detalleData['cantidad']) {
                    throw new \Exception("La bodega origen no tiene stock suficiente para el producto {$producto->nombre}.");
                }
                
                // Crear registro de traslado
                $traslado = new Traslado();
                $traslado->id_producto = $producto->id;
                $traslado->id_bodega_de = $request->origen_id;
                $traslado->id_bodega = $request->destino_id;
                $traslado->cantidad = $detalleData['cantidad'];
                $traslado->concepto = $request->nota ?? ($request->concepto ?? 'Traslado');
                $traslado->estado = $request->estado;
                $traslado->id_usuario = Auth::id();
                $traslado->id_empresa = Auth::user()->id_empresa;
                if (isset($detalleData['lote_id']) && $detalleData['lote_id']) {
                    $traslado->lote_id = $detalleData['lote_id'];
                }
                if (isset($detalleData['lote_id_destino']) && $detalleData['lote_id_destino']) {
                    $traslado->lote_id_destino = $detalleData['lote_id_destino'];
                }
                $traslado->save();
                
                // Actualizar inventarios
                $origen->stock -= $detalleData['cantidad'];
                $origen->save();
                $origen->kardex($traslado, $detalleData['cantidad'] * -1);
                
                if ($destino) {
                    $destino->stock += $detalleData['cantidad'];
                    $destino->save();
                    $destino->kardex($traslado, $detalleData['cantidad']);
                } else {
                    $destino = new Inventario();
                    $destino->id_producto = $producto->id;
                    $destino->id_bodega = $request->destino_id;
                    $destino->stock = $detalleData['cantidad'];
                    $destino->save();
                    $destino->kardex($traslado, $detalleData['cantidad']);
                }
            }
            
            DB::commit();
            return Response()->json(['message' => 'Traslado procesado exitosamente'], 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function delete($id){

        DB::beginTransaction();
         
        try {

        $traslado = Traslado::findOrfail($id);
        $traslado->estado = 'Cancelado';
        $traslado->save();

        $producto = Producto::where('id', $traslado->id_producto)->with('composiciones')->firstOrFail();
        
        // Si el traslado tiene lote_id, revertir el movimiento en los lotes
        if ($traslado->lote_id) {
            $loteOrigen = Lote::find($traslado->lote_id);
            if ($loteOrigen) {
                $loteOrigen->stock += $traslado->cantidad;
                $loteOrigen->save();
                
                // Usar lote_id_destino si se guardó; si no, buscar por numero_lote
                if ($traslado->lote_id_destino) {
                    $loteDestino = Lote::find($traslado->lote_id_destino);
                } else {
                    $loteDestino = Lote::where('id_producto', $producto->id)
                        ->where('id_bodega', $traslado->id_bodega)
                        ->where('numero_lote', $loteOrigen->numero_lote)
                        ->first();
                }
                
                if ($loteDestino) {
                    $loteDestino->stock -= $traslado->cantidad;
                    if ($loteDestino->stock < 0) {
                        $loteDestino->stock = 0;
                    }
                    $loteDestino->save();
                }
            }
        }
        
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();

        // if ($origen->stock < $traslado->cantidad) {
        //     return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
        // }

        
        if ($origen && $destino) {
            $traslado->save();
            
            $origen->stock += $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock -= $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

        }else{
            throw new \Exception('Una de las sucursales no tiene inventario.');
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega)->first();

            // if ($origen->stock < $traslado->cantidad) {
            //     return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
            // }

            
            if ($origen && $destino) {
                $cantidad = $traslado->cantidad * $comp->cantidad;

                $origen->stock += $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);

                $destino->stock -= $cantidad;
                $destino->save();
                $destino->kardex($traslado, $cantidad);

            }else{
                throw new \Exception('Una de las sucursales no tiene inventario.');
            }
        }

        DB::commit();
        return Response()->json($traslado, 201);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    /**
     * Actualizar estado del traslado (solo cambiar de Cancelado a Confirmado).
     * Cuando se confirma un traslado cancelado, se re-aplica el movimiento de inventario.
     */
    public function update(Request $request, $id)
    {
        $request->validate(['estado' => 'required|in:Confirmado,Cancelado']);

        $traslado = Traslado::findOrFail($id);

        if ($request->estado === 'Cancelado') {
            return $this->delete($id);
        }

        if ($traslado->estado !== 'Cancelado') {
            return Response()->json(['error' => 'Solo se puede confirmar un traslado cancelado.'], 400);
        }

        $traslado->estado = 'Confirmado';

        DB::beginTransaction();
        try {
            $producto = Producto::where('id', $traslado->id_producto)->with('composiciones')->firstOrFail();

            if ($traslado->lote_id && $producto->inventario_por_lotes) {
                $loteOrigen = Lote::findOrFail($traslado->lote_id);
                $loteOrigen->refresh();

                if ($loteOrigen->id_bodega != $traslado->id_bodega_de) {
                    throw new \Exception('El lote no pertenece a la bodega de origen.');
                }

                $stockDisponible = (float) $loteOrigen->stock;
                $cantidadRequerida = (float) $traslado->cantidad;
                if ($stockDisponible < $cantidadRequerida) {
                    throw new \Exception('El lote no tiene stock suficiente. Stock disponible: ' . number_format($stockDisponible, 2) . ', Cantidad requerida: ' . number_format($cantidadRequerida, 2));
                }

                $loteOrigen->stock = max(0, $stockDisponible - $cantidadRequerida);
                $loteOrigen->save();

                if ($traslado->lote_id_destino) {
                    $loteDestino = Lote::findOrFail($traslado->lote_id_destino);
                    $loteDestino->stock += $traslado->cantidad;
                    $loteDestino->save();
                } else {
                    $loteDestino = Lote::where('id_producto', $producto->id)
                        ->where('id_bodega', $traslado->id_bodega)
                        ->where('numero_lote', $loteOrigen->numero_lote)
                        ->first();
                    if ($loteDestino) {
                        $loteDestino->stock += $traslado->cantidad;
                        $loteDestino->save();
                    } else {
                        Lote::create([
                            'id_producto' => $producto->id,
                            'id_bodega' => $traslado->id_bodega,
                            'numero_lote' => $loteOrigen->numero_lote,
                            'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
                            'fecha_fabricacion' => $loteOrigen->fecha_fabricacion,
                            'stock' => $traslado->cantidad,
                            'stock_inicial' => $traslado->cantidad,
                            'id_empresa' => Auth::user()->id_empresa,
                        ]);
                    }
                }
            }

            $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();

            if (!$origen || $origen->stock < $traslado->cantidad) {
                throw new \Exception('La sucursal origen no tiene el stock suficiente.');
            }

            $origen->stock -= $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock += $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

            foreach ($producto->composiciones as $comp) {
                $prodComp = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
                $origenComp = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega_de)->first();
                $destinoComp = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega)->first();
                if ($origenComp && $destinoComp && $origenComp->stock >= $traslado->cantidad * $comp->cantidad) {
                    $cantidad = $traslado->cantidad * $comp->cantidad;
                    $origenComp->stock -= $cantidad;
                    $origenComp->save();
                    $origenComp->kardex($traslado, $cantidad * -1);
                    $destinoComp->stock += $cantidad;
                    $destinoComp->save();
                    $destinoComp->kardex($traslado, $cantidad);
                }
            }

            $traslado->save();

            DB::commit();
            return Response()->json($traslado, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request){
        $tralados = new TrasladosExport();
        $tralados->filter($request);

        return Excel::download($tralados, 'tralados.xlsx');
    }

    public function generarPdf($id) {
        $traslado = Traslado::where('id', $id)
            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario'])
            ->firstOrFail();
        
        $empresa = Empresa::findOrFail($traslado->id_empresa);

        $pdf = app('dompdf.wrapper')->loadView('reportes.inventario.traslado-pdf', compact('traslado', 'empresa'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('traslado-' . $traslado->id . '.pdf');
    }

    public function exportarPdf(Request $request) {
        $traslados = Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->where('id_bodega_de', $request->id_bodega_de);
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega_para);
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                            })
                            ->when($request->concepto, function($query) use ($request){
                                return $query->where('concepto', 'like', '%' . $request->concepto . '%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden ?? 'created_at', $request->direccion ?? 'desc')
                            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario'])
                            ->get();
        
        if ($traslados->isEmpty()) {
            return response()->json(['error' => 'No hay traslados para exportar'], 404);
        }

        $empresa = Empresa::findOrFail(Auth::user()->id_empresa);

        // Agrupar traslados por concepto
        $trasladosAgrupados = $traslados->groupBy(function($traslado) {
            return $traslado->concepto ?? 'Sin concepto';
        });

        $pdf = app('dompdf.wrapper')->loadView('reportes.inventario.traslados-pdf', compact('trasladosAgrupados', 'empresa'));
        $pdf->setPaper('letter', 'portrait');
        
        return $pdf->download('traslados-' . date('Y-m-d') . '.pdf');
    }

    public function conceptos() {
        $conceptos = Traslado::select('concepto')
            ->whereNotNull('concepto')
            ->where('concepto', '!=', '')
            ->distinct()
            ->orderBy('concepto', 'asc')
            ->pluck('concepto')
            ->map(function($concepto) {
                return $concepto;
            })
            ->values();

        return Response()->json($conceptos, 200);
    }

}

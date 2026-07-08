<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ProductoPresentacion;
use App\Services\Inventario\ConversionInventarioService;
use App\Services\Inventario\LoteAsignacionService;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\TrasladosExport;
use Maatwebsite\Excel\Facades\Excel;
class TrasladosController extends Controller
{   


    public function read($id) {
        $traslado = Traslado::where('id', $id)
            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario', 'lote', 'loteDestino', 'loteAsignaciones'])
            ->firstOrFail();

        return Response()->json($traslado, 200);
    }

    public function index(Request $request) {

        $traslados = Traslado::when($request->inicio, function($query) use ($request){
                                return $query->where('created_at', '>=', $request->inicio . ' 00:00:00');
                            })
                            ->when($request->fin, function($query) use ($request){
                                return $query->where('created_at', '<=', $request->fin . ' 23:59:59');
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->where('id_bodega_de', $request->id_bodega_de);
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega_para);
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->where(function($q) use ($request){
                                    $q->whereHas('producto', function($p) use ($request){
                                        $p->where('nombre', 'like',  '%'. $request->search . '%');
                                    })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                                });
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
          'lotes_asignados' => 'nullable|array',
        ]);

        $traslado = new Traslado();
        $traslado->fill($request->all());

        DB::beginTransaction();
         
        try {

            if ($request->id_bodega == $request->id_bodega_de) {
            throw new \Exception('Has seleccionado la misma sucursal.');
        }

        $traslado->id_presentacion = $request->id_presentacion ?: null;
        $factor = 1;
        if ($traslado->id_presentacion) {
            $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
            if ($presentacion) {
                $factor = (float) $presentacion->factor_conversion;
            }
        }
        $cantidadOriginal = (float) $request->cantidad;
        $cantidadBase = ConversionInventarioService::calcularCantidadBase($cantidadOriginal, $factor);

        $producto = Producto::where('id', $request->id_producto)->with('composiciones')->firstOrFail();
        $empresa = Empresa::find(Auth::user()->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
        $metodologia = $empresa ? $empresa->getLotesMetodologia() : 'FIFO';

        if ($producto->inventario_por_lotes && $lotesActivo && $metodologia === 'Manual') {
            $tieneLotes = !empty($request->lote_id) || !empty($request->lotes_asignados);
            if (!$tieneLotes) {
                throw new \Exception('Debe seleccionar un lote para este producto.');
            }
        }
        
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega)->first();

        if (!$origen || !$destino || $origen->stock < $cantidadBase) {
            throw new \Exception('La sucursal no tiene el stock suficiente.');
        }

        $traslado->save();

        if ($producto->inventario_por_lotes && $lotesActivo) {
            $asignaciones = LoteAsignacionService::resolverAsignacionesSalida(
                $producto,
                (int) $request->id_bodega_de,
                $cantidadBase,
                $metodologia,
                $lotesActivo,
                $request->lote_id ? (int) $request->lote_id : null,
                $request->lotes_asignados ?? null
            );

            if (empty($asignaciones)) {
                if ($metodologia === 'Manual') {
                    throw new \Exception('Debe seleccionar un lote para este producto.');
                }
            } else {
                $pivotRows = LoteAsignacionService::aplicarTrasladoLotes(
                    $asignaciones,
                    $producto,
                    (int) $request->id_bodega_de,
                    (int) $request->id_bodega,
                    $traslado,
                    $origen,
                    $destino,
                    $request->lote_id_destino ? (int) $request->lote_id_destino : null
                );
                LoteAsignacionService::sincronizarTrasladoLotes($traslado->id, $pivotRows);
                $traslado->lote_id = count($pivotRows) === 1 ? $pivotRows[0]['lote_id'] : null;
                $traslado->lote_id_destino = count($pivotRows) === 1 ? ($pivotRows[0]['lote_id_destino'] ?? null) : null;
                $traslado->save();
            }
        } else {
            $origen->stock -= $cantidadBase;
            $origen->save();
            $origen->kardex($traslado, $cantidadBase * -1);

            $destino->stock += $cantidadBase;
            $destino->save();
            $destino->kardex($traslado, $cantidadBase);
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega)->first();

            if ($origen->stock < ($cantidadBase * $comp->cantidad)) {
                return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente para componentes.', 'code' => 400], 400);
            }

            
            if ($origen && $destino) {
                $cantidad = $cantidadBase * $comp->cantidad;

                $origen->stock -= $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);

                $destino->stock += $cantidad;
                $destino->save();
                $destino->kardex($traslado, $cantidad);

            }else{
                throw new \Exception('Una de las sucursales no tiene inventario de los componentes.');
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
            'detalles.*.lotes_asignados' => 'nullable|array',
        ]);

        if ($request->origen_id == $request->destino_id) {
            return Response()->json(['error' => 'Has seleccionado la misma bodega.', 'code' => 400], 400);
        }
        
        $request->merge([
            'id_bodega_de' => $request->origen_id,
            'id_bodega' => $request->destino_id,
        ]);

        $empresa = Empresa::find(Auth::user()->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
        $metodologia = $empresa ? $empresa->getLotesMetodologia() : 'FIFO';

        DB::beginTransaction();
        
        try {
            foreach ($request->detalles as $detalleData) {
                $producto = Producto::where('id', $detalleData['producto_id'])->with('composiciones')->firstOrFail();
                
                $idPresentacion = $detalleData['id_presentacion'] ?? null;
                $factor = 1;
                if ($idPresentacion) {
                    $presentacion = ProductoPresentacion::find($idPresentacion);
                    if ($presentacion) {
                        $factor = (float) $presentacion->factor_conversion;
                    }
                }
                $cantidadOriginal = (float) $detalleData['cantidad'];
                $cantidadBase = ConversionInventarioService::calcularCantidadBase($cantidadOriginal, $factor);

                if ($producto->inventario_por_lotes && $lotesActivo && $metodologia === 'Manual') {
                    $tieneLotes = !empty($detalleData['lote_id']) || !empty($detalleData['lotes_asignados']);
                    if (!$tieneLotes) {
                        throw new \Exception("Debe seleccionar un lote para el producto {$producto->nombre}.");
                    }
                }

                $origen = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->origen_id)
                    ->first();
                $destino = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->destino_id)
                    ->first();
                
                if (!$origen || $origen->stock < $cantidadBase) {
                    throw new \Exception("La bodega origen no tiene stock suficiente para el producto {$producto->nombre}.");
                }

                if (!$destino) {
                    $destino = new Inventario();
                    $destino->id_producto = $producto->id;
                    $destino->id_bodega = $request->destino_id;
                    $destino->stock = 0;
                    $destino->save();
                }
                
                $traslado = new Traslado();
                $traslado->id_producto = $producto->id;
                $traslado->id_presentacion = $idPresentacion;
                $traslado->id_bodega_de = $request->origen_id;
                $traslado->id_bodega = $request->destino_id;
                $traslado->cantidad = $cantidadOriginal;
                $traslado->concepto = $request->nota ?? ($request->concepto ?? 'Traslado');
                $traslado->estado = $request->estado;
                $traslado->id_usuario = Auth::id();
                $traslado->id_empresa = Auth::user()->id_empresa;
                $traslado->save();

                if ($producto->inventario_por_lotes && $lotesActivo) {
                    $asignaciones = LoteAsignacionService::resolverAsignacionesSalida(
                        $producto,
                        (int) $request->origen_id,
                        $cantidadBase,
                        $metodologia,
                        $lotesActivo,
                        !empty($detalleData['lote_id']) ? (int) $detalleData['lote_id'] : null,
                        $detalleData['lotes_asignados'] ?? null
                    );

                    if (empty($asignaciones)) {
                        if ($metodologia === 'Manual') {
                            throw new \Exception("Debe seleccionar un lote para el producto {$producto->nombre}.");
                        }
                    } else {
                        $pivotRows = LoteAsignacionService::aplicarTrasladoLotes(
                            $asignaciones,
                            $producto,
                            (int) $request->origen_id,
                            (int) $request->destino_id,
                            $traslado,
                            $origen,
                            $destino,
                            !empty($detalleData['lote_id_destino']) ? (int) $detalleData['lote_id_destino'] : null
                        );
                        LoteAsignacionService::sincronizarTrasladoLotes($traslado->id, $pivotRows);
                        $traslado->lote_id = count($pivotRows) === 1 ? $pivotRows[0]['lote_id'] : null;
                        $traslado->lote_id_destino = count($pivotRows) === 1 ? ($pivotRows[0]['lote_id_destino'] ?? null) : null;
                        $traslado->save();
                    }
                } else {
                    $origen->stock -= $cantidadBase;
                    $origen->save();
                    $origen->kardex($traslado, $cantidadBase * -1);

                    $destino->stock += $cantidadBase;
                    $destino->save();
                    $destino->kardex($traslado, $cantidadBase);
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

        $traslado = Traslado::with('loteAsignaciones')->findOrfail($id);
        $traslado->estado = 'Cancelado';
        $traslado->save();

        $factor = 1;
        if ($traslado->id_presentacion) {
            $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
            if ($presentacion) {
                $factor = (float) $presentacion->factor_conversion;
            }
        }
        $cantidadBase = ConversionInventarioService::calcularCantidadBase((float) $traslado->cantidad, $factor);

        $producto = Producto::where('id', $traslado->id_producto)->with('composiciones')->firstOrFail();
        
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();

        if ($origen && $destino) {
            if ($traslado->loteAsignaciones->isNotEmpty() || $traslado->lote_id) {
                LoteAsignacionService::revertirTrasladoLotes(
                    $traslado->loteAsignaciones,
                    $producto,
                    $traslado,
                    $origen,
                    $destino,
                    $cantidadBase,
                    $traslado->lote_id,
                    $traslado->lote_id_destino
                );
            } else {
                $origen->stock += $cantidadBase;
                $origen->save();
                $origen->kardex($traslado, $cantidadBase * -1);

                $destino->stock -= $cantidadBase;
                $destino->save();
                $destino->kardex($traslado, $cantidadBase);
            }
        } else {
            throw new \Exception('Una de las sucursales no tiene inventario.');
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega)->first();

            
            if ($origen && $destino) {
                $cantidad = $cantidadBase * $comp->cantidad;

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
            $factor = 1;
            if ($traslado->id_presentacion) {
                $presentacion = ProductoPresentacion::find($traslado->id_presentacion);
                if ($presentacion) {
                    $factor = (float) $presentacion->factor_conversion;
                }
            }
            $cantidadBase = ConversionInventarioService::calcularCantidadBase((float) $traslado->cantidad, $factor);

            $producto = Producto::where('id', $traslado->id_producto)->with('composiciones')->firstOrFail();

            if ($traslado->lote_id && $producto->inventario_por_lotes) {
                $loteOrigen = Lote::findOrFail($traslado->lote_id);
                $loteOrigen->refresh();

                if ($loteOrigen->id_bodega != $traslado->id_bodega_de) {
                    throw new \Exception('El lote no pertenece a la bodega de origen.');
                }

                $stockDisponible = (float) $loteOrigen->stock;
                $cantidadRequerida = $cantidadBase;
                if ($stockDisponible < $cantidadRequerida) {
                    throw new \Exception('El lote no tiene stock suficiente. Stock disponible: ' . number_format($stockDisponible, 2) . ', Cantidad requerida: ' . number_format($cantidadRequerida, 2));
                }

                $loteOrigen->stock = max(0, $stockDisponible - $cantidadRequerida);
                $loteOrigen->save();

                if ($traslado->lote_id_destino) {
                    $loteDestino = Lote::findOrFail($traslado->lote_id_destino);
                    $loteDestino->stock += $cantidadBase;
                    $loteDestino->save();
                } else {
                    $loteDestino = Lote::where('id_producto', $producto->id)
                        ->where('id_bodega', $traslado->id_bodega)
                        ->where('numero_lote', $loteOrigen->numero_lote)
                        ->first();
                    if ($loteDestino) {
                        $loteDestino->stock += $cantidadBase;
                        $loteDestino->save();
                    } else {
                        Lote::create([
                            'id_producto' => $producto->id,
                            'id_bodega' => $traslado->id_bodega,
                            'numero_lote' => $loteOrigen->numero_lote,
                            'fecha_vencimiento' => $loteOrigen->fecha_vencimiento,
                            'fecha_fabricacion' => $loteOrigen->fecha_fabricacion,
                            'stock' => $cantidadBase,
                            'stock_inicial' => $cantidadBase,
                            'id_empresa' => Auth::user()->id_empresa,
                        ]);
                    }
                }
            }

            $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();

            if (!$origen || $origen->stock < $cantidadBase) {
                throw new \Exception('La sucursal origen no tiene el stock suficiente.');
            }

            $origen->stock -= $cantidadBase;
            $origen->save();
            $origen->kardex($traslado, $cantidadBase * -1);

            $destino->stock += $cantidadBase;
            $destino->save();
            $destino->kardex($traslado, $cantidadBase);

            foreach ($producto->composiciones as $comp) {
                $prodComp = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
                $origenComp = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega_de)->first();
                $destinoComp = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega)->first();
                if ($origenComp && $destinoComp && $origenComp->stock >= $cantidadBase * $comp->cantidad) {
                    $cantidad = $cantidadBase * $comp->cantidad;
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
        
        $empresa = Empresa::with('currency')->findOrFail($traslado->id_empresa);

        $pdf = app('dompdf.wrapper')->loadView('reportes.inventario.traslado-pdf', compact('traslado', 'empresa'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('traslado-' . $traslado->id . '.pdf');
    }

    public function exportarPdf(Request $request) {
        $traslados = Traslado::when($request->inicio, function($query) use ($request){
                                return $query->where('created_at', '>=', $request->inicio . ' 00:00:00');
                            })
                            ->when($request->fin, function($query) use ($request){
                                return $query->where('created_at', '<=', $request->fin . ' 23:59:59');
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->where('id_bodega_de', $request->id_bodega_de);
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega_para);
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->where(function($q) use ($request){
                                    $q->whereHas('producto', function($p) use ($request){
                                        $p->where('nombre', 'like',  '%'. $request->search . '%');
                                    })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                                });
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

        $empresa = Empresa::with('currency')->findOrFail(Auth::user()->id_empresa);

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

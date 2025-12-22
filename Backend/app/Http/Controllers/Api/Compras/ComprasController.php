<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Documento;
use App\Models\Compras\Compra;
use App\Models\Compras\DevolucionCompra;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exports\ComprasExport;
use App\Exports\ComprasDetallesExport;
use App\Exports\RentabilidadSucursalExport;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComprasController extends Controller
{
    

    public function index(Request $request) {
       
        $compras = Compra::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->recurrente !== null, function($q) use ($request){
                            $q->where('recurrente', !!$request->recurrente);
                        })
                        ->when($request->num_identificacion, function ($q) use ($request) {
                            $q->where('num_identificacion', $request->num_identificacion);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_bodega, function($query) use ($request){
                            return $query->where('id_bodega', $request->id_bodega);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->id_proyecto, function($query) use ($request){
                            return $query->where('id_proyecto', $request->id_proyecto);
                        })
                        ->when($request->dte && $request->dte == 0, function($query) {
                                return $query->whereNull('sello_mh');
                        })
                        ->when($request->dte && $request->dte == 1, function($query) {
                            return $query->whereNotNull('sello_mh');
                        })
                        ->where('cotizacion', 0)
                        ->when($request->buscador, function($query) use ($request){
                        return $query->whereHas('proveedor', function($q) use ($request){
                                    $q->where('nombre', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nombre_empresa', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('ncr', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nit', 'like' ,"%" . $request->buscador . "%");
                                 })->orwhere('referencia', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->withSum(['abonos' => function ($query) {
                            $query->where('estado', 'Confirmado');
                        }], 'total')
                        ->withSum(['devoluciones' => function ($query) {
                            $query->where('enable', 1);
                        }], 'total')
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->paginate($request->paginate);

        foreach ($compras as $compra) {
            $compra->saldo = $compra->saldo;
        }

        return Response()->json($compras, 200);
           
    }

    public function read($id) {

        $compra = Compra::where('id', $id)->with('detalles', 'proveedor', 'abonos', 'devoluciones')->first();
        $compra->saldo = $compra->saldo;
        return Response()->json($compra, 200);
 
    }

    public function search($txt) {

        $compras = Compra::whereHas('proveedor', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })
                    ->paginate(10);

        return Response()->json($compras, 200);

    }

    public function filter(Request $request) {

        $compras = Compra::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->referencia, function($query) use ($request){
                                return $query->where('referencia', $request->referencia);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->id_proveedor, function($query) use ($request){
                                return $query->whereHas('proveedor', function($query) use ($request)
                                {
                                    $query->where('id_proveedor', $request->id_proveedor);

                                });
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($compras, 200);

    }



    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'id_empresa'        => 'required',
            'id_bodega'       => 'required',
            'id_sucursal'       => 'required',
            'id_usuario'        => 'required',
        ]);

        $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();

            // Ajustar stocks
            foreach ($compra->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $compra->id_bodega)->first();
                
                // Anular compra y regresar stock
                if(($compra->estado != 'Anulada') && ($request['estado'] == 'Anulada')){

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad * -1);
                    }

                    // Abonos
                    foreach ($compra->abonos as $abono) {
                        $abono->estado = 'Cancelado';
                        $abono->save();
                    }

                }
                // Cancelar anulación de compra y descargar stock
                if(($compra->estado == 'Anulada') && ($request['estado'] != 'Anulada')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad);
                    }

                    // Abonos
                    foreach ($compra->abonos as $abono) {
                        $abono->estado = 'Confirmado';
                        $abono->save();
                    }

                }
            }
        
        $compra->fill($request->all());
        $compra->save();

        return Response()->json($compra, 200);

    }

    public function delete($id)
    {
        $compra = Compra::where('id', $id)->with('detalles')->firstOrFail();
        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }


    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'tipo_documento'    => 'required',
            // 'tipo_documento'    => 'required',
            // 'condicion'         => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            // 'cuotas'            => 'required_if:forma_pago,"Crédito"',
            'referencia'        => 'required_if:estado,"Pre-compra"',
            'tipo_documento'    => 'required_if:estado,"Pre-compra"',
            // 'referencia'        => 'required',
            'id_usuario'        => 'required',
            'id_empresa'        => 'required',
        ],[
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'detalles.required' => 'Los detalles son obligatorios.'
        ]);

        DB::beginTransaction();
         
        try {
        

        // Compra
            if($request->id)
                $compra = Compra::findOrFail($request->id);
            else
                $compra = new Compra;

            $compra->fill($request->all());
            $compra->save();


        // Detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['id_compra'] = $compra->id;
                
                $detalle->fill($det);
                $detalle->save();

                if (!$request->id) {
                    $producto = $detalle->producto()->with('inventarios')->first();
                    if ($producto) {
                        $stock_anterior = ($producto->inventarios->sum('stock') ?? 0);
                        $stock_actual = $det['cantidad']; // Cantidad comprada
                        $stock_total = $stock_anterior + $stock_actual; // Nuevo stock total

                        // Evitar división por cero
                        if ($stock_total > 0) {
                            $costo_promedio = (($stock_anterior * $producto->costo) + ($stock_actual * $det['costo'])) / $stock_total;
                        } else {
                            $costo_promedio = $det['costo'];
                        }

                        $producto->costo_anterior   = $producto->costo;
                        $producto->costo            = $det['costo'];
                        $producto->costo_promedio   = $costo_promedio;
                        $producto->save();
                    }

                }

                if ($request->cotizacion == 0) {
                    // Actualizar inventario solo si existe (si no existe, el producto no lleva inventario)
                    $inventario = Inventario::where('id_producto', $det['id_producto'])
                        ->where('id_bodega', $compra->id_bodega)
                        ->lockForUpdate() // Bloquear fila para evitar condiciones de carrera
                        ->first();

                    if ($inventario) {
                        // Actualizar stock de forma atómica
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        
                        // Registrar kardex
                        // Si falla el kardex, la transacción hará rollback automáticamente
                        $inventario->kardex($compra, $det['cantidad']);
                    }

                }



            }

        // Incrementar el correlarivo de orden de compra
        if (!$request->id && $request->tipo_documento == 'Orden de compra') {
            $documento = Documento::where('nombre', $compra->tipo_documento)->where('id_sucursal', $compra->id_sucursal)->first();
            $documento->increment('correlativo');
        }

        
        // Incrementar el correlarivo de Sujeto excluido
        if (!$request->id && $request->tipo_documento == 'Sujeto excluido') {
            $documento = Documento::where('nombre', $compra->tipo_documento)->where('id_sucursal', $compra->id_sucursal)->first();
            $documento->increment('correlativo');
        }

        DB::commit();
        return Response()->json($compra, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function facturacionConsigna(Request $request){
        $request->validate([
            'id'                => 'required',
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            // 'referencia'        => 'required|numeric',
            'tipo_documento'    => 'required|max:255',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_bodega'       => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos a la venta',
        ]);

        DB::beginTransaction();
      
        try {
            $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();
            if ($compra->total != $request->total) {
                // Crear consigna
                $consigna = new Compra();
                $consigna->fill($request->all());
                $consigna->estado = 'Consigna';
                $consigna->sub_total = $compra->sub_total - $request->sub_total;
                $consigna->total = $compra->total - $request->total;
                $consigna->iva = $compra->iva - $request->iva;
                $consigna->save();

                foreach($request->detalles as $detalle){
                    
                    $detalle_compra = $compra->detalles()->where('id', $detalle['id'])->first();
                    if ($detalle_compra) {
                        if ($detalle_compra->cantidad > $detalle['cantidad']) {
                            $detalle_consigna = new Detalle();
                            $detalle_consigna->id_producto = $detalle['id_producto'];
                            $detalle_consigna->costo = $detalle['costo'];
                            $detalle_consigna->cantidad = $detalle_compra->cantidad - $detalle['cantidad'];
                            $detalle_consigna->total = $detalle_consigna->costo * $detalle_consigna->cantidad;
                            $detalle_consigna->id_compra = $consigna->id;
                            $detalle_consigna->save();
                        }
                    }
                  
                }
                
                //Guardar nuevos detalles
                $compra->detalles()->delete();

                foreach ($request->detalles as $detalle) {
                    if ($detalle['cantidad'] > 0) {
                        $det = new Detalle();
                        $det->id_producto = $detalle['id_producto'];
                        $det->cantidad = $detalle['cantidad'];
                        $det->costo = $detalle['costo'];
                        $det->total = $detalle['cantidad'] * $detalle['costo'];
                        $det->descuento = 0;
                        $det->id_compra = $compra->id;
                        $det->save();
                    }
                }
                
                $compra->total = $request->total;
                $compra->iva = $request->iva;
                $compra->sub_total = $request->sub_total;
            }


            $compra->fecha = $request->fecha;
            $compra->estado = 'Pagada';
            $compra->save();

            DB::commit();
            return Response()->json($compra, 200);

            } catch (\Exception $e) {
                DB::rollback();
                return Response()->json(['error' => $e->getMessage()], 400);
            } catch (\Throwable $e) {
                DB::rollback();
                return Response()->json(['error' => $e->getMessage()], 400);
            }
    }

    public function libroCompras(Request $request) {
        $star = $request->inicio;
        $end = $request->fin;

        $compras = Compra::with('proveedor')->where('estado', '!=', 'Anulada')
                            ->when($request->tipo_documento, function($query) use ($request){
                                return $query->whereHas('documento', function($q) use ($request) {
                                        $q->where('nombre', $request->tipo_documento);
                                    });
                            })
                            ->when($request->id_sucursal, function($q) use ($request){
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('cotizacion', 0)
                            ->orderBy('id', 'desc')->get();

        $ivas = collect();

        foreach ($compras as $compra) {
                $ivas->push([
                    'fecha'                 => $compra->fecha,
                    'clase_documento'       => 1,
                    'tipo_documento'        => $compra->tipo_documento,
                    'num_documento'         => $compra->referencia,
                    'nit_nrc'               => $compra->proveedor()->pluck('nit')->first() ? $compra->proveedor()->pluck('nit')->first() : $compra->proveedor()->pluck('ncr')->first(),
                    'nombre_proveedor'        => $compra->nombre_proveedor,
                    'compras_exentas'        => $compra->exenta,
                    'compras_no_sujetas'     => $compra->no_sujeta,
                    'compras_gravadas'       => $compra->sub_total,
                    'debito_fiscal'         => $compra->iva,
                    'compras_cuenta_terceros'=> 0,
                    'debito_cuenta_terceros'=> 0,
                    'total'                 => $compra->total,
                    'dui'                   => $compra->proveedor()->pluck('dui')->first(),
                    'num_anexto'            => 1,
                ]);
        }

        // $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);

    }


    public function detalles($id)
    {
        $compra = Compra::findOrFail($id);

        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);

    }


    public function comprasProveedor($id) {

        $compras = Compra::where('id_proveedor', $id)->orderBy('estado', 'asc')->paginate(10);

        return Response()->json($compras, 200);

    }

    public function cxp() {
       
        $pagos = Compra::where('estado', 'Pendiente')->orderBy('fecha','desc')->paginate(10);

        return Response()->json($pagos, 200);

    }

    public function cxpBuscar($txt) {
       
        $pagos = Compra::where('estado', 'Pendiente')
                        ->whereHas('proveedor', function($query) use ($txt) {
                            $query->where('nombre', 'like' ,'%' . $txt . '%');
                        })
                        ->orderBy('fecha','desc')->paginate(10);

        return Response()->json($pagos, 200);

    }

    public function historial(Request $request) {

        $compras = Compra::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->get()
                        ->groupBy(function($date) {
                            return Carbon::parse($date->fecha)->format('d-m-Y');
                        });
        
        $movimientos = collect();

        foreach ($compras as $compra) {
            $movimientos->push([
                'cantidad'      => $compra->count(),
                'fecha'         => $compra[0]->fecha,
                'subtotal'      => $compra->sum('subtotal'),
                'iva'           => $compra->sum('iva'),
                'total'         => $compra->sum('total'),
                'detalles'      => $compra
            ]);
        }

        return Response()->json($movimientos, 200);

    }

    public function export(Request $request){
        $compras = new ComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras.xlsx');
    }

    public function exportDetalles(Request $request){
        $compras = new ComprasDetallesExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras-detalles.xlsx');
    }

    public function sinDevolucion(){

        $compras = Compra::where('estado', '!=', 'Anulada')
                        ->where(function ($query) {
                            // Obtener la fecha límite (hace dos meses desde ahora)
                            $fechaInicio = Carbon::now()->subMonths(2)->startOfMonth();
                            $fechaFin = Carbon::now()->endOfMonth();

                            $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
                        })
                        ->whereDoesntHave('devoluciones')
                        ->orderBy('fecha', 'DESC')
                        ->get();

        return Response()->json($compras, 200);
    }


    public function exportRentabilidad(Request $request)
    {

        //enviar id de la empresa en el request

        $user = JWTAuth::parseToken()->authenticate();
        $request->request->add(['id_empresa' => $user->id_empresa]);
        $ventas = new RentabilidadSucursalExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'corte.xlsx');
    }


    public function getNumerosIdentificacion(){
        $numsIds = Compra::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();
        
        return Response()->json($numsIds, 200);
     }

    public function generarCompraDesdeOrdenCompra(Request $request){
        $request->validate([
            'id' => 'required', // ID de la venta
            'num_orden' => 'required',
        ]);

        DB::beginTransaction();
        
        try {
            // Buscar la venta
            $venta = \App\Models\Ventas\Venta::where('id', $request->id)
                ->with('detalles', function($query) use ($request){
                    $query->withoutGlobalScope('empresa');
                }, 'cliente')
                ->firstOrFail();
            
            $orden_compra = Compra::withoutGlobalScope('empresa')->where('id', $request->num_orden)
                ->where('cotizacion', 1)
                ->with('detalles', 'proveedor')
                ->firstOrFail();

            // Buscar si ya existe una compra con el mismo tipo de documento, proveedor y correlativo
            $compraExistente = Compra::withoutGlobalScope('empresa')->where('tipo_documento', $venta->nombre_documento)
                ->where('id_proveedor', $orden_compra->id_proveedor)
                ->where('referencia', $venta->correlativo)
                ->where('id_empresa', $orden_compra->id_empresa)
                ->where('cotizacion', 0)
                ->first();

            if ($compraExistente) {
                return Response()->json([
                    'error' => 'Ya existe una compra con este tipo de documento, proveedor y correlativo.',
                ], 403);
            }
            
            // Crear la nueva compra basada en la venta
            $compra = new Compra();
            
            // Configurar campos básicos de la compra
            $compra->fecha = $venta->fecha;
            $compra->estado = $venta->estado;
            $compra->tipo_documento = $venta->nombre_documento;
            $compra->referencia = $venta->correlativo;
            $compra->forma_pago = $venta->forma_pago;
            $compra->fecha_pago = $venta->fecha_pago;
            $compra->id_usuario = $orden_compra->id_usuario;
            $compra->id_empresa = $orden_compra->id_empresa;
            $compra->id_bodega = $orden_compra->id_bodega;
            $compra->id_sucursal = $orden_compra->id_sucursal;
            $compra->cotizacion = 0; // Marcar como compra real, no cotización
            $compra->id_proveedor = $orden_compra->id_proveedor; // Usar el cliente como proveedor
            
            $compra->sub_total = $venta->sub_total;
            $compra->iva = $venta->iva;
            $compra->total = $venta->total;
            
            $compra->save();
            
            // Crear detalles de la compra a partir de los detalles de la venta
            foreach ($venta->detalles as $detalle_venta) {
                // Obtener el código del producto de la venta
                $producto_venta = \App\Models\Inventario\Producto::withoutGlobalScope('empresa')->where('id_empresa', $orden_compra->id_empresa)->where('codigo', $detalle_venta->codigo)->firstOrFail();
                if ($producto_venta) {
                        $detalle_compra = new Detalle();
                        $detalle_compra->id_producto = $producto_venta->id; // ID del producto en la empresa hija
                        $detalle_compra->cantidad = $detalle_venta->cantidad;
                        $detalle_compra->costo = $detalle_venta->precio; // Precio de la venta como costo
                        $detalle_compra->total = $detalle_venta->total;
                        $detalle_compra->descuento = $detalle_venta->descuento ?? 0;
                        $detalle_compra->id_compra = $compra->id;
                        $detalle_compra->save();

                        // Actualizar costo producto al de la ultima compra    
                            $producto_venta->costo_anterior   = $producto_venta->costo;
                            $producto_venta->costo            = $detalle_venta->precio;
                            $producto_venta->costo_promedio   = $detalle_venta->precio;
                            $producto_venta->save();

                        // Actualizar inventario
                        $inventario = Inventario::withoutGlobalScope('empresa')->where('id_producto', $producto_venta->id)
                            ->where('id_bodega', $compra->id_bodega)
                            ->first();

                        if ($inventario) {
                            $inventario->stock += $detalle_venta->cantidad;
                            $inventario->save();
                            $inventario->kardex($compra, $detalle_venta->cantidad);
                        }

                }
            }

            $orden_compra->estado = 'Aceptada';
            $orden_compra->save();

            DB::commit();
            return Response()->json($compra, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }


}

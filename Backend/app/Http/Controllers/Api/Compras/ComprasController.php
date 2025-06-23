<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\Authorization\HasAutoAuthorization;

use App\Models\Admin\Documento;
use App\Models\Compras\Compra;
use App\Models\Compras\DevolucionCompra;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use Illuminate\Support\Facades\DB;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use App\Services\Authorization\AuthorizationService;

use App\Exports\ComprasExport;
use App\Exports\ComprasDetallesExport;
use App\Models\OrdenCompra;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;


class ComprasController extends Controller
{
    use HasAutoAuthorization;
    protected $authModule = 'compras';

    protected $transaccionesService;
    protected $chequesService;
    protected $authorizationService;

    public function __construct(TransaccionesService $transaccionesService, ChequesService $chequesService,AuthorizationService $authorizationService)
    {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
        $this->authorizationService = $authorizationService;
    }

    public function index(Request $request)
    {

        $compras = Compra::with('retaceo')->when($request->inicio, function ($query) use ($request) {
            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        })
            ->when($request->recurrente !== null, function ($q) use ($request) {
                $q->where('recurrente', !!$request->recurrente);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_bodega, function ($query) use ($request) {
                return $query->where('id_bodega', $request->id_bodega);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->forma_pago, function ($query) use ($request) {
                return $query->where('forma_pago', $request->forma_pago);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function ($query) use ($request) {
                return $query->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->dte && $request->dte == 0, function ($query) {
                return $query->whereNull('sello_mh');
            })
            ->when($request->dte && $request->dte == 1, function ($query) {
                return $query->whereNotNull('sello_mh');
            })
            ->when($request->es_retaceo, function($query) use ($request) {
                return $query->where('es_retaceo', true)
                            ->when($request->es_retaceo === 'true',
                                function($q) { return $q->whereDoesntHave('retaceo'); },
                                function($q) { return $q->whereHas('retaceo'); }
                            );
            })
            ->where('cotizacion', 0)
            ->when($request->buscador, function ($query) use ($request) {
                return $query->whereHas('proveedor', function ($q) use ($request) {
                    $q->where('nombre', 'like', "%" . $request->buscador . "%")
                        ->orwhere('nombre_empresa', 'like', "%" . $request->buscador . "%")
                        ->orwhere('ncr', 'like', "%" . $request->buscador . "%")
                        ->orwhere('nit', 'like', "%" . $request->buscador . "%");
                })->orwhere('referencia', 'like', '%' . $request->buscador . '%')
                    ->orwhere('estado', 'like', '%' . $request->buscador . '%')
                    ->orwhere('observaciones', 'like', '%' . $request->buscador . '%')
                    ->orwhere('forma_pago', 'like', '%' . $request->buscador . '%');
            })
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        foreach ($compras as $compra) {
            $compra->saldo = $compra->saldo;
        }

        return Response()->json($compras, 200);
    }

    public function read($id)
    {

        $compra = Compra::where('id', $id)->with('detalles.producto', 'proveedor', 'abonos')->first();
        $compra->saldo = $compra->saldo;

        return Response()->json($compra, 200);
    }

    public function search($txt)
    {

        $compras = Compra::whereHas('proveedor', function ($query) use ($txt) {
            $query->where('nombre', 'like', '%' . $txt . '%');
        })
            ->paginate(10);

        return Response()->json($compras, 200);
    }

    public function filter(Request $request)
    {

        $compras = Compra::when($request->inicio, function ($query) use ($request) {
            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        })
            ->when($request->referencia, function ($query) use ($request) {
                return $query->where('referencia', $request->referencia);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->whereHas('proveedor', function ($query) use ($request) {
                    $query->where('id_proveedor', $request->id_proveedor);
                });
            })
            ->orderBy('id', 'desc')->paginate(100000);

        return Response()->json($compras, 200);
    }


    public function store(Request $request)
    {
        DB::beginTransaction();
        $data = $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'id_empresa'        => 'required',
            'id_bodega'         => 'required',
            'id_sucursal'       => 'required',
            'id_usuario'        => 'required',
        ]);
        
        if ($response = $this->checkAuth('store', $data)) {
            return $response;
        }


        $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();
        $orden = null;
        if ($compra->num_orden_compra) {
            $orden = OrdenCompra::where('id', $compra->num_orden_compra)->with("detalles")->first();
        }

        // Ajustar stocks
        foreach ($compra->detalles as $detalle) {

            $producto = Producto::where('id', $detalle->id_producto)
                ->with('composiciones')->firstOrFail();

            $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $compra->id_bodega)->first();

            // Anular compra y regresar stock
            if (($compra->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

                if ($inventario) {
                    $inventario->stock -= $detalle->cantidad;
                    $inventario->save();
                    $inventario->kardex($compra, $detalle->cantidad * -1);
                }
                //restaurar cantidad ingresada en orden de compra
                if ($orden) {
                    $detalle_orden = $orden->detalles->where('id_producto', $detalle->id_producto)->first();

                    $detalle_orden->cantidad_procesada = floatval($detalle_orden->cantidad_procesada) - floatval($detalle->cantidad);
                    // return [$det, $detalle_orden];
                    $detalle_orden->save();

                    $orden->estado = 'Pendiente';
                    $orden->save();
                }

                // Abonos
                foreach ($compra->abonos as $abono) {
                    $abono->estado = 'Cancelado';
                    $abono->save();
                }
            }
            // Cancelar anulación de compra y descargar stock
            if (($compra->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
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
        DB::commit();
        // return  $orden = OrdenCompra::where('id', $compra->num_orden_compra)->with("detalles")->first();

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

    public function facturacion(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'tipo_documento'    => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            'referencia'        => 'required_if:estado,"Pre-compra"',
            'tipo_documento'    => 'required_if:estado,"Pre-compra"',
            'id_usuario'        => 'required',
            'id_empresa'        => 'required',
        ], [
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'detalles.required' => 'Los detalles son obligatorios.'
        ]);

        Log::info("Facturacion - iniciando proceso");
    
        // VERIFICAR AUTORIZACIÓN - Solo para compras nuevas sin authorization_id
        if (!$request->id && !$request->id_authorization) {
            $total = $this->calcularTotalCompra($request);
            
            if ($total > 3000) {
                Log::info("Compra requiere autorización - Total: $" . $total);
                
                return response()->json([
                    'ok' => false,
                    'requires_authorization' => true,
                    'authorization_type' => 'compras_altas',
                    'message' => "Esta compra de $" . number_format($total, 2) . " requiere autorización (supera los $3,000)"
                ], 403);
            }
        }
    
        Log::info("Procesando compra normal o autorizada");
    
        DB::beginTransaction();

        try {
            // Compra
            if ($request->id)
                $compra = Compra::findOrFail($request->id);
            else
                $compra = new Compra;

            $compra->fill($request->merge(["id_sucursal" => Auth::user()->id_sucursal])->all());
            $compra->save();

            // Detalles
            foreach ($request->detalles as $det) {
                if (isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['id_compra'] = $compra->id;

                $detalle->fill($det);

                if ($request->cotizacion == 0) {
                    // Actualizar inventario
                    $inventario = Inventario::where('id_producto', $det['id_producto'])->where('id_bodega', $compra->id_bodega)->first();

                    if ($inventario) {
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($compra, $det['cantidad'], null, $det['costo']);
                    }
                }

                $detalle->save();

                if (!$request->id) {
                    $producto = $detalle->producto()->with('inventarios')->first();
                    if ($producto) {
                        $stock_anterior = ($producto->inventarios->sum('stock') ?? 0) - $det['cantidad'];
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
            }

            //actualizar orden de compra
            if ($compra->num_orden_compra) {
                $detCollection = collect($request->detalles);
                $orden = OrdenCompra::where('id', $compra->num_orden_compra)->with("detalles")->first();
                $finishOrder = true;
                foreach ($orden->detalles as $detalle) {
                    $det = $detCollection->where('id_producto', $detalle->id_producto)->first();
                    if ($det) {
                        $detalle->cantidad_procesada += $det['cantidad'];
                        if ($detalle->cantidad_procesada < $detalle->cantidad) {
                            $finishOrder = false;
                        }
                        $detalle->save();
                    }
                }
                if ($detCollection->count() != $orden->detalles->count()) {
                    $finishOrder = false;
                }
                if ($finishOrder)
                    $orden->estado = 'Aceptada';

                $orden->save();
            }

            // Crear transaccion bancaria
            if (!$request->id && $compra->cotizacion == 0 && $compra->forma_pago != 'Efectivo' && $compra->forma_pago != 'Cheque') {
                $this->transaccionesService->crear($compra, 'Cargo', 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }

            // Crear cheque
            if (!$request->id && $compra->cotizacion == 0 && $compra->forma_pago == 'Cheque') {
                $this->chequesService->crear($compra, $compra->nombre_proveedor, 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }

            // Incrementar el correlarivo de orden de compra
            if ($request->estado == 'Pre-compra') {
                $documento = Documento::where('nombre', $compra->tipo_documento)->first();
                $documento->increment('correlativo');
            }

            // Incrementar el correlarivo de Sujeto excluido
            if ($request->tipo_documento == 'Sujeto excluido') {
                $documento = Documento::where('nombre', $compra->tipo_documento)->first();
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

    public function facturacionConsigna(Request $request)
    {
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

                foreach ($request->detalles as $detalle) {

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

    public function detalles($id)
    {
        $compra = Compra::findOrFail($id);

        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }


    public function comprasProveedor($id)
    {

        $compras = Compra::where('id_proveedor', $id)->orderBy('estado', 'asc')->paginate(10);

        return Response()->json($compras, 200);
    }

    public function cxp()
    {

        $pagos = Compra::where('estado', 'Pendiente')->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($pagos, 200);
    }

    public function cxpBuscar($txt)
    {

        $pagos = Compra::where('estado', 'Pendiente')
            ->whereHas('proveedor', function ($query) use ($txt) {
                $query->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($pagos, 200);
    }

    public function historial(Request $request)
    {

        $compras = Compra::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->groupBy(function ($date) {
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

    public function export(Request $request)
    {
        $compras = new ComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras.xlsx');
    }

    public function exportDetalles(Request $request)
    {
        $compras = new ComprasDetallesExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras-detalles.xlsx');
    }

    public function sinDevolucion()
    {

        $compras = Compra::where('estado', '!=', 'Anulada')
            ->whereMonth('fecha', '>=', date('m') - 1)
            ->whereYear('fecha', date('Y'))
            ->whereDoesntHave('devoluciones')
            ->orderBy('fecha', 'DESC')
            ->get();

        return Response()->json($compras, 200);
    }

    public function procesarCompraAutorizada($compraId)
    {
        Log::info("Procesando compra autorizada: " . $compraId);
        
        DB::beginTransaction();
        
        try {
            $compra = Compra::findOrFail($compraId);
            
            // Cambiar estado a procesada
            $compra->estado = 'Pagada';
            $compra->save();
            
            // Actualizar inventarios (que no se hizo cuando estaba pendiente)
            foreach ($compra->detalles as $detalle) {
                if ($compra->cotizacion == 0) {
                    $inventario = Inventario::where('id_producto', $detalle->id_producto)
                                           ->where('id_bodega', $compra->id_bodega)
                                           ->first();

                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad, null, $detalle->costo);
                    }
                    
                    // Actualizar costo del producto
                    $producto = $detalle->producto()->with('inventarios')->first();
                    if ($producto) {
                        $stock_anterior = ($producto->inventarios->sum('stock') ?? 0) - $detalle->cantidad;
                        $stock_actual = $detalle->cantidad;
                        $stock_total = $stock_anterior + $stock_actual;

                        if ($stock_total > 0) {
                            $costo_promedio = (($stock_anterior * $producto->costo) + ($stock_actual * $detalle->costo)) / $stock_total;
                        } else {
                            $costo_promedio = $detalle->costo;
                        }

                        $producto->costo_anterior = $producto->costo;
                        $producto->costo = $detalle->costo;
                        $producto->costo_promedio = $costo_promedio;
                        $producto->save();
                    }
                }
            }
            
            // Crear transacciones bancarias si aplica
            if ($compra->cotizacion == 0 && $compra->forma_pago != 'Efectivo' && $compra->forma_pago != 'Cheque') {
                $this->transaccionesService->crear($compra, 'Cargo', 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }

            if ($compra->cotizacion == 0 && $compra->forma_pago == 'Cheque') {
                $this->chequesService->crear($compra, $compra->nombre_proveedor, 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }
            
            DB::commit();
            
            Log::info("Compra autorizada procesada exitosamente: " . $compraId);
            
            return $compra;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando compra autorizada: " . $e->getMessage());
            throw $e;
        }
    }

    protected function handlePendingAuthorization($data, $authorization)
    {
        Log::info("Creando compra pendiente de autorización");
        
        DB::beginTransaction();
        
        try {
            // Crear compra en estado pendiente
            $compraData = $data;
            $compraData['estado'] = 'Pendiente Autorización';
            $compraData['authorization_id'] = $authorization->id;
            $compraData['id_sucursal'] = Auth::user()->id_sucursal;
            
            $compra = new Compra;
            $compra->fill($compraData);
            $compra->save();
            
            // Crear detalles de la compra pendiente (sin actualizar inventario)
            foreach ($data['detalles'] as $det) {
                $detalle = new Detalle;
                $det['id_compra'] = $compra->id;
                $detalle->fill($det);
                $detalle->save();
            }
            
            // Actualizar la autorización con el ID de la compra creada
            $authorization->update([
                'authorizeable_id' => $compra->id
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'data' => $compra,
                'estado' => 'Pendiente Autorización',
                'requires_authorization' => true,
                'authorization_code' => $authorization->code,
                'message' => 'Compra creada pendiente de autorización'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando compra pendiente: " . $e->getMessage());
            
            return response()->json([
                'ok' => false,
                'requires_authorization' => true,
                'authorization_type' => $authorization->authorizationType->name,
                'message' => 'Error al crear compra pendiente: ' . $e->getMessage(),
                'authorization_code' => $authorization->code
            ], 403);
        }
    }

    private function calcularTotalCompra($request)
    {
        $total = $request->total ?? $request->sub_total ?? 0;
        
        // Si no hay total, calcularlo de los detalles
        if ($total == 0 && isset($request->detalles)) {
            $total = collect($request->detalles)->sum('total');
        }
        
        return $total;
    }
}

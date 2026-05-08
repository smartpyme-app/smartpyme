<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Exports\ReportesAutomaticos\EstadoFinancieroConsolidadoSucursales\EstadoFinancieroConsolidadoSucursalesExport;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorExport;
use App\Exports\ReportesAutomaticos\InventarioPorSucursal\InventarioExport;
use App\Exports\ReportesAutomaticos\VentasComprasPorMarcaProveedor\VentasComprasPorMarcaProveedorExport;
use App\Exports\VentasAcumuladoExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;
use App\Services\FidelizacionCliente\ConsumoPuntosService as FidelizacionConsumoPuntosService;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCompuesto;
use App\Models\Ventas\MetodoDePago;
use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Paquete;
use App\Services\Webhooks\WebhookPaqueteVentaDispatcher;
use App\Models\Contabilidad\Proyecto;
use App\Models\Eventos\Evento;
use App\Models\Restaurante\PedidoRestaurante;
use App\Services\Restaurante\PedidoCanalInventarioService;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Exports\VentasExport;
use App\Exports\VentasDetallesExport;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorExport;
use App\Exports\ReportesAutomaticos\VentasPorVendedor\VentasPorVendedorExport;
use App\Exports\VentasPorUtilidadesExport;
use App\Exports\VentasPorMarcasExport;
use App\Exports\CobrosPorVendedorExport;
use App\Exports\CuentasCobrarExport;
use App\Mail\ReporteVentasPorVendedor;
use Maatwebsite\Excel\Facades\Excel;
// use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class VentasController extends Controller
{

    public function index(Request $request)
    {
        $excludeFromList = ['dte_invalidacion'];
        $columns = array_values(array_diff(Schema::getColumnListing('ventas'), $excludeFromList));
        $dteIndex = array_search('dte', $columns, true);
        if ($dteIndex !== false) {
            $columns[$dteIndex] = DB::raw("IF(COALESCE(ventas.dte_s3_key,'') <> '', NULL, ventas.dte) as dte");
        }

        $ventas = Venta::select($columns)
            ->when($request->inicio, function ($query) use ($request) {
            return $query->where('fecha', '>=', $request->inicio);
        })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->recurrente !== null, function ($q) use ($request) {
                $q->where('recurrente', !!$request->recurrente);
            })
            ->when($request->num_identificacion, function ($q) use ($request) {
                $q->where('num_identificacion', $request->num_identificacion);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_bodega, function ($query) use ($request) {
                return $query->where('id_bodega', $request->id_bodega);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->forma_pago, function ($query) use ($request) {
                return $query->where('forma_pago', $request->forma_pago)
                    ->orwhereHas('metodos_de_pago', function ($query) use ($request) {
                        $query->where('nombre', $request->forma_pago);
                    });
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->where('id_vendedor', $request->id_vendedor)
                    ->orwhereHas('detalles', function ($query) use ($request) {
                        $query->where('id_vendedor', $request->id_vendedor);
                    });
            })
            ->when($request->id_canal, function ($query) use ($request) {
                return $query->where('id_canal', $request->id_canal);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function ($query) use ($request) {
                return $query->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->whereHas('documento', function ($q) use ($request) {
                    $q->where('nombre', $request->tipo_documento);
                });
            })
            ->when($request->id_documento, function ($query) use ($request) {
                $documento = Documento::find($request->id_documento);
                if ($documento) {
                    return $query->whereHas('documento', function ($q) use ($documento) {
                        $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                    });
                }
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->dte && $request->dte == 1, function ($query) {
                return $query->whereNull('sello_mh');
            })
            ->when($request->dte && $request->dte == 2, function ($query) {
                return $query->whereNotNull('sello_mh');
            })
            ->where('cotizacion', 0)
            ->when($request->buscador, fn ($query) => $this->aplicarFiltroBuscador($query, (string) $request->buscador))
            ->with(['cliente', 'usuario', 'vendedor', 'sucursal', 'canal', 'documento', 'proyecto'])
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        return Response()->json($ventas, 200);
    }

    /**
     * Aplica el filtro de búsqueda por cliente (FULLTEXT: nombre, apellido, ncr, nit, nombre_empresa),
     * correlativo (LIKE), y ventas (FULLTEXT: num_orden, observaciones, forma_pago, estado, numero_control).
     */
    private function aplicarFiltroBuscador($query, string $termino)
    {
        $termino = trim(preg_replace('/\s+/', ' ', $termino));
        if ($termino === '') {
            return $query;
        }

        $buscador = '%' . $termino . '%';
        $palabras = array_values(array_filter(explode(' ', $termino), fn ($p) => $p !== ''));

        $matchClientes = count($palabras) > 1
            ? implode(' ', array_map(fn ($p) => '+' . preg_replace('/[+\-<>()~*"]/', '', $p), $palabras))
            : $termino;
        $clienteIds = Cliente::query()
            ->whereRaw(
                'MATCH(clientes.nombre, clientes.apellido, clientes.nombre_empresa, clientes.nit, clientes.ncr) AGAINST(? IN ' . (count($palabras) > 1 ? 'BOOLEAN' : 'NATURAL LANGUAGE') . ' MODE)',
                [$matchClientes]
            )
            ->limit(5000)
            ->pluck('id');

        return $query->where(function ($q) use ($buscador, $clienteIds, $termino) {
            if ($clienteIds->isNotEmpty()) {
                $q->whereIn('id_cliente', $clienteIds);
            }
            $q->orWhere('correlativo', 'like', $buscador)
                ->orWhereRaw(
                    'MATCH(ventas.num_orden, ventas.observaciones, ventas.forma_pago, ventas.estado, ventas.numero_control) AGAINST(? IN NATURAL LANGUAGE MODE)',
                    [$termino]
                );
        });
    }

    public function read($id)
    {
        $venta = Venta::where('id', $id)
            ->with('devoluciones', 'detalles.composiciones', 'detalles.vendedor', 'detalles.producto', 'abonos.usuario', 'cliente', 'impuestos.impuesto', 'metodos_de_pago')
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->first();

        if (!$venta) {
            return response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
        }

        $venta->saldo = round($venta->total - ($venta->abonos_sum_total ?? 0) - ($venta->devoluciones_sum_total ?? 0), 2);
        return Response()->json($venta, 200);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'fecha'             => 'required',
    //         'estado'            => 'required',
    //         'id_usuario'        => 'required',
    //     ]);


    //     $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();

    //     // Ajustar stocks
    //     foreach ($venta->detalles as $detalle) {

    //         $producto = Producto::where('id', $detalle->id_producto)
    //             ->with('composiciones')->firstOrFail();

    //         $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();

    //         // Anular venta y regresar stock
    //         if (($venta->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

    //             if ($inventario) {
    //                 $inventario->stock += $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad * -1);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_producto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock += $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad) * -1);
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Cancelado';
    //                 $abono->save();
    //             }

    //             if ($inventario) {
    //                 $inventario->stock += $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad * -1);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_compuesto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock += $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, $detalle->cantidad);
    //                 }

    //                 // Inventario compuestos
    //                 foreach ($detalle->composiciones()->get() as $comp) {

    //                     $inventario = Inventario::where('id_producto', $comp->id_producto)
    //                         ->where('id_bodega', $venta->id_bodega)->first();

    //                     if ($inventario) {
    //                         $inventario->stock -= $detalle->cantidad * $comp->cantidad;
    //                         $inventario->save();
    //                         $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
    //                     }
    //                 }

    //                 // Abonos
    //                 foreach ($venta->abonos as $abono) {
    //                     $abono->estado = 'Confirmado';
    //                     $abono->save();
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Cancelado';
    //                 $abono->save();
    //             }
    //         }
    //         // Cancelar anulación de venta y descargar stock
    //         if (($venta->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
    //             // Aplicar stock
    //             if ($inventario) {
    //                 $inventario->stock -= $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_compuesto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock -= $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Confirmado';
    //                 $abono->save();
    //             }
    //         }
    //     }

    //     $venta->fill($request->all());
    //     $venta->save();

    //     return Response()->json($venta, 200);
    // }


    public function store(Request $request)
    {
        $request->validate([
            'id'                => 'required|numeric',
            'fecha'             => 'required',
            'estado'            => 'required',
            'id_usuario'        => 'required',
        ]);

        // Buscar la venta respetando el scope global de empresa
        $venta = Venta::where('id', $request->id)->with('detalles')->first();
        
        if (!$venta) {
            return response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
        }

        $webhookPaquetesFacturadosBulk = false;

        // Ajustar stocks
        foreach ($venta->detalles as $detalle) {

            $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();

            // Anular venta y regresar stock
            if (($venta->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

                // Si el detalle tiene lote_id, regresar stock al lote
                if ($detalle->lote_id) {
                    $lote = Lote::find($detalle->lote_id);
                    if ($lote) {
                        $lote->stock += $detalle->cantidad;
                        $lote->save();
                    }
                }

                if ($inventario) {
                    $inventario->stock += $detalle->cantidad;
                    $inventario->save();
                    $inventario->kardex($venta, $detalle->cantidad * -1);
                }

                // Inventario compuestos
                foreach ($detalle->composiciones()->get() as $comp) {

                    $inventario = Inventario::where('id_producto', $comp->id_producto)
                        ->where('id_bodega', $venta->id_bodega)->first();

                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad * $comp->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad) * -1);
                    }
                }

                // Abonos
                foreach ($venta->abonos as $abono) {
                    $abono->estado = 'Cancelado';
                    $abono->save();
                }

                // Paquetes de la venta: cambiar estado a En bodega
                Paquete::where('id_venta', $venta->id)->update(['estado' => 'En bodega']);
            }
            // Cancelar anulación de venta y descargar stock
            if (($venta->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
                // Si el detalle tiene lote_id, descontar del lote
                if ($detalle->lote_id) {
                    $lote = Lote::find($detalle->lote_id);
                    if ($lote && $lote->stock >= $detalle->cantidad) {
                        $lote->stock -= $detalle->cantidad;
                        $lote->save();
                    }
                }
                
                // Aplicar stock
                if ($inventario) {
                    $inventario->stock -= $detalle->cantidad;
                    $inventario->save();
                    $inventario->kardex($venta, $detalle->cantidad);
                }

                // Inventario compuestos
                foreach ($detalle->composiciones()->get() as $comp) {

                    $inventario = Inventario::where('id_producto', $comp->id_producto)
                        ->where('id_bodega', $venta->id_bodega)->first();

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad * $comp->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
                    }
                }

                // Abonos
                foreach ($venta->abonos as $abono) {
                    $abono->estado = 'Confirmado';
                    $abono->save();
                }
                // Paquetes de la venta: al revertir anulación quedan Facturados (update masivo no dispara observer)
                Paquete::where('id_venta', $venta->id)->update(['estado' => 'Facturado']);
                if (!$webhookPaquetesFacturadosBulk) {
                    $webhookPaquetesFacturadosBulk = true;
                    $idsPaquetes = Paquete::withoutGlobalScopes()
                        ->where('id_venta', $venta->id)
                        ->pluck('id');
                    foreach ($idsPaquetes as $pid) {
                        WebhookPaqueteVentaDispatcher::dispatch((int) $pid);
                    }
                }
            }
        }

        // El frontend ya envía el total sin propina, así que no necesitamos ajustarlo
        $venta->fill($request->all());
        $venta->save();

        return Response()->json($venta, 200);
    }

    public function delete($id)
    {
        $venta = Venta::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);
    }



    // Facturacion

    public function corte()
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $caja   = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte  = $caja->corte;
        $ventas = $corte->ventas()->orderBy('id', 'desc')
            ->paginate(30);

        return Response()->json($ventas, 200);
    }

    public function facturacion(Request $request)
    {
        // Validar que usuarios "Ventas Limitado" no puedan crear ventas al crédito
        $user = auth()->user();
        if ($user->tipo === 'Ventas Limitado' && $request->credito == 1) {
            return response()->json([
                'error' => 'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.'
            ], 403);
        }

        // Validar límite de crédito del cliente (si aplica)
        if ($request->estado === 'Pendiente' && $request->id_cliente) {
            $cliente = Cliente::find($request->id_cliente);
            if ($cliente && $cliente->limite_credito !== null && $cliente->limite_credito > 0) {
                $ventasPendientes = Venta::where('id_cliente', $request->id_cliente)
                    ->where('estado', 'Pendiente')
                    ->where(function ($q) {
                        $q->where('cotizacion', 0)->orWhereNull('cotizacion');
                    })
                    ->when($request->id, fn ($q) => $q->where('id', '!=', $request->id))
                    ->withSum(['abonos' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
                    ->withSum(['devoluciones' => fn ($q) => $q->where('enable', 1)], 'total')
                    ->get();

                $saldoPendiente = $ventasPendientes->sum(function ($v) {
                    $abonos = $v->abonos_sum_total ?? 0;
                    $devoluciones = $v->devoluciones_sum_total ?? 0;
                    return round($v->total - $abonos - $devoluciones, 2);
                });

                $nuevoSaldo = round($saldoPendiente + (float) $request->total, 2);
                if ($nuevoSaldo > $cliente->limite_credito) {
                    return response()->json([
                        'error' => 'El cliente ha excedido su límite de crédito. Saldo pendiente: $' . number_format($saldoPendiente, 2) . '. Total con esta venta: $' . number_format($nuevoSaldo, 2) . '. Límite permitido: $' . number_format($cliente->limite_credito, 2) . '.'
                    ], 422);
                }
            }
        }

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            // 'correlativo'       => 'required|numeric|unique:ventas,correlativo,'.$request->id.',id,id_sucursal,'.$request->id_sucursal.',id_documento,'.$request->id_documento,
            'id_documento'      => 'required|max:255',
            'id_canal'          => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            'fecha_expiracion'  => 'required_if:cotizacion,1',
            'descripcion_impresion'  => 'required_if:descripcion_personalizada,1',
            'credito'           => 'required_if:condicion,"Crédito"',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_bodega'         => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
            'fecha_expiracion.required_if' => 'La fecha de expiracion es obligatorio cuando es cotización.',
        ]);

        DB::beginTransaction();

        try {

            // Obtener la empresa para verificar configuración de vender sin stock y lotes
            $empresa = Empresa::findOrFail(Auth::user()->id_empresa);
            $puedeVenderSinStock = $empresa->vender_sin_stock == 1;
            $lotesActivo = $empresa->isLotesActivo();

            $saltarActualizarInventario = false;
            if (!$request->id && $request->filled('id_pedido_canal')) {
                $pedidoCanalFactura = PedidoRestaurante::where('id', $request->id_pedido_canal)
                    ->where('id_empresa', Auth::user()->id_empresa)
                    ->where('estado', 'pendiente_facturar')
                    ->whereNull('id_venta')
                    ->with('detalles')
                    ->first();
                if (!$pedidoCanalFactura) {
                    DB::rollBack();
                    return response()->json(['error' => 'Pedido de canal no válido para facturar o ya fue vinculado.'], 422);
                }
                if (!$pedidoCanalFactura->id_bodega) {
                    DB::rollBack();
                    return response()->json(['error' => 'El pedido no tiene bodega de inventario. Confirme el pedido con una bodega o anule y vuelva a crear.'], 422);
                }
                if ((int) $request->id_bodega !== (int) $pedidoCanalFactura->id_bodega) {
                    DB::rollBack();
                    return response()->json(['error' => 'La bodega de la factura debe coincidir con la bodega del pedido (el inventario ya se descontó al confirmar).'], 422);
                }
                if (!PedidoCanalInventarioService::ventaCoincideConPedido($pedidoCanalFactura, $request->detalles ?? [])) {
                    DB::rollBack();
                    return response()->json(['error' => 'Las cantidades por producto deben coincidir con el pedido de canal; el stock ya se comprometió al confirmar.'], 422);
                }
                $saltarActualizarInventario = true;
            }

            if ($request->id)
                $venta = Venta::findOrFail($request->id);
            else
                $venta = new Venta;
            
            // El frontend ya envía el total sin propina, así que no necesitamos ajustarlo
            $venta->fill($request->all());

                $documento = Documento::where('id', $request->id_documento)
                            ->lockForUpdate()
                            ->firstOrFail();

                $venta->correlativo = $documento->correlativo;
                $documento->increment('correlativo');

            $venta->save();

            // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if (isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['id_venta'] = $venta->id;


                $detalle->fill($det);
                $detalle->save();

                // Pagar si es paquete
                if (isset($det['id_paquete'])) {
                    $paquete = Paquete::find($det['id_paquete']);
                    if ($paquete) {
                        $paquete->estado = ($venta->estado == 'Pagada') ? 'Facturado' : 'Pendiente';
                        $paquete->fecha = $venta->fecha;
                        $paquete->id_venta = $venta->id;
                        $paquete->id_venta_detalle = $detalle->id;
                        $paquete->save();
                    }
                }

                // Pagar si es cita
                if (isset($det['id_cita'])) {
                    $evento = Evento::findOrfail($det['id_cita']);
                    if ($venta->estado == 'Pagada') {
                        $evento->estado = 'Pagado';
                        $evento->estadoVerificarFrecuencia('Pagado');
                    } else {
                        $evento->estado = 'Pendiente';
                        $evento->save();
                    }
                }

                // Si es compuesto
                if (isset($det['composiciones'])) {
                    foreach ($det['composiciones'] as $item) {
                        // Validar que id_compuesto exista antes de procesar
                        if (!isset($item['id_compuesto']) || empty($item['id_compuesto'])) {
                            continue; // Saltar esta composición si no tiene id_compuesto
                        }
                        $cd = new DetalleCompuesto;
                        $cd->id_producto = $item['id_compuesto'];
                        $cd->cantidad   = $item['cantidad'];
                        $cd->id_detalle = $detalle->id;
                        $cd->save();
                    }
                }


                // Actualizar inventario
                if ($request->cotizacion == 0 && !$saltarActualizarInventario) {

                    // Obtener el producto para verificar si es servicio
                    $producto = Producto::where('id', $det['id_producto'])->first();
                    
                    // Validar stock solo si no es servicio y si la empresa no permite vender sin stock
                    if ($producto && $producto->tipo != 'Servicio') {
                        $inventario = Inventario::where('id_producto', $det['id_producto'])
                            ->where('id_bodega', $venta->id_bodega)->first();
                        
                        // Validar stock disponible
                        if ($inventario) {
                            $stockDisponible = $inventario->stock;
                            $cantidadRequerida = $det['cantidad'];
                            
                            // Si no se permite vender sin stock y no hay suficiente stock
                            if (!$puedeVenderSinStock && $stockDisponible < $cantidadRequerida) {
                                DB::rollback();
                                return response()->json([
                                    'error' => "No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$stockDisponible}, Cantidad requerida: {$cantidadRequerida}"
                                ], 400);
                            }
                        } else {
                            // Si no existe inventario y no se permite vender sin stock
                            if (!$puedeVenderSinStock) {
                                DB::rollback();
                                return response()->json([
                                    'error' => "No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada"
                                ], 400);
                            }
                        }
                    }

                    // Verificar si el producto tiene inventario por lotes (y la empresa tiene lotes activos)
                    $producto = Producto::find($det['id_producto']);
                    $loteSeleccionado = null;
                    
                    if ($producto && $producto->inventario_por_lotes && $lotesActivo) {
                        $empresa = $empresa ?: \App\Models\Admin\Empresa::find($venta->id_empresa);
                        $metodologia = $empresa->getLotesMetodologia();
                        
                        // Si se especificó un lote manualmente, usarlo
                        if (isset($det['lote_id']) && $det['lote_id']) {
                            $loteSeleccionado = \App\Models\Inventario\Lote::find($det['lote_id']);
                        } else {
                            // Si la metodología es Manual, no seleccionar automáticamente
                            if ($metodologia === 'Manual') {
                                $loteSeleccionado = null;
                            } else {
                                // Seleccionar lote automáticamente según metodología
                                $lotesQuery = \App\Models\Inventario\Lote::where('id_producto', $det['id_producto'])
                                    ->where('id_bodega', $venta->id_bodega)
                                    ->where('stock', '>', 0);
                                
                                switch ($metodologia) {
                                    case 'FIFO':
                                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                                        break;
                                    case 'LIFO':
                                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'desc')->first();
                                        break;
                                    case 'FEFO':
                                        // Primero en vencer, primero en salir (query sin mutar para fallback FIFO)
                                        $loteSeleccionado = (clone $lotesQuery)
                                            ->whereNotNull('fecha_vencimiento')
                                            ->orderBy('fecha_vencimiento', 'asc')
                                            ->first();
                                        if (!$loteSeleccionado) {
                                            $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                                        }
                                        break;
                                    default:
                                        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();
                                }
                            }
                        }
                        
                        if ($loteSeleccionado) {
                            // Validar stock del lote
                            if ($loteSeleccionado->stock < $det['cantidad']) {
                                if (!$puedeVenderSinStock) {
                                    DB::rollback();
                                    return response()->json([
                                        'error' => "No hay suficiente stock en el lote {$loteSeleccionado->numero_lote}. Stock disponible: {$loteSeleccionado->stock}, Cantidad requerida: {$det['cantidad']}"
                                    ], 400);
                                }
                            }
                            
                            // Descontar del lote
                            $loteSeleccionado->stock -= $det['cantidad'];
                            $loteSeleccionado->save();
                            
                            // Guardar lote_id en el detalle
                            $detalle->lote_id = $loteSeleccionado->id;
                            $detalle->save();
                            
                            // También actualizar inventario tradicional para mantener consistencia
                            $inventario = Inventario::where('id_producto', $det['id_producto'])
                                ->where('id_bodega', $venta->id_bodega)->first();
                            if ($inventario) {
                                $inventario->stock -= $det['cantidad'];
                                $inventario->save();
                                $inventario->kardex($venta, $det['cantidad'], $det['precio']);
                            }
                        } else {
                            // No hay lotes disponibles o no se seleccionó (metodología Manual sin lote_id)
                            if ($metodologia === 'Manual' && !isset($det['lote_id'])) {
                                DB::rollback();
                                return response()->json([
                                    'error' => "Debe seleccionar un lote para el producto: {$producto->nombre} (Metodología Manual)"
                                ], 400);
                            } elseif (!$puedeVenderSinStock) {
                                DB::rollback();
                                return response()->json([
                                    'error' => "No hay lotes disponibles con stock para el producto: {$producto->nombre}"
                                ], 400);
                            }
                        }
                    } else {
                        // Restar inventario del producto principal (sin lotes)
                        $inventario = Inventario::where('id_producto', $det['id_producto'])
                            ->where('id_bodega', $venta->id_bodega)->first();
                        if ($inventario) {
                            $inventario->stock -= $det['cantidad'];
                            $inventario->save();
                            $inventario->kardex($venta, $det['cantidad'], $det['precio']);
                        }
                    }

                    // Inventario compuestos
                    if (isset($det['composiciones'])) {
                        foreach ($det['composiciones'] as $comp) {
                            // Validar que id_compuesto exista antes de procesar
                            if (!isset($comp['id_compuesto']) || empty($comp['id_compuesto'])) {
                                continue; // Saltar esta composición si no tiene id_compuesto
                            }
                            
                            $productoCompuesto = Producto::where('id', $comp['id_compuesto'])->first();
                            
                            // Validar stock de productos compuestos solo si no es servicio
                            if ($productoCompuesto && $productoCompuesto->tipo != 'Servicio') {
                                $cantidadCompRequerida = $det['cantidad'] * $comp['cantidad'];
                                
                                // Verificar si el producto compuesto tiene lotes activos
                                if ($productoCompuesto->inventario_por_lotes && $lotesActivo) {
                                    $metodologia = $empresa->getLotesMetodologia();
                                    
                                    // Buscar lote para el producto compuesto
                                    $loteCompuesto = null;
                                    if (isset($comp['lote_id']) && $comp['lote_id']) {
                                        $loteCompuesto = \App\Models\Inventario\Lote::find($comp['lote_id']);
                                    } else {
                                        if ($metodologia !== 'Manual') {
                                            $lotesQuery = \App\Models\Inventario\Lote::where('id_producto', $comp['id_compuesto'])
                                                ->where('id_bodega', $venta->id_bodega)
                                                ->where('stock', '>', 0);
                                            
                                            switch ($metodologia) {
                                                case 'FIFO':
                                                    $loteCompuesto = $lotesQuery->orderBy('created_at', 'asc')->first();
                                                    break;
                                                case 'LIFO':
                                                    $loteCompuesto = $lotesQuery->orderBy('created_at', 'desc')->first();
                                                    break;
                                                case 'FEFO':
                                                    $loteCompuesto = (clone $lotesQuery)
                                                        ->whereNotNull('fecha_vencimiento')
                                                        ->orderBy('fecha_vencimiento', 'asc')
                                                        ->first();
                                                    if (!$loteCompuesto) {
                                                        $loteCompuesto = $lotesQuery->orderBy('created_at', 'asc')->first();
                                                    }
                                                    break;
                                                default:
                                                    $loteCompuesto = $lotesQuery->orderBy('created_at', 'asc')->first();
                                            }
                                        }
                                    }
                                    
                                    if ($loteCompuesto) {
                                        // Validar stock del lote del producto compuesto
                                        if ($loteCompuesto->stock < $cantidadCompRequerida) {
                                            if (!$puedeVenderSinStock) {
                                                DB::rollback();
                                                return response()->json([
                                                    'error' => "No hay suficiente stock en el lote {$loteCompuesto->numero_lote} del producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$loteCompuesto->stock}, Cantidad requerida: {$cantidadCompRequerida}"
                                                ], 400);
                                            }
                                        }
                                        
                                        // Descontar del lote del producto compuesto
                                        $loteCompuesto->stock -= $cantidadCompRequerida;
                                        $loteCompuesto->save();
                                    } else {
                                        // Si no hay lote disponible, validar inventario tradicional
                                        $inventarioComp = Inventario::where('id_producto', $comp['id_compuesto'])
                                            ->where('id_bodega', $venta->id_bodega)->first();
                                        
                                        if ($inventarioComp) {
                                            $stockDisponibleComp = $inventarioComp->stock;
                                            
                                            if (!$puedeVenderSinStock && $stockDisponibleComp < $cantidadCompRequerida) {
                                                DB::rollback();
                                                return response()->json([
                                                    'error' => "No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}"
                                                ], 400);
                                            }
                                        } else {
                                            if (!$puedeVenderSinStock) {
                                                DB::rollback();
                                                return response()->json([
                                                    'error' => "No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada"
                                                ], 400);
                                            }
                                        }
                                    }
                                } else {
                                    // Producto compuesto sin lotes, validar inventario tradicional
                                    $inventarioComp = Inventario::where('id_producto', $comp['id_compuesto'])
                                        ->where('id_bodega', $venta->id_bodega)->first();
                                    
                                    if ($inventarioComp) {
                                        $stockDisponibleComp = $inventarioComp->stock;
                                        
                                        if (!$puedeVenderSinStock && $stockDisponibleComp < $cantidadCompRequerida) {
                                            DB::rollback();
                                            return response()->json([
                                                'error' => "No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}"
                                            ], 400);
                                        }
                                    } else {
                                        if (!$puedeVenderSinStock) {
                                            DB::rollback();
                                            return response()->json([
                                                'error' => "No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada"
                                            ], 400);
                                        }
                                    }
                                }
                                
                                // Restar inventario del producto compuesto (tradicional, para mantener consistencia)
                                $inventario = Inventario::where('id_producto', $comp['id_compuesto'])
                                    ->where('id_bodega', $venta->id_bodega)->first();

                                if ($inventario) {
                                    $inventario->stock -= $cantidadCompRequerida;
                                    $inventario->save();
                                    $inventario->kardex($venta, $cantidadCompRequerida);
                                }
                            }
                        }
                    }
                }
            }

            // Evento
            if ($request->id_evento) {
                $evento = Evento::findOrfail($request->id_evento);
                if ($venta->estado == 'Pagada') {
                    $evento->estado = 'Pagado';
                    $evento->estadoVerificarFrecuencia('Pagado');
                } else {
                    $evento->estado = 'Pendiente';
                    $evento->save();
                }
            }

            // Pagar si es proyecto
            if ($request->id_proyecto) {
                $proyecto = Proyecto::find($request->id_proyecto);
                if ($proyecto) {
                    $proyecto->estado = ($venta->estado == 'Pagada') ? 'Facturado' : 'Pendiente';
                    $proyecto->save();
                }
            }

            // Impuestos: al editar eliminar los actuales para no duplicar; luego crear/actualizar según request
            if ($request->impuestos) {
                if ($request->id) {
                    Impuesto::where('id_venta', $venta->id)->delete();
                }
                foreach ($request->impuestos as $impuesto) {
                    $venta_impuesto = new Impuesto();
                    $venta_impuesto->id_impuesto = $impuesto['id_impuesto'] ?? $impuesto['id'];
                    $venta_impuesto->monto = $impuesto['monto'] ?? 0;
                    $venta_impuesto->id_venta = $venta->id;
                    $venta_impuesto->save();
                }
            }

            // Pago en diferentes metodos
            if (isset($request['metodos_de_pago'])) {
                foreach ($request['metodos_de_pago'] as $metodo) {

                    $metodo_pago = new MetodoDePago;
                    $metodo_pago->id_venta = $venta->id;
                    $metodo_pago->nombre = $metodo['nombre'];
                    $metodo_pago->total = $metodo['total'];
                    $metodo_pago->save();
                }
            }

            // Procesar canje de puntos si se especifica
            if ($venta->puntos_canjeados > 0 && $venta->id_cliente && $venta->estado == 'Pagada') {
                try {
                    $consumoPuntosService = app(FidelizacionConsumoPuntosService::class);
                    $resultadoCanje = $consumoPuntosService->canjearPuntos(
                        $venta->id_cliente,
                        $venta->id_empresa,
                        $venta->puntos_canjeados,
                        "Canje aplicado en venta #{$venta->id}"
                    );
                    
                    if (!$resultadoCanje['success']) {
                        throw new \Exception('Error en el canje de puntos: ' . $resultadoCanje['error']);
                    }
                    
                    Log::info('Canje de puntos procesado exitosamente en venta', [
                        'venta_id' => $venta->id,
                        'cliente_id' => $venta->id_cliente,
                        'puntos_canjeados' => $venta->puntos_canjeados,
                        'descuento_aplicado' => $venta->descuento_puntos
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Error al procesar canje de puntos en venta', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // Revertir la transacción si falla el canje
                    throw $e;
                }
            }

            // Procesar puntos de fidelización (acumulación) si la venta está pagada
            if ($venta->estado == 'Pagada' && $venta->id_cliente) {
                try {
                    $consumoPuntosService = app(FidelizacionConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización en facturación', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // No se interrumpe la transacción por errores en puntos de acumulación
                }
            }

            DB::commit();
            $venta->refresh();
            return Response()->json($venta, 200);
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
        // Validar que usuarios "Ventas Limitado" no puedan crear ventas al crédito
        $user = auth()->user();
        if ($user->tipo === 'Ventas Limitado') {
            return response()->json([
                'error' => 'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.'
            ], 403);
        }

        $request->validate([
            'id'                => 'required',
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            'id_documento'      => 'required|max:255',
            'id_canal'          => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            'fecha_pago'        => 'required',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos a la venta',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
        ]);

        DB::beginTransaction();

        try {
            $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();
            if (round($venta->total, 2) > round($request->total, 2)) {
                // Crear consigna
                $consigna = new Venta();
                $consigna->fill($request->all());
                $consigna->estado = 'Consigna';
                $consigna->sub_total = $venta->sub_total - $request->sub_total;
                $consigna->total_costo  = $venta->total_costo  - $request->total_costo;
                $consigna->total = $venta->total - $request->total;
                $consigna->iva = $venta->iva - $request->iva;
                $consigna->save();

                foreach ($request->detalles as $detalle) {

                    $detalle_venta = $venta->detalles()->where('id', $detalle['id'])->first();
                    if ($detalle_venta) {
                        if ($detalle_venta->cantidad > $detalle['cantidad']) {
                            $detalle_consigna = new Detalle();
                            $detalle_consigna->id_producto = $detalle['id_producto'];
                            $detalle_consigna->precio = $detalle['precio'];
                            $detalle_consigna->cantidad = $detalle_venta->cantidad - $detalle['cantidad'];
                            $detalle_consigna->total = $detalle_consigna->precio * $detalle_consigna->cantidad;
                            $detalle_consigna->id_venta = $consigna->id;
                            $detalle_consigna->save();
                        }
                    }
                }

                //Guardar nuevos detalles
                $venta->detalles()->delete();

                foreach ($request->detalles as $detalle) {
                    if ($detalle['cantidad'] > 0) {
                        $det = new Detalle();
                        $det->id_producto = $detalle['id_producto'];
                        $det->cantidad = $detalle['cantidad'];
                        $det->precio = $detalle['precio'];
                        $det->total = $detalle['cantidad'] * $detalle['precio'];
                        $det->descuento = 0;
                        $det->id_venta = $venta->id;
                        $det->save();
                    }
                }

                $venta->total = $request->total;
                $venta->iva = $request->iva;
                $venta->sub_total = $request->sub_total;
            }


            $venta->fecha = $request->fecha;
            $venta->estado = 'Pagada';
            $venta->save();

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->id_cliente) {
                try {
                    $consumoPuntosService = app(FidelizacionConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización al pagar venta', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // No se interrumpe la transacción por errores en puntos
                }
            }

            DB::commit();
            return Response()->json($venta, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function pendientes()
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $caja    = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte   = $caja->corte;

        if ($corte) {
            if (!$corte->cierre)
                $corte->cierre = Carbon::now()->toDateTimeString();;

            $ventas  = $corte->ventas()->where('estado', 'En Proceso')
                ->orderBy('id', 'desc')
                ->paginate(5000);
        } else {
            $ventas  = Venta::where('estado', 'En Proceso')
                ->orderBy('id', 'desc')
                ->paginate(5000);
        }


        return Response()->json($ventas, 200);
    }

    public function vendedor()
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $ventas  = Venta::where('estado', 'En Proceso')
            ->where('id_usuario', $usuario->id)
            ->orderBy('id', 'desc')
            ->paginate(5000);

        return Response()->json($ventas, 200);
    }

    public function generarDoc($id)
    {

        $venta = Venta::where('id', $id)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        if ($documento->nombre == 'Ticket' || $documento->nombre == 'Recibo') {
            $documento = Documento::findOrfail($venta->id_documento);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            if (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            ) {
                $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $venta->pdf = false;
                return view(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
            }

            return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
        }

        if ($documento->nombre == 'Factura') {
            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            // Accesorios HN (716) o flag en custom_empresa
            if (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            ) {
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $venta->pdf = true;
                $pdf = app('dompdf.wrapper')->loadView(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
                $alto_base = 300;
                $alto_por_producto = 24;
                $total_lineas = max(1, $venta->detalles->count());
                $notaExtra = $documento->nota ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;
                $alto_total_pt = $alto_total_mm * 2.83465;
                $ancho_pt = 80 * 2.83465;
                $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);
                return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
            }

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total, 2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if (Auth::user()->id_empresa == 38) { //38
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.velo', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 212) { //212
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.fotopro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 62) { //62
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.hotel-eco', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 84) { //84
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.devetsa', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 75) { //75
                // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 104) { //104
                // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.factura-Coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 11) { //11
                // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            } elseif (Auth::user()->id_empresa == 12) { //12
                // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            } elseif (Auth::user()->id_empresa == 128) { //128
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.kiero-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283.46, 765.35]);
            } elseif (Auth::user()->id_empresa == 135) { //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            } elseif (Auth::user()->id_empresa == 136) { //136 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 609.4488]);
            } elseif (Auth::user()->id_empresa == 149) { //149 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 187) { //187  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 24) { //24  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Via-del-Mar', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 174) { //174  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Consultora-Raices', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 59) { //59  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Smartpyme', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } else {
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }


            return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
        }

        if ($documento->nombre == 'Crédito fiscal') {
            $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total, 2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
            $centavos = $formatter->toWords($n[1]);

            if (Auth::user()->id_empresa == 24) { //24
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.vetvia-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 212) { //212
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-FotoPro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 38) { //38
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.velo-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 62) { //62
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.hotel-eco-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 128) { //128
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.kiero-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283, 765]);
            } elseif (Auth::user()->id_empresa == 135) { //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            } elseif (Auth::user()->id_empresa == 136) { //136
                // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 297.64, 382.68]);
            } elseif (Auth::user()->id_empresa == 158) { //158
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Guaca-Mix-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 187) { //187  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'portrait');
            } elseif (Auth::user()->id_empresa == 84) { //84
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.devetsa-cff', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 59) { //59
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.smartpyme-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } else {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.credito', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }

            return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
        }
    }

    public function anularDoc()
    {

        return view('reportes.anulacion');
    }

    public function sinDevolucion()
    {

        $ventas = Venta::where('estado', '!=', 'Anulada')
            ->where(function ($query) {
                // Obtener la fecha límite (hace dos meses desde ahora)
                $fechaInicio = Carbon::now()->subMonths(2)->startOfMonth();
                $fechaFin = Carbon::now()->endOfMonth();

                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            })
            ->whereHas('documento', function ($q) {
                $q->whereIn('nombre', ['Factura', 'Crédito fiscal']);
            })
            ->whereDoesntHave('devoluciones')
            ->orderBy('fecha', 'DESC')
            ->get();

        return Response()->json($ventas, 200);
    }

    public function libroIva(Request $request)
    {
        $star = $request->inicio;
        $end = $request->fin;

        $ventas = Venta::with('cliente')->where('estado', '!=', 'Pendiente')
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->whereHas('documento', function ($q) use ($request) {
                    $q->where('nombre', $request->tipo_documento);
                });
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderBy('fecha', 'desc')->get();

        $ivas = collect();

        foreach ($ventas as $venta) {
            $ivas->push([
                'fecha'                 => $venta->fecha,
                'clase_documento'       => 1,
                'tipo_documento'        => '03',
                'num_resolucion'        => $venta->documento()->pluck('resolucion')->first(),
                'num_serie'             => $venta->documento()->pluck('numero_autorizacion')->first(),
                'num_documento'         => $venta->correlativo,
                'num_control_interno'   => $venta->correlativo,
                'nit_nrc'               => $venta->cliente()->pluck('nit')->first() ? $venta->cliente()->pluck('nit')->first() : $venta->cliente()->pluck('ncr')->first(),
                'nombre_cliente'        => $venta->nombre_cliente,
                'ventas_exentas'        => $venta->exenta,
                'ventas_no_sujetas'     => $venta->no_sujeta,
                'ventas_gravadas'       => $venta->sub_total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
                'debito_fiscal'         => $venta->iva,
                'ventas_cuenta_terceros' => 0,
                'debito_cuenta_terceros' => 0,
                'total'                 => $venta->total,
                'dui'                   => $venta->cliente()->pluck('dui')->first(),
                'num_anexto'            => 1,
            ]);
        }

        $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);
    }

    public function cxc(Request $request)
    {
        $paginate = $request->paginate ?? 10;
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';

        $cobros = Venta::where('estado', 'Pendiente')
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('id_vendedor', $request->id_vendedor)
                        ->orWhereHas('detalles', function ($q2) use ($request) {
                            $q2->where('id_vendedor', $request->id_vendedor);
                        });
                });
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                $buscador = '%' . $request->buscador . '%';
                return $query->where(function ($q) use ($buscador) {
                    $q->whereHas('cliente', function ($qCliente) use ($buscador) {
                        $qCliente->where('nombre', 'like', $buscador)
                            ->orWhere('nombre_empresa', 'like', $buscador)
                            ->orWhere('ncr', 'like', $buscador)
                            ->orWhere('nit', 'like', $buscador);
                    })
                        ->orWhere('correlativo', 'like', $buscador)
                        ->orWhere('estado', 'like', $buscador)
                        ->orWhere('observaciones', 'like', $buscador);
                });
            })
            ->where('cotizacion', 0)
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->paginate($paginate);

        return Response()->json($cobros, 200);
    }

    public function cxcExport(Request $request)
    {
        try {
            ini_set('memory_limit', '256M');
            set_time_limit(120);
            $export = new CuentasCobrarExport();
            $export->filter($request);
            return Excel::download($export, 'cuentas-por-cobrar.xlsx');
        } catch (\Throwable $e) {
            \Log::error('CXC Export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Error al generar el reporte: ' . $e->getMessage()], 500);
        }
    }

    public function cxcBuscar($txt)
    {
        $cobros = Venta::where('estado', 'Pendiente')
            ->whereHas('cliente', function ($query) use ($txt) {
                $query->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($cobros, 200);
    }

    public function historial(Request $request)
    {

        $ventas = Venta::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->fecha)->format('d-m-Y');
            });

        $movimientos = collect();

        foreach ($ventas as $venta) {
            $ventaTotal = $venta->sum('total');
            $costoTotal = $venta->sum('subcosto');
            $movimientos->push([
                'cantidad'      => $venta->count(),
                'fecha'         => $venta[0]->fecha,
                'total'         => $ventaTotal,
                'costo'         => $costoTotal,
                'utilidad'      => $ventaTotal - $costoTotal,
                'detalles'      => $venta
            ]);
        }

        return Response()->json($movimientos, 200);
    }

    public function export(Request $request)
    {
        $ventas = new VentasExport();
        $ventas->filter($request);

        $anio = VentasExport::anioDesdeRequest($request);
        $nombreArchivo = $anio !== null ? "ventas-{$anio}.xlsx" : 'ventas.xlsx';

        return Excel::download($ventas, $nombreArchivo);
    }

    public function exportDetalles(Request $request)
    {
        $ventas = new VentasDetallesExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas-detalles.xlsx');
    }


    /**
     * Genera el reporte diario de ventas por vendedor
     *
     * @param Request $request Solicitud HTTP
     * @return mixed Descarga del archivo Excel o ruta del archivo generado
     * @throws \Exception Si ocurre un error al generar el reporte
     */
    public function reporteDiario(Request $request)
    {
        try {
            $fecha = Carbon::today()->format('Y-m-d');
            $export = new VentasPorVendedorExport($fecha);


            if ($request->has('enviar_correo')) {

                $reportDirectory = storage_path("app/public/reportes");
                $filename = "ventas-por-vendedor-{$fecha}.xlsx";
                $path = "{$reportDirectory}/{$filename}";


                if (!file_exists($reportDirectory)) {
                    if (!mkdir($reportDirectory, 0755, true)) {
                        throw new \Exception("No se pudo crear el directorio para los reportes");
                    }
                }

                Excel::store($export, "public/reportes/{$filename}");

                if (!file_exists($path)) {
                    throw new \Exception("El archivo del reporte no se pudo generar correctamente");
                }

                return $path;
            } else {
                return Excel::download(
                    $export,
                    "ventas-por-vendedor-{$fecha}.xlsx"
                );
            }
        } catch (\Exception $e) {
            Log::error("Error al generar reporte diario: " . $e->getMessage());

            throw $e;
        }
    }

    public function acumuladoExport(Request $request)
    {

        //enviar id de la empresa en el request

        $user = JWTAuth::parseToken()->authenticate();
        $request->request->add(['id_empresa' => $user->id_empresa]);
        $ventas = new VentasAcumuladoExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'corte.xlsx');
    }

    public function porMarcasExport(Request $request)
    {
        $ventas = new VentasPorMarcasExport();
        $ventas->filter($request);
        return Excel::download($ventas, 'ventas-por-marcas.xlsx');
    }

    public function porUtilidadesExport(Request $request)
    {
        $ventas = new VentasPorUtilidadesExport();
        $ventas->filter($request);
        return Excel::download($ventas, 'ventas-por-utilidades.xlsx');
    }

    public function cobrosPorVendedorExport(Request $request)
    {
        $cobros = new CobrosPorVendedorExport();
        $cobros->filter($request);
        return Excel::download($cobros, 'cobros-por-vendedor.xlsx');
    }

    public function enviarReporteDiario()
    {
        try {
            $fecha = Carbon::today()->format('Y-m-d');
            $export = new VentasPorVendedorExport($fecha);
            $filename = "ventas-por-vendedor-{$fecha}.xlsx";

            $relativePath = "reportes/{$filename}";

            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            Storage::disk('public')->put($relativePath, '');


            Excel::store($export, $relativePath, 'public');


            $filePath = public_path('img/' . $relativePath);

            if (!file_exists($filePath)) {

                Log::error("Archivo no encontrado en: {$filePath}");

                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            $ventasDelDia = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->count();

            $totalVentas = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->sum('total');

            $vendedoresConVentas = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->distinct('id_vendedor')
                ->where('estado', '!=', 'Anulada')
                ->count('id_vendedor');

            $destinatarios = [
                'cristian.g@smartpyme.sv',
            ];

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath)
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            return response()->json(['message' => 'Reporte enviado correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte diario: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function enviarReporteProgramado($configuracion, $empresa, $fechaInicio, $fechaFin)
    {
        try {
            // $fecha = Carbon::today()->format('Y-m-d');
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion->sucursales);
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $empresa->id,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
            } elseif ($configuracion->tipo_reporte === 'cobros-por-vendedor') {
                $request = new Request([
                    'id_empresa' => $empresa->id,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
            } elseif ($configuracion->tipo_reporte === 'ventas-compras-por-marca-proveedor') {
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $empresa->id,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-totales') {
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $empresa->id,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasExport($requestVentasTotales);
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-por-producto') {
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $empresa->id,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
            }
            $filename = "{$configuracion->tipo_reporte}-{$fechaInicio}.xlsx";


            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($empresa->id);


            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');

            $filePath = public_path('img/' . $relativePath);

            if (!file_exists($filePath)) {
                Log::error("Archivo no encontrado en: {$filePath}");
                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            } else {
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-utilidades' => 'Reporte de Ventas por Utilidades ' . $fechaInicio . ' al ' . $fechaFin,
                'cobros-por-vendedor' => 'Reporte de Cobros por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-compras-por-marca-proveedor' => 'Reporte de Ventas y Compras por Marca y Proveedor ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-totales' => 'Reporte de Detalle de Ventas Totales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-por-producto' => 'Reporte de Detalle de Ventas por Producto ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;



            $datos = [
                'fecha' => $fechaInicio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto,
                'automatico' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            $destinatarios = $configuracion->destinatarios;

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            // Registrar que se envió el reporte
            Log::info("Reporte enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);


            unlink($filePath);


            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte programado: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function enviarReporteProgramadoTest($configuracion, $destinatarios, $fechaInicio, $fechaFin)
    {
        try {
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                $filename = "ventas-por-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "ventas-por-categoria-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "estado-financiero-consolidado-sucursales-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                $filename = "detalle-ventas-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "inventario-por-sucursal-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                $filename = "ventas-por-utilidades-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'cobros-por-vendedor') {
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
                $filename = "cobros-por-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-compras-por-marca-proveedor') {
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
                $filename = "ventas-compras-por-marca-proveedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-totales') {
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasExport($requestVentasTotales);
                $filename = "detalle-ventas-totales-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-por-producto') {
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
                $filename = "detalle-ventas-por-producto-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            }

            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($configuracion->id_empresa);

            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }


            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');


            $filePath = public_path('img/' . $relativePath);


            if (!file_exists($filePath)) {

                Log::error("Archivo no encontrado en: {$filePath}");

                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            // Obtener estadísticas para incluir en el correo
            if($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            }else{
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-utilidades' => 'Reporte de Ventas por Utilidades ' . $fechaInicio . ' al ' . $fechaFin,
                'cobros-por-vendedor' => 'Reporte de Cobros por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-compras-por-marca-proveedor' => 'Reporte de Ventas y Compras por Marca y Proveedor ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-totales' => 'Reporte de Detalle de Ventas Totales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-por-producto' => 'Reporte de Detalle de Ventas por Producto ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto ?: "Reporte de Prueba: " . $configuracion->tipo_reporte . " - " . Carbon::today()->format('d/m/Y'),
                'esPrueba' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            Log::info("Reporte de prueba enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);

            unlink($filePath);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte de prueba: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function exportarReporteProgramado($configuracion, $fechaInicio, $fechaFin)
    {
        //try {
        // Implementar la lógica de exportación

        Log::info("Exportando reporte: {$configuracion->tipo_reporte}", [
            'configuracion_id' => $configuracion->id,
            'fecha' => $fechaInicio . ' al ' . $fechaFin,
            'configuracion' => $configuracion
        ]);

        // if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
        //     $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
        //     $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
        // } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
        //     $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } else {
        //     return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        // }

        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'ventas-por-categoria-vendedor':
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'estado-financiero-consolidado-sucursales':
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'detalle-ventas-vendedor':
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                break;
            case 'inventario-por-sucursal':
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'ventas-por-utilidades':
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                break;
            case 'cobros-por-vendedor':
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
                break;
            case 'ventas-compras-por-marca-proveedor':
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
                break;
            case 'detalle-ventas-totales':
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasExport($requestVentasTotales);
                break;
            case 'detalle-ventas-por-producto':
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
                break;
            default:
                return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        }

        return Excel::download($export, $configuracion->tipo_reporte . '-' . $fechaInicio . '-' . $fechaFin . '.xlsx');
        // } catch (\Exception $e) {
        //     Log::error('Error al exportar reporte programado: ' . $e->getMessage(), [
        //         'configuracion_id' => $configuracion->id ?? null,
        //         'tipo_reporte' => $configuracion->tipo_reporte ?? null
        //     ]);
        //     throw $e;
        // }
    }


    public function getNumerosIdentificacion(){
        $numsIds = Venta::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();

        return Response()->json($numsIds, 200);
     }

}

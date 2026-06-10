<?php

namespace App\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Proyecto;
use App\Models\Eventos\Evento;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Paquete;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCompuesto;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\MetodoDePago;
use App\Models\Ventas\Venta;
use App\Services\FidelizacionCliente\ConsumoPuntosService as FidelizacionConsumoPuntosService;
use App\Services\Inventario\ConversionInventarioService;
use App\Services\Restaurante\PedidoCanalInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionService
{
    /**
     * Reglas de negocio previas a validar el request (crédito, permisos).
     *
     * @throws FacturacionException
     */
    public function assertReglasNegocio(User $user, Request $request): void
    {
        if ($user->tipo === 'Ventas Limitado' && $request->credito == 1) {
            throw new FacturacionException(
                'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.',
                403
            );
        }

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
                    throw new FacturacionException(
                        'El cliente ha excedido su límite de crédito. Saldo pendiente: $'
                        . number_format($saldoPendiente, 2)
                        . '. Total con esta venta: $'
                        . number_format($nuevoSaldo, 2)
                        . '. Límite permitido: $'
                        . number_format($cliente->limite_credito, 2)
                        . '.',
                        422
                    );
                }
            }
        }
    }

    /**
     * Crea o actualiza una venta con inventario, lotes, impuestos y fidelización.
     *
     * @throws FacturacionException
     */
    public function procesar(User $user, Request $request): Venta
    {
        DB::beginTransaction();

        try {
                // Obtener la empresa para verificar configuración de vender sin stock y lotes
                $empresa = Empresa::findOrFail($user->id_empresa);
                $puedeVenderSinStock = $empresa->vender_sin_stock == 1;
                $lotesActivo = $empresa->isLotesActivo();
    
                $saltarActualizarInventario = false;
                if (!$request->id && $request->filled('id_pedido_canal')) {
                    $pedidoCanalFactura = PedidoRestaurante::where('id', $request->id_pedido_canal)
                        ->where('id_empresa', $user->id_empresa)
                        ->where('estado', 'pendiente_facturar')
                        ->whereNull('id_venta')
                        ->with('detalles')
                        ->first();
                    if (!$pedidoCanalFactura) {
                        throw new \App\Exceptions\FacturacionException('Pedido de canal no válido para facturar o ya fue vinculado.', 422);
                    }
                    if (!$pedidoCanalFactura->id_bodega) {
                        throw new \App\Exceptions\FacturacionException('El pedido no tiene bodega de inventario. Confirme el pedido con una bodega o anule y vuelva a crear.', 422);
                    }
                    if ((int) $request->id_bodega !== (int) $pedidoCanalFactura->id_bodega) {
                        throw new \App\Exceptions\FacturacionException('La bodega de la factura debe coincidir con la bodega del pedido (el inventario ya se descontó al confirmar).', 422);
                    }
                    if (!PedidoCanalInventarioService::ventaCoincideConPedido($pedidoCanalFactura, $request->detalles ?? [])) {
                        throw new \App\Exceptions\FacturacionException('Las cantidades por producto deben coincidir con el pedido de canal; el stock ya se comprometió al confirmar.', 422);
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
    
                    // ── Cálculos de Costos con Presentaciones ──
                    $factorDet = 1;
                    if (!empty($det['id_presentacion'])) {
                        $presentacionDet = \App\Models\Inventario\ProductoPresentacion::find($det['id_presentacion']);
                        if ($presentacionDet) {
                            $factorDet = (float) $presentacionDet->factor_conversion;
                        }
                    }
    
                    if (isset($det['costo'])) {
                        // $det['costo'] trae el costo del producto base
                        $costoProporcional = round((float) $det['costo'] * $factorDet, 6);
                        $det['costo'] = $costoProporcional;
                        
                        // Aseguramos que total_costo sea exacto
                        $det['total_costo'] = round($costoProporcional * (float) $det['cantidad'], 6);
                    }
    
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
                            $factorComp = 1;
                            if (isset($item['id_presentacion']) && $item['id_presentacion']) {
                                $presentacionComp = \App\Models\Inventario\ProductoPresentacion::find($item['id_presentacion']);
                                if ($presentacionComp) {
                                    $factorComp = $presentacionComp->factor_conversion;
                                }
                            }
    
                            $cd = new DetalleCompuesto;
                            $cd->id_producto = $item['id_compuesto'];
                            $cd->cantidad   = $item['cantidad'] * $factorComp;
                            $cd->id_detalle = $detalle->id;
                            $cd->save();
                        }
                    }
    
    
                    // Actualizar inventario
                    if ($request->cotizacion == 0 && !$saltarActualizarInventario) {
    
                        // Obtener el producto para verificar si es servicio
                        $producto = Producto::where('id', $det['id_producto'])->first();
    
                        // ── Resolución del factor de conversión (presentaciones) ──────────────
                        $factorDet = 1;
                        if (!empty($det['id_presentacion'])) {
                            $presentacionDet = \App\Models\Inventario\ProductoPresentacion::find($det['id_presentacion']);
                            if ($presentacionDet) {
                                $factorDet = (float) $presentacionDet->factor_conversion;
                            }
                        }
                        // Cantidad en unidades base que se descuenta del inventario y del Kardex
                        $cantidadBaseDet = ConversionInventarioService::calcularCantidadBase(
                            $det['cantidad'],
                            $factorDet
                        );
                        
                        // Validar stock solo si no es servicio y si la empresa no permite vender sin stock
                        if ($producto && $producto->tipo != 'Servicio') {
                            $inventario = Inventario::where('id_producto', $det['id_producto'])
                                ->where('id_bodega', $venta->id_bodega)->first();
    
                            // Validar stock disponible
                            if ($inventario) {
                                $stockDisponible = $inventario->stock;
                                $cantidadRequerida = $cantidadBaseDet;
                                
                                // Si no se permite vender sin stock y no hay suficiente stock
                                if (!$puedeVenderSinStock && $stockDisponible < $cantidadRequerida) {
                                    DB::rollback();
                                    throw new \App\Exceptions\FacturacionException("No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$stockDisponible}, Cantidad requerida: {$cantidadRequerida}", 400);
                                }
                            } else {
                                // Si no existe inventario y no se permite vender sin stock
                                if (!$puedeVenderSinStock) {
                                    DB::rollback();
                                    throw new \App\Exceptions\FacturacionException("No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada", 400);
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
                                if ($loteSeleccionado->stock < $cantidadBaseDet) {
                                    if (!$puedeVenderSinStock) {
                                        DB::rollback();
                                        throw new \App\Exceptions\FacturacionException("No hay suficiente stock en el lote {$loteSeleccionado->numero_lote}. Stock disponible: {$loteSeleccionado->stock}, Cantidad requerida: {$cantidadBaseDet}", 400);
                                    }
                                }
    
                                // Descontar del lote
                                $loteSeleccionado->stock -= $cantidadBaseDet;
                                $loteSeleccionado->save();
    
                                // Guardar lote_id en el detalle
                                $detalle->lote_id = $loteSeleccionado->id;
                                $detalle->save();
    
                                // También actualizar inventario tradicional para mantener consistencia
                                $inventario = Inventario::where('id_producto', $det['id_producto'])
                                    ->where('id_bodega', $venta->id_bodega)->first();
                                if ($inventario) {
                                    $inventario->stock -= $cantidadBaseDet;
                                    $inventario->save();
                                    $inventario->kardex($venta, $cantidadBaseDet, $det['precio']);
                                }
                            } else {
                                // No hay lotes disponibles o no se seleccionó (metodología Manual sin lote_id)
                                if ($metodologia === 'Manual' && !isset($det['lote_id'])) {
                                    DB::rollback();
                                    throw new \App\Exceptions\FacturacionException("Debe seleccionar un lote para el producto: {$producto->nombre} (Metodología Manual)", 400);
                                } elseif (!$puedeVenderSinStock) {
                                    DB::rollback();
                                    throw new \App\Exceptions\FacturacionException("No hay lotes disponibles con stock para el producto: {$producto->nombre}", 400);
                                }
                            }
                        } else {
                            // Restar inventario del producto principal (sin lotes)
                            $inventario = Inventario::where('id_producto', $det['id_producto'])
                                ->where('id_bodega', $venta->id_bodega)->first();
                            if ($inventario) {
                                $inventario->stock -= $cantidadBaseDet;
                                $inventario->save();
                                $inventario->kardex($venta, $cantidadBaseDet, $det['precio']);
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
                                    $factorComp = 1;
                                    if (isset($comp['id_presentacion']) && $comp['id_presentacion']) {
                                        $presentacionComp = \App\Models\Inventario\ProductoPresentacion::find($comp['id_presentacion']);
                                        if ($presentacionComp) {
                                            $factorComp = $presentacionComp->factor_conversion;
                                        }
                                    }
                                    $cantidadCompRequerida = $det['cantidad'] * $comp['cantidad'] * $factorComp;
                                    
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
                                                    throw new \App\Exceptions\FacturacionException("No hay suficiente stock en el lote {$loteCompuesto->numero_lote} del producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$loteCompuesto->stock}, Cantidad requerida: {$cantidadCompRequerida}", 400);
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
                                                    throw new \App\Exceptions\FacturacionException("No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}", 400);
                                                }
                                            } else {
                                                if (!$puedeVenderSinStock) {
                                                    DB::rollback();
                                                    throw new \App\Exceptions\FacturacionException("No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada", 400);
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
                                                throw new \App\Exceptions\FacturacionException("No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}", 400);
                                            }
                                        } else {
                                            if (!$puedeVenderSinStock) {
                                                DB::rollback();
                                                throw new \App\Exceptions\FacturacionException("No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada", 400);
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
                $venta->load(['detalles', 'cliente', 'impuestos']);

                return $venta;

        } catch (FacturacionException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new FacturacionException($e->getMessage(), 400);
        }
    }
}

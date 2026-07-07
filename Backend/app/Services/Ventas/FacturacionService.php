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
use App\Services\Inventario\LoteAsignacionService;
use App\Services\Inventario\StockDisponibleService;
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

                        $producto = Producto::where('id', $det['id_producto'])->first();

                        $factorDet = 1;
                        if (!empty($det['id_presentacion'])) {
                            $presentacionDet = \App\Models\Inventario\ProductoPresentacion::find($det['id_presentacion']);
                            if ($presentacionDet) {
                                $factorDet = (float) $presentacionDet->factor_conversion;
                            }
                        }
                        $cantidadBaseDet = ConversionInventarioService::calcularCantidadBase(
                            $det['cantidad'],
                            $factorDet
                        );

                        if ($producto && $producto->tipo != 'Servicio' && !$puedeVenderSinStock) {
                            $loteIdDet = ($producto->inventario_por_lotes && $lotesActivo)
                                ? null
                                : (!empty($det['lote_id']) ? (int) $det['lote_id'] : null);
                            $stockDisponible = StockDisponibleService::obtenerParaVenta(
                                $producto,
                                (int) $venta->id_bodega,
                                $empresa,
                                $loteIdDet
                            );

                            if ($stockDisponible === null) {
                                throw new FacturacionException(
                                    "No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada",
                                    400
                                );
                            }

                            if ($stockDisponible < $cantidadBaseDet) {
                                throw new FacturacionException(
                                    "No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$stockDisponible}, Cantidad requerida: {$cantidadBaseDet}",
                                    400
                                );
                            }
                        }

                        $producto = Producto::find($det['id_producto']);

                        if ($producto && $producto->inventario_por_lotes && $lotesActivo) {
                            $metodologia = $empresa->getLotesMetodologia();
                            $lotePreferido = !empty($det['lote_id']) ? (int) $det['lote_id'] : null;
                            $asignacionManual = !empty($det['lotes_asignados']) ? $det['lotes_asignados'] : null;

                            try {
                                $asignaciones = LoteAsignacionService::distribuir(
                                    (int) $det['id_producto'],
                                    (int) $venta->id_bodega,
                                    $cantidadBaseDet,
                                    $metodologia,
                                    $lotePreferido,
                                    $asignacionManual
                                );
                            } catch (\RuntimeException $e) {
                                if (!$puedeVenderSinStock) {
                                    throw new FacturacionException(
                                        $metodologia === 'Manual' && !$lotePreferido && !$asignacionManual
                                            ? "Debe seleccionar un lote para el producto: {$producto->nombre} (Metodología Manual)"
                                            : $e->getMessage(),
                                        400
                                    );
                                }
                                $asignaciones = [];
                            }

                            $inventario = Inventario::where('id_producto', $det['id_producto'])
                                ->where('id_bodega', $venta->id_bodega)->first();

                            if (!empty($asignaciones) && $inventario) {
                                LoteAsignacionService::aplicarSalida(
                                    $asignaciones,
                                    $detalle,
                                    $venta,
                                    $inventario,
                                    (float) $det['precio']
                                );
                            } elseif (!$puedeVenderSinStock && empty($asignaciones)) {
                                throw new FacturacionException(
                                    "No hay lotes disponibles con stock para el producto: {$producto->nombre}",
                                    400
                                );
                            }
                        } else {
                            $inventario = Inventario::where('id_producto', $det['id_producto'])
                                ->where('id_bodega', $venta->id_bodega)->first();
                            if ($inventario) {
                                $inventario->stock -= $cantidadBaseDet;
                                $inventario->save();
                                $inventario->kardex($venta, $cantidadBaseDet, $det['precio']);
                            }
                        }

                        if (isset($det['composiciones'])) {
                            foreach ($det['composiciones'] as $comp) {
                                if (!isset($comp['id_compuesto']) || empty($comp['id_compuesto'])) {
                                    continue;
                                }

                                $productoCompuesto = Producto::where('id', $comp['id_compuesto'])->first();

                                if ($productoCompuesto && $productoCompuesto->tipo != 'Servicio') {
                                    $factorComp = 1;
                                    if (isset($comp['id_presentacion']) && $comp['id_presentacion']) {
                                        $presentacionComp = \App\Models\Inventario\ProductoPresentacion::find($comp['id_presentacion']);
                                        if ($presentacionComp) {
                                            $factorComp = $presentacionComp->factor_conversion;
                                        }
                                    }
                                    $cantidadCompRequerida = $det['cantidad'] * $comp['cantidad'] * $factorComp;

                                    if ($productoCompuesto->inventario_por_lotes && $lotesActivo) {
                                        $metodologia = $empresa->getLotesMetodologia();
                                        $lotePreferidoComp = !empty($comp['lote_id']) ? (int) $comp['lote_id'] : null;

                                        try {
                                            $asignacionesComp = LoteAsignacionService::distribuir(
                                                (int) $comp['id_compuesto'],
                                                (int) $venta->id_bodega,
                                                $cantidadCompRequerida,
                                                $metodologia,
                                                $lotePreferidoComp,
                                                !empty($comp['lotes_asignados']) ? $comp['lotes_asignados'] : null
                                            );
                                        } catch (\RuntimeException $e) {
                                            if (!$puedeVenderSinStock) {
                                                throw new FacturacionException(
                                                    "Producto compuesto {$productoCompuesto->nombre}: {$e->getMessage()}",
                                                    400
                                                );
                                            }
                                            $asignacionesComp = [];
                                        }

                                        $inventarioComp = Inventario::where('id_producto', $comp['id_compuesto'])
                                            ->where('id_bodega', $venta->id_bodega)->first();

                                        if (!empty($asignacionesComp) && $inventarioComp) {
                                            LoteAsignacionService::aplicarSalidaSinDetalle(
                                                $asignacionesComp,
                                                $venta,
                                                $inventarioComp
                                            );
                                        } elseif (!$puedeVenderSinStock && empty($asignacionesComp)) {
                                            throw new FacturacionException(
                                                "No hay lotes disponibles con stock para el producto compuesto: {$productoCompuesto->nombre}",
                                                400
                                            );
                                        }
                                    } else {
                                        if (!$puedeVenderSinStock) {
                                            $stockDisponibleComp = StockDisponibleService::obtenerParaVenta(
                                                $productoCompuesto,
                                                (int) $venta->id_bodega,
                                                $empresa
                                            );

                                            if ($stockDisponibleComp === null) {
                                                throw new FacturacionException(
                                                    "No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada",
                                                    400
                                                );
                                            }

                                            if ($stockDisponibleComp < $cantidadCompRequerida) {
                                                throw new FacturacionException(
                                                    "No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}",
                                                    400
                                                );
                                            }
                                        }

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

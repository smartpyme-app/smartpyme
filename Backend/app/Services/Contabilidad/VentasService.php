<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Admin\FormaDePago;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Exception;

class VentasService
{
    public function crearPartida($venta)
    {
        // Validar que la venta existe
        if (!$venta || !isset($venta->id)) {
            throw new Exception('La venta proporcionada no es válida', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Validar que la venta tiene los datos necesarios
        if (!$venta->fecha) {
            throw new Exception('La venta no tiene fecha asignada', 400);
        }

        if (!$venta->total || $venta->total <= 0) {
            throw new Exception('La venta no tiene un monto válido', 400);
        }

        if (!$venta->id_sucursal) {
            throw new Exception('La venta no tiene sucursal asignada', 400);
        }

        Partida::assertNoExisteParaOrigen('Venta', $venta->id, 'Ya existen partidas contables generadas para esta venta.');

        DB::beginTransaction();

        try {
            // === PRIMERA PARTIDA: INGRESOS POR VENTAS ===
            $partida_ingresos = Partida::create([
                'fecha' => $venta->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas. ' . ($venta->nombre_documento ?? 'Documento') . ' #' . ($venta->correlativo ?? 'Sin correlativo'),
                'estado' => 'Pendiente',
                'referencia' => 'Venta',
                'id_referencia' => $venta->id,
                'id_usuario' => $venta->id_usuario,
                'id_empresa' => $venta->id_empresa,
            ]);

            // Debe - Determinar cuenta según estado de la venta
            if ($venta->estado == 'Pendiente') {
                // Venta al crédito - CxC
                if (!$configuracion->id_cuenta_cxc) {
                    throw new Exception('No se ha configurado la cuenta de cuentas por cobrar', 400);
                }
                $cuenta_debe = Cuenta::find($configuracion->id_cuenta_cxc);
                if (!$cuenta_debe) {
                    throw new Exception('No se encontró la cuenta contable de cuentas por cobrar', 400);
                }
            } else {
                // Venta al contado - según forma de pago
                if (!$venta->forma_pago) {
                    throw new Exception('La venta no tiene forma de pago asignada', 400);
                }

                $formapago = FormaDePago::with('banco')->where('nombre', $venta->forma_pago)->first();
                if (!$formapago) {
                    throw new Exception('No se encontró la forma de pago: ' . $venta->forma_pago, 400);
                }

                if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
                    throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada, para configurarla puede ir al menú de la aplicación, en Finanzas > Métodos de pago', 400);
                }

                $cuenta_debe = Cuenta::find($formapago->banco->id_cuenta_contable);
                if (!$cuenta_debe) {
                    throw new Exception('No se encontró la cuenta contable del banco asociado a la forma de pago, para configurarla puede ir al menú de la aplicación, en Finanzas > Bancos, seleccionar el tab de Cuentas y agregar la cuenta contable al banco.', 400);
                }
            }

            Detalle::create([
                'id_cuenta' => $cuenta_debe->id,
                'codigo' => $cuenta_debe->codigo,
                'nombre_cuenta' => $cuenta_debe->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => $venta->total,
                'haber' => NULL,
                'saldo' => 0,
                'id_partida' => $partida_ingresos->id
            ]);

            // Haber - Procesar productos de la venta
            $productos_venta = DetalleVenta::where('id_venta', $venta->id)->get();

            if ($productos_venta->isEmpty()) {
                throw new Exception('La venta no tiene productos asociados', 400);
            }

            $total_productos_procesados = 0;

            foreach ($productos_venta as $producto) {
                if (!$producto->producto) {
                    throw new Exception('Producto no encontrado en el detalle de venta', 400);
                }

                $id_categoria = $producto->producto->id_categoria;

                if ($id_categoria) {
                    $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)
                                                                ->where('id_sucursal', $venta->id_sucursal)
                                                                ->first();

                    if (!$cuenta_categoria_sucursal) {
                        throw new Exception('No se encontró configuración de cuentas para la categoría del producto en esta sucursal', 400);
                    }

                    if (!$cuenta_categoria_sucursal->id_cuenta_contable_ingresos) {
                        throw new Exception('La categoría del producto no tiene cuenta de ingresos configurada', 400);
                    }

                    $cuenta = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_ingresos);
                    if (!$cuenta) {
                        throw new Exception('No se encontró la cuenta contable de ingresos para la categoría', 400);
                    }

                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Ingresos por ventas',
                        'debe' => NULL,
                        'haber' => $producto->total,
                        'saldo' => 0,
                        'id_partida' => $partida_ingresos->id
                    ]);

                    $total_productos_procesados += $producto->total;
                } else {
                    // Producto sin categoría - usar cuenta general
                    if (!$configuracion->id_cuenta_ventas) {
                        throw new Exception('No se ha configurado la cuenta general de ventas', 400);
                    }

                    $cuenta = Cuenta::find($configuracion->id_cuenta_ventas);
                    if (!$cuenta) {
                        throw new Exception('No se encontró la cuenta contable general de ventas', 400);
                    }

                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Ingresos por ventas',
                        'debe' => NULL,
                        'haber' => $venta->sub_total,
                        'saldo' => 0,
                        'id_partida' => $partida_ingresos->id
                    ]);

                    $total_productos_procesados += $venta->sub_total;
                    break; // Solo procesar una vez para productos sin categoría
                }
            }

            // IVA
            if ($venta->iva > 0) {
                if (!$configuracion->id_cuenta_iva_ventas) {
                    throw new Exception('No se ha configurado la cuenta de IVA ventas', 400);
                }

                $cuenta = Cuenta::find($configuracion->id_cuenta_iva_ventas);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de IVA ventas', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => NULL,
                    'haber' => $venta->iva,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id
                ]);
            }

            // IVA Retenido
            if ($venta->iva_retenido > 0) {
                if (!$configuracion->id_cuenta_iva_retenido_ventas) {
                    throw new Exception('No se ha configurado la cuenta de IVA retenido ventas', 400);
                }

                $cuenta = Cuenta::find($configuracion->id_cuenta_iva_retenido_ventas);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de IVA retenido ventas', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => $venta->iva_retenido,
                    'haber' => NULL,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id
                ]);
            }

            // Propina
            if ($venta->propina > 0) {
                if (!$configuracion->id_cuenta_propina_ventas) {
                    throw new Exception('No se ha configurado la cuenta de propina ventas', 400);
                }
                
                $cuenta = Cuenta::find($configuracion->id_cuenta_propina_ventas);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de propina ventas', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => NULL,
                    'haber' => $venta->propina,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id
                ]);
            }

            // === SEGUNDA PARTIDA: COSTO DE VENTAS ===
            $partida_costos = Partida::create([
                'fecha' => $venta->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Costo de ventas. ' . ($venta->nombre_documento ?? 'Documento') . ' #' . ($venta->correlativo ?? 'Sin correlativo'),
                'estado' => 'Pendiente',
                'referencia' => 'Venta',
                'id_referencia' => $venta->id,
                'id_usuario' => $venta->id_usuario,
                'id_empresa' => $venta->id_empresa,
            ]);

            $total_costo_procesado = 0;

            foreach ($productos_venta as $producto) {
                $id_categoria = $producto->producto->id_categoria;

                if ($id_categoria) {
                    $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)
                                                                ->where('id_sucursal', $venta->id_sucursal)
                                                                ->first();

                    if (!$cuenta_categoria_sucursal) {
                        throw new Exception('No se encontró configuración de cuentas para la categoría del producto en esta sucursal', 400);
                    }

                    // Cuenta de costo
                    if (!$cuenta_categoria_sucursal->id_cuenta_contable_costo) {
                        throw new Exception('La categoría del producto no tiene cuenta de costo configurada', 400);
                    }

                    $cuenta_costos = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_costo);
                    if (!$cuenta_costos) {
                        throw new Exception('No se encontró la cuenta contable de costo para la categoría', 400);
                    }

                    // Cuenta de inventario
                    if (!$cuenta_categoria_sucursal->id_cuenta_contable_inventario) {
                        throw new Exception('La categoría del producto no tiene cuenta de inventario configurada', 400);
                    }

                    $cuenta_inventarios = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_inventario);
                    if (!$cuenta_inventarios) {
                        throw new Exception('No se encontró la cuenta contable de inventario para la categoría', 400);
                    }

                    $costo_total_producto = ($producto->costo * $producto->cantidad);

                    // Debe - Costo de venta
                    Detalle::create([
                        'id_cuenta' => $cuenta_costos->id,
                        'codigo' => $cuenta_costos->codigo,
                        'nombre_cuenta' => $cuenta_costos->nombre,
                        'concepto' => 'Costo de ventas',
                        'debe' => $costo_total_producto,
                        'haber' => NULL,
                        'saldo' => 0,
                        'id_partida' => $partida_costos->id
                    ]);

                    // Haber - Inventario
                    Detalle::create([
                        'id_cuenta' => $cuenta_inventarios->id,
                        'codigo' => $cuenta_inventarios->codigo,
                        'nombre_cuenta' => $cuenta_inventarios->nombre,
                        'concepto' => 'Inventarios',
                        'debe' => NULL,
                        'haber' => $costo_total_producto,
                        'saldo' => 0,
                        'id_partida' => $partida_costos->id
                    ]);

                    $total_costo_procesado += $costo_total_producto;
                } else {
                    // Producto sin categoría - usar cuentas generales
                    if (!$configuracion->id_cuenta_costo_venta) {
                        throw new Exception('No se ha configurado la cuenta general de costo de venta', 400);
                    }

                    if (!$configuracion->id_cuenta_inventario) {
                        throw new Exception('No se ha configurado la cuenta general de inventario', 400);
                    }

                    $cuenta_costo = Cuenta::find($configuracion->id_cuenta_costo_venta);
                    if (!$cuenta_costo) {
                        throw new Exception('No se encontró la cuenta contable general de costo de venta', 400);
                    }

                    $cuenta_inventario = Cuenta::find($configuracion->id_cuenta_inventario);
                    if (!$cuenta_inventario) {
                        throw new Exception('No se encontró la cuenta contable general de inventario', 400);
                    }

                    // Debe - Costo de venta
                    Detalle::create([
                        'id_cuenta' => $cuenta_costo->id,
                        'codigo' => $cuenta_costo->codigo,
                        'nombre_cuenta' => $cuenta_costo->nombre,
                        'concepto' => 'Costo de ventas',
                        'debe' => $venta->total_costo,
                        'haber' => NULL,
                        'saldo' => 0,
                        'id_partida' => $partida_costos->id
                    ]);

                    // Haber - Inventario
                    Detalle::create([
                        'id_cuenta' => $cuenta_inventario->id,
                        'codigo' => $cuenta_inventario->codigo,
                        'nombre_cuenta' => $cuenta_inventario->nombre,
                        'concepto' => 'Inventarios',
                        'debe' => NULL,
                        'haber' => $venta->total_costo,
                        'saldo' => 0,
                        'id_partida' => $partida_costos->id
                    ]);

                    $total_costo_procesado += $venta->total_costo;
                    break; // Solo procesar una vez para productos sin categoría
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partidas contables de venta creadas exitosamente',
                'partida_ingresos_id' => $partida_ingresos->id,
                'partida_costos_id' => $partida_costos->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear las partidas de venta: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear las partidas de venta: ' . $e->getMessage(), 500);
        }
    }
}

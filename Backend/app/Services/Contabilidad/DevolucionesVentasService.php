<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle as DetalleDevolucion;
use App\Models\Ventas\Venta;
use Exception;
use Illuminate\Support\Facades\DB;

class DevolucionesVentasService
{
    public function crearPartida($devolucion)
    {
        if (!$devolucion || !isset($devolucion->id)) {
            throw new Exception('La devolución proporcionada no es válida', 400);
        }

        $devolucion = Devolucion::with(['detalles.producto', 'documento', 'venta'])->find($devolucion->id);
        if (!$devolucion) {
            throw new Exception('No se encontró la devolución', 400);
        }

        if (!$devolucion->enable) {
            throw new Exception('No se puede contabilizar una devolución anulada', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        if (!$devolucion->fecha) {
            throw new Exception('La devolución no tiene fecha asignada', 400);
        }

        if (!$devolucion->total || $devolucion->total <= 0) {
            throw new Exception('La devolución no tiene un monto válido', 400);
        }

        if (!$devolucion->id_sucursal) {
            throw new Exception('La devolución no tiene sucursal asignada', 400);
        }

        if (!$configuracion->id_cuenta_devoluciones_ventas) {
            throw new Exception('No se ha configurado la cuenta de devoluciones de ventas', 400);
        }

        Partida::assertNoExisteParaOrigen(
            'Devolucion de Venta',
            $devolucion->id,
            'Ya existen partidas contables generadas para esta devolución.'
        );

        $refDocumento = ($devolucion->nombre_documento ?: 'Devolución') . ' #' . ($devolucion->correlativo ?? $devolucion->id);

        DB::beginTransaction();

        try {
            $partida_ingresos = Partida::create([
                'fecha' => $devolucion->fecha,
                'tipo' => 'Diario',
                'concepto' => 'Devolución de ventas. ' . $refDocumento,
                'estado' => 'Pendiente',
                'referencia' => 'Devolucion de Venta',
                'id_referencia' => $devolucion->id,
                'id_usuario' => $devolucion->id_usuario,
                'id_empresa' => $devolucion->id_empresa,
            ]);

            $cuenta_devoluciones = Cuenta::find($configuracion->id_cuenta_devoluciones_ventas);
            if (!$cuenta_devoluciones) {
                throw new Exception('No se encontró la cuenta contable de devoluciones de ventas', 400);
            }

            // Neto gravado + exentas / no sujetas (sin IVA, retencion ni cuenta a terceros)
            $montoDevolucion = (float) $devolucion->sub_total
                + (float) ($devolucion->exenta ?? 0)
                + (float) ($devolucion->no_sujeta ?? 0);

            Detalle::create([
                'id_cuenta' => $cuenta_devoluciones->id,
                'codigo' => $cuenta_devoluciones->codigo,
                'nombre_cuenta' => $cuenta_devoluciones->nombre,
                'concepto' => 'Devolucion de ventas',
                'debe' => $montoDevolucion,
                'haber' => null,
                'saldo' => 0,
                'id_partida' => $partida_ingresos->id,
            ]);

            if ($devolucion->cuenta_a_terceros > 0) {
                if (!$configuracion->id_cuenta_cuenta_a_terceros) {
                    throw new Exception('No se ha configurado la cuenta contable de cuenta a terceros', 400);
                }

                $cuenta_terceros = Cuenta::find($configuracion->id_cuenta_cuenta_a_terceros);
                if (!$cuenta_terceros) {
                    throw new Exception('No se encontro la cuenta contable de cuenta a terceros', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta_terceros->id,
                    'codigo' => $cuenta_terceros->codigo,
                    'nombre_cuenta' => $cuenta_terceros->nombre,
                    'concepto' => 'Cobros a cuenta de terceros (devolucion)',
                    'debe' => $devolucion->cuenta_a_terceros,
                    'haber' => null,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id,
                ]);
            }

            if ($devolucion->iva > 0) {
                if (!$configuracion->id_cuenta_iva_ventas) {
                    throw new Exception('No se ha configurado la cuenta de IVA ventas', 400);
                }

                $cuenta_iva = Cuenta::find($configuracion->id_cuenta_iva_ventas);
                if (!$cuenta_iva) {
                    throw new Exception('No se encontró la cuenta contable de IVA ventas', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta_iva->id,
                    'codigo' => $cuenta_iva->codigo,
                    'nombre_cuenta' => $cuenta_iva->nombre,
                    'concepto' => 'IVA Débito Fiscal (devolución)',
                    'debe' => $devolucion->iva,
                    'haber' => null,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id,
                ]);
            }

            if ($devolucion->iva_retenido > 0) {
                if (!$configuracion->id_cuenta_iva_retenido_ventas) {
                    throw new Exception('No se ha configurado la cuenta de IVA retenido ventas', 400);
                }

                $cuenta_iva_retenido = Cuenta::find($configuracion->id_cuenta_iva_retenido_ventas);
                if (!$cuenta_iva_retenido) {
                    throw new Exception('No se encontró la cuenta contable de IVA retenido ventas', 400);
                }

                Detalle::create([
                    'id_cuenta' => $cuenta_iva_retenido->id,
                    'codigo' => $cuenta_iva_retenido->codigo,
                    'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                    'concepto' => 'IVA retenido (devolución)',
                    'debe' => null,
                    'haber' => $devolucion->iva_retenido,
                    'saldo' => 0,
                    'id_partida' => $partida_ingresos->id,
                ]);
            }

            $cuenta_haber = $this->resolverCuentaHaber($devolucion, $configuracion);

            Detalle::create([
                'id_cuenta' => $cuenta_haber->id,
                'codigo' => $cuenta_haber->codigo,
                'nombre_cuenta' => $cuenta_haber->nombre,
                'concepto' => 'Devolución de ventas',
                'debe' => null,
                'haber' => $devolucion->total,
                'saldo' => 0,
                'id_partida' => $partida_ingresos->id,
            ]);

            // Reverso de costo de ventas (inventario entra, costo se reduce)
            $productos = DetalleDevolucion::with('producto')->where('id_devolucion_venta', $devolucion->id)->get();
            $tieneCosto = $productos->contains(fn ($p) => ($p->costo * $p->cantidad) > 0)
                || ($devolucion->total_costo ?? 0) > 0;

            $partida_costos_id = null;
            if ($tieneCosto && $productos->isNotEmpty()) {
                $partida_costos = Partida::create([
                    'fecha' => $devolucion->fecha,
                    'tipo' => 'Diario',
                    'concepto' => 'Reverso costo por devolución. ' . $refDocumento,
                    'estado' => 'Pendiente',
                    'referencia' => 'Devolucion de Venta',
                    'id_referencia' => $devolucion->id,
                    'id_usuario' => $devolucion->id_usuario,
                    'id_empresa' => $devolucion->id_empresa,
                ]);
                $partida_costos_id = $partida_costos->id;

                foreach ($productos as $producto) {
                    $id_categoria = $producto->producto->id_categoria ?? null;
                    $costo_total_producto = $producto->costo * $producto->cantidad;

                    if ($costo_total_producto <= 0) {
                        continue;
                    }

                    if ($id_categoria) {
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)
                            ->where('id_sucursal', $devolucion->id_sucursal)
                            ->first();

                        if (!$cuenta_categoria_sucursal) {
                            throw new Exception('No se encontró configuración de cuentas para la categoría del producto en esta sucursal', 400);
                        }

                        if (!$cuenta_categoria_sucursal->id_cuenta_contable_costo) {
                            throw new Exception('La categoría del producto no tiene cuenta de costo configurada', 400);
                        }

                        if (!$cuenta_categoria_sucursal->id_cuenta_contable_inventario) {
                            throw new Exception('La categoría del producto no tiene cuenta de inventario configurada', 400);
                        }

                        $cuenta_costos = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_costo);
                        $cuenta_inventarios = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_inventario);

                        if (!$cuenta_costos || !$cuenta_inventarios) {
                            throw new Exception('No se encontraron las cuentas contables de costo/inventario para la categoría', 400);
                        }

                        Detalle::create([
                            'id_cuenta' => $cuenta_inventarios->id,
                            'codigo' => $cuenta_inventarios->codigo,
                            'nombre_cuenta' => $cuenta_inventarios->nombre,
                            'concepto' => 'Inventarios (devolución)',
                            'debe' => $costo_total_producto,
                            'haber' => null,
                            'saldo' => 0,
                            'id_partida' => $partida_costos->id,
                        ]);

                        Detalle::create([
                            'id_cuenta' => $cuenta_costos->id,
                            'codigo' => $cuenta_costos->codigo,
                            'nombre_cuenta' => $cuenta_costos->nombre,
                            'concepto' => 'Costo de ventas (devolución)',
                            'debe' => null,
                            'haber' => $costo_total_producto,
                            'saldo' => 0,
                            'id_partida' => $partida_costos->id,
                        ]);
                    } else {
                        if (!$configuracion->id_cuenta_costo_venta || !$configuracion->id_cuenta_inventario) {
                            throw new Exception('No se han configurado las cuentas generales de costo/inventario', 400);
                        }

                        $cuenta_costo = Cuenta::find($configuracion->id_cuenta_costo_venta);
                        $cuenta_inventario = Cuenta::find($configuracion->id_cuenta_inventario);

                        if (!$cuenta_costo || !$cuenta_inventario) {
                            throw new Exception('No se encontraron las cuentas contables generales de costo/inventario', 400);
                        }

                        $totalCosto = $devolucion->total_costo ?? $costo_total_producto;

                        Detalle::create([
                            'id_cuenta' => $cuenta_inventario->id,
                            'codigo' => $cuenta_inventario->codigo,
                            'nombre_cuenta' => $cuenta_inventario->nombre,
                            'concepto' => 'Inventarios (devolución)',
                            'debe' => $totalCosto,
                            'haber' => null,
                            'saldo' => 0,
                            'id_partida' => $partida_costos->id,
                        ]);

                        Detalle::create([
                            'id_cuenta' => $cuenta_costo->id,
                            'codigo' => $cuenta_costo->codigo,
                            'nombre_cuenta' => $cuenta_costo->nombre,
                            'concepto' => 'Costo de ventas (devolución)',
                            'debe' => null,
                            'haber' => $totalCosto,
                            'saldo' => 0,
                            'id_partida' => $partida_costos->id,
                        ]);

                        break;
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partidas contables de devolución creadas exitosamente',
                'partida_ingresos_id' => $partida_ingresos->id,
                'partida_costos_id' => $partida_costos_id,
            ];
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear las partidas de devolución: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear las partidas de devolución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Haber: reduce CxC si la venta original es a crédito; si es contado, usa la forma de pago;
     * si existe cuenta de devoluciones a clientes, tiene prioridad.
     */
    private function resolverCuentaHaber(Devolucion $devolucion, Configuracion $configuracion): Cuenta
    {
        if ($configuracion->id_cuenta_devoluciones_clientes) {
            $cuenta = Cuenta::find($configuracion->id_cuenta_devoluciones_clientes);
            if ($cuenta) {
                return $cuenta;
            }
        }

        $venta = $devolucion->venta ?: Venta::find($devolucion->id_venta);

        if ($venta && $venta->estado === 'Pendiente') {
            if (!$configuracion->id_cuenta_cxc) {
                throw new Exception('No se ha configurado la cuenta de cuentas por cobrar', 400);
            }
            $cuenta = Cuenta::find($configuracion->id_cuenta_cxc);
            if (!$cuenta) {
                throw new Exception('No se encontró la cuenta contable de cuentas por cobrar', 400);
            }

            return $cuenta;
        }

        $formaPagoNombre = $venta->forma_pago ?? null;
        if ($formaPagoNombre) {
            $formapago = FormaDePago::with('banco')->where('nombre', $formaPagoNombre)->first();
            if ($formapago && $formapago->banco && $formapago->banco->id_cuenta_contable) {
                $cuenta = Cuenta::find($formapago->banco->id_cuenta_contable);
                if ($cuenta) {
                    return $cuenta;
                }
            }
        }

        if (!$configuracion->id_cuenta_cxc) {
            throw new Exception('No se pudo determinar la cuenta Haber para la devolución. Configure devoluciones a clientes, CxC o la forma de pago de la venta.', 400);
        }

        $cuenta = Cuenta::find($configuracion->id_cuenta_cxc);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta contable de cuentas por cobrar', 400);
        }

        return $cuenta;
    }
}

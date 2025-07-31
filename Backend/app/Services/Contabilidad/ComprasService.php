<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Exception;

class ComprasService
{
    public function crearPartida($compra)
    {
        // Validar que la compra existe
        if (!$compra || !isset($compra->id)) {
            throw new Exception('La compra proporcionada no es válida', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Validar que la compra tiene los datos necesarios
        if (!$compra->fecha) {
            throw new Exception('La compra no tiene fecha asignada', 400);
        }

        if (!$compra->total || $compra->total <= 0) {
            throw new Exception('La compra no tiene un monto válido', 400);
        }

        DB::beginTransaction();

        try {
            $partida = Partida::create([
                'fecha'         => $compra->fecha,
                'tipo'          => $compra->estado == 'Pendiente' ? 'CxP' : 'Egreso',
                'concepto'      => 'Compra de mercancía. ' . ($compra->tipo_documento ?? 'Documento') . ' #' . ($compra->referencia ?? 'Sin referencia'),
                'estado'        => 'Pendiente',
                'referencia'    => 'Compra',
                'id_referencia' => $compra->id,
                'id_usuario'    => $compra->id_usuario,
                'id_empresa'    => $compra->id_empresa,
            ]);

            // Haber - Determinar cuenta según estado
            if ($compra->estado == 'Pendiente') {
                if (!$configuracion->id_cuenta_cxp) {
                    throw new Exception('No se ha configurado la cuenta de cuentas por pagar en la configuración contable', 400);
                }
                $cuenta = Cuenta::find($configuracion->id_cuenta_cxp);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
                }
            } else {
                if (!$compra->forma_pago) {
                    throw new Exception('La compra no tiene forma de pago asignada', 400);
                }

                $formapago = FormaDePago::with('banco')->where('nombre', $compra->forma_pago)->first();
                if (!$formapago) {
                    throw new Exception('No se encontró la forma de pago: ' . $compra->forma_pago, 400);
                }

                if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
                    throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada', 400);
                }

                $cuenta = Cuenta::find($formapago->banco->id_cuenta_contable);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable del banco asociado a la forma de pago', 400);
                }
            }

            Detalle::create([
                'id_cuenta'         => $cuenta->id,
                'codigo'            => $cuenta->codigo,
                'nombre_cuenta'     => $cuenta->nombre,
                'concepto'          => 'Compra de mercancía',
                'debe'              => NULL,
                'haber'             => $compra->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            // Debe - Procesar productos de la compra
            $productos_compra = DetalleCompra::where('id_compra', $compra->id)->get();

            if ($productos_compra->isEmpty()) {
                throw new Exception('La compra no tiene productos asociados', 400);
            }

            $total_productos_procesados = 0;

            foreach ($productos_compra as $producto) {
                if (!$producto->producto) {
                    throw new Exception('Producto no encontrado en el detalle de compra', 400);
                }

                $id_categoria = $producto->producto->id_categoria;

                if ($id_categoria) {
                    if (!$compra->id_sucursal) {
                        throw new Exception('La compra no tiene sucursal asignada', 400);
                    }

                    $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)
                                                                ->where('id_sucursal', $compra->id_sucursal)
                                                                ->first();

                    if (!$cuenta_categoria_sucursal) {
                        throw new Exception('No se encontró configuración de cuentas para la categoría del producto en esta sucursal', 400);
                    }

                    if (!$cuenta_categoria_sucursal->id_cuenta_contable_inventario) {
                        throw new Exception('La categoría del producto no tiene cuenta de inventario configurada', 400);
                    }

                    $cuenta = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_inventario);
                    if (!$cuenta) {
                        throw new Exception('No se encontró la cuenta contable de inventario para la categoría', 400);
                    }

                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Compra de mercancía',
                        'debe'              => $producto->total,
                        'haber'             => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);

                    $total_productos_procesados += $producto->total;
                } else {
                    // Producto sin categoría - usar cuenta general
                    if (!$configuracion->id_cuenta_inventario) {
                        throw new Exception('No se ha configurado la cuenta general de inventario', 400);
                    }

                    $cuenta = Cuenta::find($configuracion->id_cuenta_inventario);
                    if (!$cuenta) {
                        throw new Exception('No se encontró la cuenta contable general de inventario', 400);
                    }

                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Compra de mercancía',
                        'debe'              => $compra->sub_total,
                        'haber'             => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);

                    $total_productos_procesados += $compra->sub_total;
                    break; // Solo procesar una vez para productos sin categoría
                }
            }

            // IVA
            if ($compra->iva > 0) {
                if (!$configuracion->id_cuenta_iva_compras) {
                    throw new Exception('No se ha configurado la cuenta de IVA compras', 400);
                }

                $cuenta = Cuenta::find($configuracion->id_cuenta_iva_compras);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de IVA compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
                    'debe'              => $compra->iva,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            // Percepción IVA
            if ($compra->percepcion > 0) {
                if (!$configuracion->id_cuenta_iva_retenido_compras) {
                    throw new Exception('No se ha configurado la cuenta de IVA retenido compras', 400);
                }

                $cuenta = Cuenta::find($configuracion->id_cuenta_iva_retenido_compras);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de IVA retenido compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
                    'debe'              => $compra->percepcion,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            // Renta retenida
            if ($compra->renta_retenida > 0) {
                if (!$configuracion->id_cuenta_renta_retenida_compras) {
                    throw new Exception('No se ha configurado la cuenta de renta retenida compras', 400);
                }

                $cuenta = Cuenta::find($configuracion->id_cuenta_renta_retenida_compras);
                if (!$cuenta) {
                    throw new Exception('No se encontró la cuenta contable de renta retenida compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
                    'debe'              => $compra->renta_retenida,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de compra creada exitosamente',
                'partida_id' => $partida->id,
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de compra: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de compra: ' . $e->getMessage(), 500);
        }
    }
}

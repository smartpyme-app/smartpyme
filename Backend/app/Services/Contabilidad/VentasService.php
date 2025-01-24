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
        $configuracion = Configuracion::firstOrFail();

        DB::beginTransaction();

        try {

            $partida = Partida::create([
                'fecha' => $venta->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas. ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'estado' => 'Pendiente',
                'referencia' => 'Venta',
                'id_referencia' => $venta->id,
                'id_usuario' => $venta->id_usuario,
                'id_empresa' => $venta->id_empresa,
            ]);

            // Debe
            // Haber
            if ($venta->estado == 'Pendiente') {
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxc)->firstOrFail();
            } else {
                $formapago = FormaDePago::with('banco')->where('nombre', $venta->forma_pago)->firstOrFail();
                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->firstOrFail();
            }
            Detalle::create([
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => $venta->total,
                'haber' => NULL,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);

            // Haber
            //llamamos a los productos de la venta para obtener la categoria
            $productos_venta = DetalleVenta::where('id_venta', $venta->id)->get();

            foreach ($productos_venta as $producto) {
                $id_categoria = $producto->producto->id_categoria;
                if($id_categoria){
                    $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->firstOrFail();
                    $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Ingresos por ventas',
                        'debe' => NULL,
                        'haber' => $producto->total,
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);
                }else{
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_ventas)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Ingresos por ventas',
                        'debe' => NULL,
                        'haber' => $venta->sub_total,
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);
                }
            }

            if ($venta->iva > 0) {
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->firstOrFail();
                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => NULL,
                    'haber' => $venta->iva,
                    'saldo' => 0,
                    'id_partida' => $partida->id
                ]);
            }

            if ($venta->iva_retenido > 0) {
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->firstOrFail();
                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => $venta->iva_retenido,
                    'haber' => NULL,
                    'saldo' => 0,
                    'id_partida' => $partida->id
                ]);
            }


        // Costo de venta
             $partida = Partida::create([
                 'fecha' => $venta->fecha,
                 'tipo' => 'Ingreso',
                 'concepto' => 'Ingresos por costo de ventas. ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                 'estado' => 'Pendiente',
                 'referencia'    => 'Venta',
                 'id_referencia' => $venta->id,
                 'id_usuario' => $venta->id_usuario,
                 'id_empresa' => $venta->id_empresa,
             ]);

            $productos_venta = DetalleVenta::where('id_venta', $venta->id)->get();

            foreach ($productos_venta as $producto) {
                $id_categoria = $producto->producto->id_categoria;
                if($id_categoria){
                    $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->firstOrFail();
                    $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta_costos->id,
                        'codigo' => $cuenta_costos->codigo,
                        'nombre_cuenta' => $cuenta_costos->nombre,
                        'concepto' => 'Ingresos por costo de ventas',
                        'debe' => ($producto->costo * $producto->cantidad),
                        'haber' => NULL,
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);

                    $cuenta_inventarios = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta_inventarios->id,
                        'codigo' => $cuenta_inventarios->codigo,
                        'nombre_cuenta' => $cuenta_inventarios->nombre,
                        'concepto' => 'Inventarios',
                        'debe' => NULL,
                        'haber' => ($producto->costo * $producto->cantidad),
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);
                }else{
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Ingresos por costo de ventas',
                        'debe' => $venta->total_costo,
                        'haber' => NULL,
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_inventario)->firstOrFail();
                    Detalle::create([
                        'id_cuenta' => $cuenta->id,
                        'codigo' => $cuenta->codigo,
                        'nombre_cuenta' => $cuenta->nombre,
                        'concepto' => 'Inventarios',
                        'debe' => NULL,
                        'haber' => $venta->total_costo,
                        'saldo' => 0,
                        'id_partida' => $partida->id
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }

    }

}

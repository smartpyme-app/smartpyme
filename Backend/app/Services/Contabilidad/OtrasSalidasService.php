<?php

namespace App\Services\Contabilidad;

use App\Models\Inventario\Salidas\Salida;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OtrasSalidasService
{
    public function crearPartida($salida)
    {
        // Validar que la salida existe
        if (!$salida || !isset($salida->id)) {
            throw new Exception('La salida proporcionada no es válida', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Recargar la salida con las relaciones necesarias
        $salidaCompleta = Salida::with('detalles.producto', 'bodega', 'empresa')->where('id', $salida->id)->first();

        if (!$salidaCompleta) {
            throw new Exception('No se encontró la salida con ID: ' . $salida->id, 400);
        }

        // Validar que la salida esté aprobada
        if ($salidaCompleta->estado !== 'Aprobada') {
            throw new Exception('La salida debe estar en estado Aprobada para generar la partida contable', 400);
        }

        // Validar que tenga detalles
        if (!$salidaCompleta->detalles || count($salidaCompleta->detalles) == 0) {
            throw new Exception('La salida no tiene detalles para generar la partida contable', 400);
        }

        DB::beginTransaction();

        try {
            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($salidaCompleta->fecha)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Otra salida de inventario - ' . $salidaCompleta->concepto,
                'estado'        => 'Pendiente',
                'referencia'    => 'Otra Salida',
                'id_referencia' => $salidaCompleta->id,
                'id_usuario'    => $salidaCompleta->id_usuario,
                'id_empresa'    => $salidaCompleta->id_empresa,
            ]);

            // Procesar cada detalle de la salida
            foreach ($salidaCompleta->detalles as $detalle) {
                if (!$detalle->producto) {
                    throw new Exception('El detalle no tiene un producto válido asociado', 400);
                }

                // Validar categoría del producto
                $id_categoria = $detalle->producto->id_categoria;
                if (!$id_categoria) {
                    throw new Exception('El producto ' . $detalle->producto->nombre . ' no tiene categoría asignada', 400);
                }

                // Buscar cuentas por categoría y sucursal
                $cuenta_categoria = CuentaCategoria::where('id_categoria', $id_categoria)
                                                ->where('id_sucursal', $salidaCompleta->bodega->id_sucursal)
                                                ->first();

                if (!$cuenta_categoria) {
                    throw new Exception('No se encontró configuración de cuentas para la categoría del producto ' . $detalle->producto->nombre . ' en esta sucursal', 400);
                }

                // Obtener cuenta de inventario
                $cuenta_inventario = Cuenta::find($cuenta_categoria->id_cuenta_contable_inventario);

                if (!$cuenta_inventario) {
                    throw new Exception('No se encontró la cuenta contable de inventario configurada para el producto ' . $detalle->producto->nombre, 400);
                }

                // Calcular el costo a utilizar
                $costo_unitario = $detalle->costo;

                // Si el detalle no tiene costo o es 0, usar el costo del producto
                if (!$costo_unitario || $costo_unitario == 0) {
                    // Verificar si la empresa usa costo promedio
                    if ($salidaCompleta->empresa &&
                        $salidaCompleta->empresa->valor_inventario == 'promedio' &&
                        $detalle->producto->costo_promedio > 0) {
                        $costo_unitario = $detalle->producto->costo_promedio;
                    } else {
                        $costo_unitario = $detalle->producto->costo;
                    }
                }

                // Validar que tengamos un costo válido
                if (!$costo_unitario || $costo_unitario <= 0) {
                    throw new Exception('No se puede crear la partida: el producto ' . $detalle->producto->nombre . ' no tiene un costo válido asignado', 400);
                }

                // Calcular el monto total del detalle
                $monto_total = $detalle->cantidad * $costo_unitario;

                // Crear detalle de salida de inventario - HABER
                Detalle::create([
                    'id_cuenta'         => $cuenta_inventario->id,
                    'codigo'            => $cuenta_inventario->codigo,
                    'nombre_cuenta'     => $cuenta_inventario->nombre,
                    'concepto'          => 'Salida de inventario - ' . $detalle->producto->nombre,
                    'debe'              => null,
                    'haber'             => $monto_total,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                // Buscar cuenta de contrapartida según el tipo de salida
                $cuenta_contrapartida = Cuenta::find($configuracion->id_cuenta_inventario_transitorio);

                if (!$cuenta_contrapartida) {
                    throw new Exception('No se encontró la cuenta contrapartida configurada para el tipo de salida: ' . $salidaCompleta->tipo, 400);
                }

                // Crear detalle de contrapartida - DEBE
                Detalle::create([
                    'id_cuenta'         => $cuenta_contrapartida->id,
                    'codigo'            => $cuenta_contrapartida->codigo,
                    'nombre_cuenta'     => $cuenta_contrapartida->nombre,
                    'concepto'          => 'Contrapartida salida de inventario - ' . $detalle->producto->nombre,
                    'debe'              => $monto_total,
                    'haber'             => null,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de otra salida creada exitosamente',
                'partida_id' => $partida->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de otra salida: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de otra salida: ' . $e->getMessage(), 500);
        }
    }
}

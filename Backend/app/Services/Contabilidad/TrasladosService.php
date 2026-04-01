<?php

namespace App\Services\Contabilidad;

use App\Models\Inventario\Traslado;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class TrasladosService
{
    public function crearPartida($traslado)
    {
        // Validar que el traslado existe
        if (!$traslado || !isset($traslado->id)) {
            throw new Exception('El traslado proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Recargar el traslado con las relaciones necesarias
        $trasladoCompleto = Traslado::with('producto', 'origen', 'destino')->where('id', $traslado->id)->first();

        if (!$trasladoCompleto) {
            throw new Exception('No se encontró el traslado con ID: ' . $traslado->id, 400);
        }

        // Validar que el producto existe
        if (!$trasladoCompleto->producto) {
            throw new Exception('El traslado no tiene un producto válido asociado', 400);
        }

        // Validar que las bodegas existen
        if (!$trasladoCompleto->origen) {
            throw new Exception('El traslado no tiene una bodega de origen válida asociada', 400);
        }

        if (!$trasladoCompleto->destino) {
            throw new Exception('El traslado no tiene una bodega de destino válida asociada', 400);
        }

        // Validar cantidad y costo
        if (!$trasladoCompleto->cantidad || $trasladoCompleto->cantidad <= 0) {
            throw new Exception('El traslado no tiene una cantidad válida', 400);
        }

        Partida::assertNoExisteParaOrigen('Traslado', $trasladoCompleto->id, 'Ya existen partidas contables generadas para este traslado.');

        DB::beginTransaction();

        try {
            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($trasladoCompleto->created_at)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Traslado de inventario',
                'estado'        => 'Pendiente',
                'referencia'    => 'Traslado',
                'id_referencia' => $trasladoCompleto->id,
                'id_usuario'    => $trasladoCompleto->id_usuario,
                'id_empresa'    => $trasladoCompleto->id_empresa,
            ]);

            // Validar categoría del producto
            $id_categoria = $trasladoCompleto->producto->id_categoria;
            if (!$id_categoria) {
                throw new Exception('El producto no tiene categoría asignada', 400);
            }

            // Calcular el costo a utilizar
            $costo_unitario = $trasladoCompleto->costo;

            // Si el traslado no tiene costo o es 0, usar el costo del producto
            if (!$costo_unitario || $costo_unitario == 0) {
                // Verificar si la empresa usa costo promedio
                if ($trasladoCompleto->empresa &&
                    $trasladoCompleto->empresa->valor_inventario == 'promedio' &&
                    $trasladoCompleto->producto->costo_promedio > 0) {
                    $costo_unitario = $trasladoCompleto->producto->costo_promedio;
                } else {
                    $costo_unitario = $trasladoCompleto->producto->costo;
                }
            }

            // Validar que tengamos un costo válido
            if (!$costo_unitario || $costo_unitario <= 0) {
                throw new Exception('No se puede crear la partida: el producto no tiene un costo válido asignado', 400);
            }

            // Buscar cuenta de categoría para bodega origen
            $cuenta_categoria_origen = CuentaCategoria::where('id_categoria', $id_categoria)
                                                    ->where('id_sucursal', $trasladoCompleto->origen->id_sucursal)
                                                    ->first();

            if (!$cuenta_categoria_origen) {
                throw new Exception('No se encontró configuración de cuentas para la categoría del producto en la sucursal de origen', 400);
            }

            // Obtener cuenta de inventario origen
            $cuenta_inventario_origen = Cuenta::find($cuenta_categoria_origen->id_cuenta_contable_inventario);
            if (!$cuenta_inventario_origen) {
                throw new Exception('No se encontró la cuenta contable de inventario para la bodega de origen', 400);
            }

            // Buscar cuenta de categoría para bodega destino
            $cuenta_categoria_destino = CuentaCategoria::where('id_categoria', $id_categoria)
                                                    ->where('id_sucursal', $trasladoCompleto->destino->id_sucursal)
                                                    ->first();

            if (!$cuenta_categoria_destino) {
                throw new Exception('No se encontró configuración de cuentas para la categoría del producto en la sucursal de destino', 400);
            }

            // Obtener cuenta de inventario destino
            $cuenta_inventario_destino = Cuenta::find($cuenta_categoria_destino->id_cuenta_contable_inventario);
            if (!$cuenta_inventario_destino) {
                throw new Exception('No se encontró la cuenta contable de inventario para la bodega de destino', 400);
            }

            // Calcular monto total del traslado
            $monto_total = $trasladoCompleto->cantidad * $costo_unitario;

            // Crear detalle de salida (origen) - HABER
            Detalle::create([
                'id_cuenta'         => $cuenta_inventario_origen->id,
                'codigo'            => $cuenta_inventario_origen->codigo,
                'nombre_cuenta'     => $cuenta_inventario_origen->nombre,
                'concepto'          => 'Salida por traslado de inventario',
                'debe'              => null,
                'haber'             => $monto_total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            // Crear detalle de entrada (destino) - DEBE
            Detalle::create([
                'id_cuenta'         => $cuenta_inventario_destino->id,
                'codigo'            => $cuenta_inventario_destino->codigo,
                'nombre_cuenta'     => $cuenta_inventario_destino->nombre,
                'concepto'          => 'Entrada por traslado de inventario',
                'debe'              => $monto_total,
                'haber'             => null,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de traslado creada exitosamente',
                'partida_id' => $partida->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de traslado: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de traslado: ' . $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Services\Contabilidad;

use App\Models\Inventario\Entradas\Entrada;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OtrasEntradasService
{
    public function crearPartida($entrada)
    {
        // Validar que la entrada existe
        if (!$entrada || !isset($entrada->id)) {
            throw new Exception('La entrada proporcionada no es válida', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Recargar la entrada con las relaciones necesarias
        $entradaCompleta = Entrada::with('detalles.producto', 'bodega', 'empresa')->where('id', $entrada->id)->first();

        if (!$entradaCompleta) {
            throw new Exception('No se encontró la entrada con ID: ' . $entrada->id, 400);
        }

        // Validar que la entrada esté aprobada
        if ($entradaCompleta->estado !== 'Aprobada') {
            throw new Exception('La entrada debe estar en estado Aprobada para generar la partida contable', 400);
        }

        // Validar que tenga detalles
        if (!$entradaCompleta->detalles || count($entradaCompleta->detalles) == 0) {
            throw new Exception('La entrada no tiene detalles para generar la partida contable', 400);
        }

        DB::beginTransaction();

        try {
            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($entradaCompleta->fecha)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Otra entrada de inventario - ' . $entradaCompleta->concepto,
                'estado'        => 'Pendiente',
                'referencia'    => 'Otra Entrada',
                'id_referencia' => $entradaCompleta->id,
                'id_usuario'    => $entradaCompleta->id_usuario,
                'id_empresa'    => $entradaCompleta->id_empresa,
            ]);

            // Procesar cada detalle de la entrada
            foreach ($entradaCompleta->detalles as $detalle) {
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
                                                ->where('id_sucursal', $entradaCompleta->bodega->id_sucursal)
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
                    if ($entradaCompleta->empresa &&
                        $entradaCompleta->empresa->valor_inventario == 'promedio' &&
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

                // Crear detalle de entrada de inventario - DEBE
                Detalle::create([
                    'id_cuenta'         => $cuenta_inventario->id,
                    'codigo'            => $cuenta_inventario->codigo,
                    'nombre_cuenta'     => $cuenta_inventario->nombre,
                    'concepto'          => 'Entrada de inventario - ' . $detalle->producto->nombre,
                    'debe'              => $monto_total,
                    'haber'             => null,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                // Buscar cuenta de contrapartida según el tipo de entrada
                $cuenta_contrapartida = Cuenta::find($configuracion->id_cuenta_inventario_transitorio);

                if (!$cuenta_contrapartida) {
                    throw new Exception('No se encontró la cuenta contrapartida configurada para el tipo de entrada: ' . $entradaCompleta->tipo, 400);
                }

                // Crear detalle de contrapartida - HABER
                Detalle::create([
                    'id_cuenta'         => $cuenta_contrapartida->id,
                    'codigo'            => $cuenta_contrapartida->codigo,
                    'nombre_cuenta'     => $cuenta_contrapartida->nombre,
                    'concepto'          => 'Contrapartida entrada de inventario - ' . $detalle->producto->nombre,
                    'debe'              => null,
                    'haber'             => $monto_total,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de otra entrada creada exitosamente',
                'partida_id' => $partida->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de otra entrada: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de otra entrada: ' . $e->getMessage(), 500);
        }
    }
}

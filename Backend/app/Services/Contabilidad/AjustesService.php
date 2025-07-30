<?php

namespace App\Services\Contabilidad;

use App\Models\Inventario\Ajuste;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class AjustesService
{
    public function crearPartida($ajuste)
    {
        // Validar que el ajuste existe
        if (!$ajuste || !isset($ajuste->id)) {
            throw new Exception('El ajuste proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Recargar el ajuste con las relaciones necesarias
        $ajusteCompleto = Ajuste::with('producto', 'bodega', 'empresa')->where('id', $ajuste->id)->first();

        if (!$ajusteCompleto) {
            throw new Exception('No se encontró el ajuste con ID: ' . $ajuste->id, 400);
        }

        // Validar que el producto existe
        if (!$ajusteCompleto->producto) {
            throw new Exception('El ajuste no tiene un producto válido asociado', 400);
        }

        // Validar que la bodega existe
        if (!$ajusteCompleto->bodega) {
            throw new Exception('El ajuste no tiene una bodega válida asociada', 400);
        }

        DB::beginTransaction();

        try {
            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($ajusteCompleto->created_at)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Ajuste de inventario - Conteo físico.',
                'estado'        => 'Pendiente',
                'referencia'    => 'Ajuste',
                'id_referencia' => $ajusteCompleto->id,
                'id_usuario'    => $ajusteCompleto->id_usuario,
                'id_empresa'    => $ajusteCompleto->id_empresa,
            ]);

            // Validar categoría del producto
            $id_categoria = $ajusteCompleto->producto->id_categoria;
            if (!$id_categoria) {
                throw new Exception('El producto no tiene categoría asignada', 400);
            }

            // Buscar cuentas por categoría y sucursal
            $cuenta_categoria = CuentaCategoria::where('id_categoria', $id_categoria)
                                                        ->where('id_sucursal', $ajusteCompleto->bodega->id_sucursal)
                                                        ->first();

            if (!$cuenta_categoria) {
                throw new Exception('No se encontró configuración de cuentas para la categoría del producto en esta sucursal', 400);
            }

            // Obtener cuenta de inventario
            $cuenta_inventario = Cuenta::find($cuenta_categoria->id_cuenta_contable_inventario);

            if (!$cuenta_inventario) {
                throw new Exception('No se encontró la cuenta contable de inventario configurada', 400);
            }

            // Calcular el costo a utilizar
            $costo_unitario = $ajusteCompleto->costo;

            // Si el ajuste no tiene costo o es 0, usar el costo del producto
            if (!$costo_unitario || $costo_unitario == 0) {
                // Verificar si la empresa usa costo promedio
                if ($ajusteCompleto->empresa &&
                    $ajusteCompleto->empresa->valor_inventario == 'promedio' &&
                    $ajusteCompleto->producto->costo_promedio > 0) {
                    $costo_unitario = $ajusteCompleto->producto->costo_promedio;
                } else {
                    $costo_unitario = $ajusteCompleto->producto->costo;
                }
            }

            // Validar que tengamos un costo válido
            if (!$costo_unitario || $costo_unitario <= 0) {
                throw new Exception('No se puede crear la partida: el producto no tiene un costo válido asignado', 400);
            }

            // Determinar si el ajuste es positivo o negativo
            $monto = abs($costo_unitario * $ajusteCompleto->ajuste);

            if ($ajusteCompleto->ajuste < 0) {
                // 🔻 FALTANTE → Disminuye el inventario, se registra una pérdida
                $id_cuenta_perdida = $cuenta_categoria->id_cuenta_contable_perdida ?? $configuracion->id_cuenta_perdida_ajuste;

                if (!$id_cuenta_perdida) {
                    throw new Exception('No se encontró la cuenta de pérdida configurada, por favor verifique las configuraciones de contabilidad', 400);
                }

                $cuenta_perdida = Cuenta::find($id_cuenta_perdida);

                if (!$cuenta_perdida) {
                    throw new Exception('No se encontró la cuenta contable de pérdida, por favor verifique las configuraciones de contabilidad', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta_perdida->id,
                    'codigo'            => $cuenta_perdida->codigo,
                    'nombre_cuenta'     => $cuenta_perdida->nombre,
                    'concepto'          => 'Pérdida por ajuste de inventario',
                    'debe'              => $monto,
                    'haber'             => null,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                Detalle::create([
                    'id_cuenta'         => $cuenta_inventario->id,
                    'codigo'            => $cuenta_inventario->codigo,
                    'nombre_cuenta'     => $cuenta_inventario->nombre,
                    'concepto'          => 'Salida por ajuste de inventario',
                    'debe'              => null,
                    'haber'             => $monto,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            } else {
                // 🔺 SOBRANTE → Aumenta el inventario, se registra una ganancia
                $id_cuenta_ganancia = $cuenta_categoria->id_cuenta_contable_ganancia ?? $configuracion->id_cuenta_ganancia_ajuste;

                if (!$id_cuenta_ganancia) {
                    throw new Exception('No se encontró la cuenta de ganancia configurada, por favor verifique las configuraciones de contabilidad', 400);
                }

                $cuenta_ganancia = Cuenta::find($id_cuenta_ganancia);

                if (!$cuenta_ganancia) {
                    throw new Exception('No se encontró la cuenta contable de ganancia, por favor verifique las configuraciones de contabilidad', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta_inventario->id,
                    'codigo'            => $cuenta_inventario->codigo,
                    'nombre_cuenta'     => $cuenta_inventario->nombre,
                    'concepto'          => 'Entrada por ajuste de inventario',
                    'debe'              => $monto,
                    'haber'             => null,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                Detalle::create([
                    'id_cuenta'         => $cuenta_ganancia->id,
                    'codigo'            => $cuenta_ganancia->codigo,
                    'nombre_cuenta'     => $cuenta_ganancia->nombre,
                    'concepto'          => 'Ganancia por ajuste de inventario',
                    'debe'              => null,
                    'haber'             => $monto,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

                        DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable creada exitosamente',
                'partida_id' => $partida->id,
                'debug_info' => [
                    'costo_unitario_utilizado' => $costo_unitario,
                    'cantidad_ajuste' => $ajusteCompleto->ajuste,
                    'monto_calculado' => $monto,
                    'costo_original_ajuste' => $ajusteCompleto->costo,
                    'costo_producto' => $ajusteCompleto->producto->costo,
                    'costo_promedio_producto' => $ajusteCompleto->producto->costo_promedio ?? null
                ]
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida: ' . $e->getMessage(), 500);
        }
    }
}

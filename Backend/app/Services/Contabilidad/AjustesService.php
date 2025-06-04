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
        $configuracion = Configuracion::first();
        $ajuste = Ajuste::with('producto', 'bodega')->where('id', $ajuste->id)->first();

        DB::beginTransaction();

        try {

            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($ajuste->created_at)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Ajuste de inventario - Conteo físico.',
                'estado'        => 'Pendiente',
                'referencia'    => 'Ajuste',
                'id_referencia' => $ajuste->id,
                'id_usuario'    => $ajuste->id_usuario,
                'id_empresa'    => $ajuste->id_empresa,
            ]);

            // Validar categoría
            $id_categoria = $ajuste->producto->id_categoria;
            if (!$id_categoria) {
                throw new \Exception('El producto no tiene categoría asignada');
            }

            // Buscar cuentas por categoría y sucursal
            $cuenta_categoria = CuentaCategoria::where('id_categoria', $id_categoria)
                                                        ->where('id_sucursal', $ajuste->bodega->id_sucursal)
                                                        ->first();
            
            if (!$cuenta_categoria) {
                throw new \Exception('No hay cuenta de categoría');
            }

            // Obtener cuenta de inventario
            $cuenta_inventario = Cuenta::find($cuenta_categoria->id_cuenta_contable_inventario);

            if (!$cuenta_inventario) {
                throw new \Exception('No hay cuenta de inventario');
            }

            // Determinar si el ajuste es positivo o negativo
            $monto = abs($ajuste->costo * $ajuste->ajuste);

            if ($ajuste->ajuste < 0) {
                // 🔻 FALTANTE → Disminuye el inventario, se registra una pérdida
                $cuenta_perdida = Cuenta::find($cuenta_categoria->id_cuenta_contable_perdida ?? $configuracion->id_cuenta_perdida_ajuste); // Asegúrate de tener este campo
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
                $cuenta_ganancia = Cuenta::find($cuenta_categoria->id_cuenta_contable_ganancia ?? $configuracion->id_cuenta_ganancia_ajuste); // Asegúrate de tener este campo
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

        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }



    }

}

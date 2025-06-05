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
        $configuracion = Configuracion::first();
        $traslado = Traslado::with('producto', 'origen', 'destino')->where('id', $traslado->id)->first();

        DB::beginTransaction();

        try {

            // Crear partida contable
            $partida = Partida::create([
                'fecha'         => Carbon::parse($traslado->created_at)->format('Y-m-d'),
                'tipo'          => 'Diario',
                'concepto'      => 'Traslado de inventario',
                'estado'        => 'Pendiente',
                'referencia'    => 'Traslado',
                'id_referencia' => $traslado->id,
                'id_usuario'    => $traslado->id_usuario,
                'id_empresa'    => $traslado->id_empresa,
            ]);

            // Validar categoría
            $id_categoria = $traslado->producto->id_categoria;
            if (!$id_categoria) {
                throw new \Exception('El producto no tiene categoría asignada');
            }

            // Cuenta origen
                $cuenta_categoria = CuentaCategoria::where('id_categoria', $id_categoria)
                                                ->where('id_sucursal', $traslado->origen->id_sucursal)
                                                ->first();
                
                if (!$cuenta_categoria) {
                    throw new \Exception('No hay cuenta de categoría del origen');
                }

                // Obtener cuenta de inventario
                $cuenta_inventario_origen = Cuenta::findOrFail($cuenta_categoria->id_cuenta_contable_inventario);

                if (!$cuenta_inventario_origen) {
                    throw new \Exception('No hay cuenta de inventario del origen');
                }
            // Cuenta destino
                $cuenta_categoria = CuentaCategoria::where('id_categoria', $id_categoria)
                                                ->where('id_sucursal', $traslado->destino->id_sucursal)
                                                ->first();
                
                if (!$cuenta_categoria) {
                    throw new \Exception('No hay cuenta de categoría del destino');
                }

                // Obtener cuenta de inventario
                $cuenta_inventario_destino = Cuenta::findOrFail($cuenta_categoria->id_cuenta_contable_inventario);

                if (!$cuenta_inventario_destino) {
                    throw new \Exception('No hay cuenta de inventario del destino');
                }

            Detalle::create([
                'id_cuenta'         => $cuenta_inventario_origen->id,
                'codigo'            => $cuenta_inventario_origen->codigo,
                'nombre_cuenta'     => $cuenta_inventario_origen->nombre,
                'concepto'          => 'Salida por traslado de inventario',
                'debe'              => $traslado->cantidad * $traslado->costo,
                'haber'             => null,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);
            Detalle::create([
                'id_cuenta'         => $cuenta_inventario_destino->id,
                'codigo'            => $cuenta_inventario_destino->codigo,
                'nombre_cuenta'     => $cuenta_inventario_destino->nombre,
                'concepto'          => 'Entrada por traslado de inventario',
                'debe'              => null,
                'haber'             => $traslado->cantidad * $traslado->costo,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);


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

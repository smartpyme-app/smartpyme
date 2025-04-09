<?php

namespace App\Imports;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TrasladosImport implements ToModel, WithHeadingRow, WithStartRow
{
    use Importable;

    protected $concepto;
    protected $trasladados = 0;
    protected $errores = [];

    public function __construct($concepto)
    {
        $this->concepto = $concepto;
    }

    public function startRow(): int
    {
        return 2; 
    }


    public function model(array $row)
    {
        Log::info('Fila procesada para traslado:', $row);

        try {
            if (!isset($row['id_producto']) || !isset($row['cantidad_a_trasladar'])) {
                Log::warning('Claves faltantes para traslado. Claves disponibles:', array_keys($row));
                //$this->errores[] = "Falta información necesaria en alguna fila del archivo.";
                return null;
            }

            $idProducto = $row['id_producto'];
            $idBodegaOrigen = $row['id_bodega_origen'];
            $idBodegaDestino = $row['id_bodega_destino'];
            $cantidadTraslado = floatval($row['cantidad_a_trasladar']);
            if ($cantidadTraslado <= 0) {
                Log::info("Cantidad de traslado debe ser mayor a cero para el producto {$idProducto}");
                return null;
            }

            // Localizar inventarios en origen y destino
            $inventarioOrigen = Inventario::where('id_producto', $idProducto)
                ->where('id_bodega', $idBodegaOrigen)
                ->first();

            $inventarioDestino = Inventario::where('id_producto', $idProducto)
                ->where('id_bodega', $idBodegaDestino)
                ->first();

            if (!$inventarioOrigen) {
                Log::warning("No se encontró inventario en bodega origen para producto {$idProducto}");
                $this->errores[] = "No se encontró inventario en bodega origen para producto ID {$idProducto}";
                return null;
            }

            // Verificar stock suficiente
            if ($inventarioOrigen->stock < $cantidadTraslado) {
                Log::warning("Stock insuficiente en origen para producto {$idProducto}. Disponible: {$inventarioOrigen->stock}, Solicitado: {$cantidadTraslado}");
                $this->errores[] = "Stock insuficiente en origen para producto ID {$idProducto}";
                return null;
            }

            // Buscar el producto para el registro
            $producto = Producto::find($idProducto);
            if (!$producto) {
                Log::warning("No se encontró el producto con ID: {$idProducto}");
                $this->errores[] = "No se encontró el producto con ID: {$idProducto}";
                return null;
            }

            // Iniciar transacción
            DB::beginTransaction();

            try {
                // Registrar el traslado
                $traslado = new Traslado();
                $traslado->id_producto = $idProducto;
                $traslado->id_bodega_de = $idBodegaOrigen;
                $traslado->id_bodega = $idBodegaDestino;
                $traslado->concepto = $this->concepto;
                $traslado->cantidad = $cantidadTraslado;
                $traslado->id_usuario = Auth::id();
                $traslado->id_empresa = Auth::user()->id_empresa;
                $traslado->estado = 'Confirmado';
                $traslado->save();

                // Actualizar inventario de origen
                $inventarioOrigen->stock -= $cantidadTraslado;
                $inventarioOrigen->save();
                $inventarioOrigen->kardex($traslado, $cantidadTraslado * -1);

                // Actualizar o crear inventario de destino
                if ($inventarioDestino) {
                    $inventarioDestino->stock += $cantidadTraslado;
                    $inventarioDestino->save();
                    $inventarioDestino->kardex($traslado, $cantidadTraslado);
                } else {
                    // Crear nuevo inventario en destino si no existe
                    $inventarioDestino = new Inventario();
                    $inventarioDestino->id_producto = $idProducto;
                    $inventarioDestino->id_bodega = $idBodegaDestino;
                    $inventarioDestino->stock = $cantidadTraslado;
                    $inventarioDestino->save();
                    $inventarioDestino->kardex($traslado, $cantidadTraslado);
                }

                // Verificar composiciones del producto
                foreach ($producto->composiciones as $composicion) {
                    $productoCompuesto = Producto::where('id', $composicion->id_compuesto)->first();

                    if (!$productoCompuesto) {
                        throw new \Exception("No se encontró el producto compuesto ID {$composicion->id_compuesto}");
                    }

               
                    $cantidadCompuesto = $cantidadTraslado * $composicion->cantidad;

                    // Buscar inventarios del componente
                    $inventarioCompuestoOrigen = Inventario::where('id_producto', $composicion->id_compuesto)
                        ->where('id_bodega', $idBodegaOrigen)
                        ->first();

                    $inventarioCompuestoDestino = Inventario::where('id_producto', $composicion->id_compuesto)
                        ->where('id_bodega', $idBodegaDestino)
                        ->first();

                    if (!$inventarioCompuestoOrigen) {
                        throw new \Exception("No se encontró inventario para el componente {$productoCompuesto->nombre} en bodega origen");
                    }

                    if ($inventarioCompuestoOrigen->stock < $cantidadCompuesto) {
                        throw new \Exception("Stock insuficiente para el componente {$productoCompuesto->nombre} en bodega origen");
                    }

               
                    $inventarioCompuestoOrigen->stock -= $cantidadCompuesto;
                    $inventarioCompuestoOrigen->save();
                    $inventarioCompuestoOrigen->kardex($traslado, $cantidadCompuesto * -1);

                    
                    if ($inventarioCompuestoDestino) {
                        $inventarioCompuestoDestino->stock += $cantidadCompuesto;
                        $inventarioCompuestoDestino->save();
                        $inventarioCompuestoDestino->kardex($traslado, $cantidadCompuesto);
                    } else {
                        $inventarioCompuestoDestino = new Inventario();
                        $inventarioCompuestoDestino->id_producto = $composicion->id_compuesto;
                        $inventarioCompuestoDestino->id_bodega = $idBodegaDestino;
                        $inventarioCompuestoDestino->stock = $cantidadCompuesto;
                        $inventarioCompuestoDestino->save();
                        $inventarioCompuestoDestino->kardex($traslado, $cantidadCompuesto);
                    }
                }

                DB::commit();
                $this->trasladados++;

                Log::info("Producto {$producto->nombre} trasladado. Cantidad: {$cantidadTraslado}");
            } catch (\Exception $e) {
                DB::rollback();
                Log::error("Error en traslado: " . $e->getMessage());
                $this->errores[] = "Error en traslado de producto ID {$idProducto}: " . $e->getMessage();
            }
        } catch (\Exception $e) {
            Log::error("Error procesando fila: " . $e->getMessage(), $row);
            $this->errores[] = "Error general: " . $e->getMessage();
        }

        return null;
    }


    public function getTrasladados(): int
    {
        return $this->trasladados;
    }

    /**
     * @return array
     */
    public function getErrores(): array
    {
        return $this->errores;
    }
}

<?php

namespace App\Services\Contabilidad;

use App\Models\Compras\Retaceo\Retaceo;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Exception;

class RetaceoService
{
    public function crearPartida($id_retaceo)
    {
        $configuracion = Configuracion::firstOrFail();
        $retaceo = Retaceo::with('distribucion', 'gastos', 'compra')->findOrFail($id_retaceo);

        if ($retaceo->estado !== 'Aplicado') {
            throw new Exception('El retaceo debe estar en estado Aplicado para generar la partida contable.', 400);
        }

        // Verificar que no exista una partida contable para este retaceo
        $partidaExistente = Partida::where('referencia', 'Retaceo')
                                  ->where('id_referencia', $retaceo->id)
                                  ->first();
        if ($partidaExistente) {
            throw new Exception('Ya existe una partida contable para este retaceo.', 400);
        }

        DB::beginTransaction();

        try {
            // Crear la partida principal del retaceo
            $partida = Partida::create([
                'fecha'         => $retaceo->fecha,
                'tipo'          => 'Ajuste',
                'concepto'      => 'Retaceo de importación. DUCA #' . $retaceo->numero_duca . ' - Factura #' . $retaceo->numero_factura,
                'estado'        => 'Pendiente',
                'referencia'    => 'Retaceo',
                'id_referencia' => $retaceo->id,
                'id_usuario'    => $retaceo->id_usuario,
                'id_empresa'    => $retaceo->id_empresa,
            ]);

            // DEBE: Incrementar el inventario por el monto de los gastos distribuidos
            foreach ($retaceo->distribucion as $distribucion) {
                $montoIncremento = $distribucion->costo_landed - $distribucion->valor_fob;

                if ($montoIncremento > 0) {
                    // Buscar la cuenta contable específica de la categoría del producto
                    $producto = $distribucion->producto;

                    if ($producto && $producto->id_categoria) {
                        $cuentaCategoria = CuentaCategoria::where('id_categoria', $producto->id_categoria)
                                                         ->where('id_sucursal', $retaceo->id_sucursal)
                                                         ->first();

                        if ($cuentaCategoria && $cuentaCategoria->id_cuenta_contable_inventario) {
                            $cuenta = Cuenta::findOrFail($cuentaCategoria->id_cuenta_contable_inventario);
                        } else {
                            $cuenta = Cuenta::findOrFail($configuracion->id_cuenta_inventario);
                        }
                    } else {
                        $cuenta = Cuenta::findOrFail($configuracion->id_cuenta_inventario);
                    }

                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Incremento de inventario por retaceo - ' . $producto->nombre,
                        'debe'              => $montoIncremento,
                        'haber'             => null,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }
            }

            // HABER: Registrar los gastos del retaceo
            foreach ($retaceo->gastos as $gasto) {
                if ($gasto->monto > 0) {
                    // Determinar la cuenta contable según el tipo de gasto
                    $cuentaGasto = $this->obtenerCuentaGasto($gasto->tipo_gasto, $configuracion);

                    if ($cuentaGasto) {
                        Detalle::create([
                            'id_cuenta'         => $cuentaGasto->id,
                            'codigo'            => $cuentaGasto->codigo,
                            'nombre_cuenta'     => $cuentaGasto->nombre,
                            'concepto'          => 'Gasto de importación - ' . $gasto->tipo_gasto,
                            'debe'              => null,
                            'haber'             => $gasto->monto,
                            'saldo'             => 0,
                            'id_partida'        => $partida->id
                        ]);
                    }
                }
            }

            // Si no hay gastos específicos, usar CxP por el total
            if ($retaceo->gastos->isEmpty() && $retaceo->total_gastos > 0) {
                $cuentaCxP = Cuenta::findOrFail($configuracion->id_cuenta_cxp);

                Detalle::create([
                    'id_cuenta'         => $cuentaCxP->id,
                    'codigo'            => $cuentaCxP->codigo,
                    'nombre_cuenta'     => $cuentaCxP->nombre,
                    'concepto'          => 'Gastos de importación pendientes de pago',
                    'debe'              => null,
                    'haber'             => $retaceo->total_gastos,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'mensaje' => 'Partida contable generada correctamente',
                'partida' => $partida
            ];

        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception('Error al generar partida contable: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Obtener la cuenta contable según el tipo de gasto
     */
    private function obtenerCuentaGasto($tipoGasto, $configuracion)
    {
        // Mapear tipos de gasto a cuentas contables
        // Esto puede ser configurado según las necesidades contables de la empresa
        switch (strtolower($tipoGasto)) {
            case 'transporte':
                // Buscar cuenta específica de gastos de transporte
                return Cuenta::where('nombre', 'like', '%transporte%')
                           ->orWhere('nombre', 'like', '%flete%')
                           ->first() ?? Cuenta::findOrFail($configuracion->id_cuenta_cxp);

            case 'seguro':
                // Buscar cuenta específica de seguros
                return Cuenta::where('nombre', 'like', '%seguro%')
                           ->first() ?? Cuenta::findOrFail($configuracion->id_cuenta_cxp);

            case 'dai':
                // Buscar cuenta específica de DAI/Aranceles
                return Cuenta::where('nombre', 'like', '%dai%')
                           ->orWhere('nombre', 'like', '%arancel%')
                           ->first() ?? Cuenta::findOrFail($configuracion->id_cuenta_cxp);

            case 'otros':
            default:
                // Usar cuenta por pagar por defecto
                return Cuenta::findOrFail($configuracion->id_cuenta_cxp);
        }
    }

    /**
     * Crear partidas contables siguiendo el patrón específico del cliente
     * Usa cuenta "Pedido en Transito" y agrupa gastos por proveedor
     */
    public function crearPartidaEstiloCliente($id_retaceo)
    {
        $configuracion = Configuracion::firstOrFail();
        $retaceo = Retaceo::with('distribucion', 'gastos', 'compra')->findOrFail($id_retaceo);

        if ($retaceo->estado !== 'Aplicado') {
            throw new Exception('El retaceo debe estar en estado Aplicado para generar la partida contable.', 400);
        }

        // Verificar que no exista una partida contable para este retaceo
        $partidaExistente = Partida::where('referencia', 'Retaceo-Cliente')
                                  ->where('id_referencia', $retaceo->id)
                                  ->first();
        if ($partidaExistente) {
            throw new Exception('Ya existe una partida contable estilo cliente para este retaceo.', 400);
        }

        if (!$configuracion->id_cuenta_pedidos_transito) {
            throw new Exception('Debe configurar la cuenta de Pedidos en Transito en la configuración contable.', 400);
        }

        DB::beginTransaction();

        try {
            $cuentaPedidosTransito = Cuenta::findOrFail($configuracion->id_cuenta_pedidos_transito);
            $partidaNumero = 1;

            // Agrupar gastos por tipo/proveedor según el patrón del cliente
            $grupos = $this->agruparGastosEstiloCliente($retaceo);

            foreach ($grupos as $grupo) {
                $partida = Partida::create([
                    'fecha'         => $retaceo->fecha,
                    'tipo'          => 'Egreso',
                    'concepto'      => $grupo['concepto'],
                    'estado'        => 'Pendiente',
                    'referencia'    => 'Retaceo-Cliente',
                    'id_referencia' => $retaceo->id,
                    'id_usuario'    => $retaceo->id_usuario,
                    'id_empresa'    => $retaceo->id_empresa,
                ]);

                $totalDebe = 0;

                // DEBE: Pedido en Transito por cada gasto del grupo
                foreach ($grupo['gastos'] as $gasto) {
                    if ($gasto['monto'] > 0) {
                        Detalle::create([
                            'id_cuenta'         => $cuentaPedidosTransito->id,
                            'codigo'            => $cuentaPedidosTransito->codigo,
                            'nombre_cuenta'     => $cuentaPedidosTransito->nombre,
                            'concepto'          => $gasto['concepto_detalle'],
                            'debe'              => $gasto['monto'],
                            'haber'             => null,
                            'saldo'             => 0,
                            'id_partida'        => $partida->id
                        ]);
                        $totalDebe += $gasto['monto'];
                    }
                }

                // DEBE: IVA si aplica
                if (isset($grupo['iva']) && $grupo['iva']['monto'] > 0) {
                    $cuentaIva = Cuenta::findOrFail($grupo['iva']['id_cuenta']);
                    Detalle::create([
                        'id_cuenta'         => $cuentaIva->id,
                        'codigo'            => $cuentaIva->codigo,
                        'nombre_cuenta'     => $cuentaIva->nombre,
                        'concepto'          => $grupo['iva']['concepto'],
                        'debe'              => $grupo['iva']['monto'],
                        'haber'             => null,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                    $totalDebe += $grupo['iva']['monto'];
                }

                // HABER: Proveedor específico
                if ($grupo['proveedor']['id_cuenta'] && $totalDebe > 0) {
                    $cuentaProveedor = Cuenta::findOrFail($grupo['proveedor']['id_cuenta']);
                    Detalle::create([
                        'id_cuenta'         => $cuentaProveedor->id,
                        'codigo'            => $cuentaProveedor->codigo,
                        'nombre_cuenta'     => $cuentaProveedor->nombre,
                        'concepto'          => $grupo['proveedor']['concepto'],
                        'debe'              => null,
                        'haber'             => $totalDebe,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                $partidaNumero++;
            }

            DB::commit();

            return [
                'success' => true,
                'mensaje' => 'Partidas contables estilo cliente generadas correctamente',
                'partidas_creadas' => $partidaNumero - 1
            ];

        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception('Error al generar partidas estilo cliente: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Agrupar gastos siguiendo el patrón del cliente
     * Replicando exactamente la estructura mostrada en el ejemplo
     */
    private function agruparGastosEstiloCliente($retaceo)
    {
        $configuracion = Configuracion::firstOrFail();
        $grupos = [];

        // Debug: Log para ver qué gastos tenemos
        \Log::info('Datos del retaceo:', [
            'gastos' => $retaceo->gastos->toArray(),
            'distribucion' => $retaceo->distribucion->toArray()
        ]);

        // Obtener gastos organizados
        $gastoTransporte = $retaceo->gastos->where('tipo_gasto', 'Transporte')->first();
        $gastoSeguro = $retaceo->gastos->where('tipo_gasto', 'Seguro')->first();
        $gastoOtro = $retaceo->gastos->where('tipo_gasto', 'Otro')->first();

        $valorFobTotal = $retaceo->distribucion->sum('valor_fob');

        // PARTIDA 1: Desembolso inicial (FOB + Otros gastos) → Banco
        // Siguiendo el patrón: "Desembolso para compra de Empaque" del cliente
        $montoDesembolso = $valorFobTotal;
        if ($gastoOtro) {
            $montoDesembolso += $gastoOtro->monto;
        }

        if ($montoDesembolso > 0) {
            $gastosDesembolso = [];

            // Agregar FOB como desembolso base
            $gastosDesembolso[] = [
                'monto' => $valorFobTotal,
                'concepto_detalle' => 'Desembolso para compra de Empaque'
            ];

            // Agregar otros gastos si existen
            if ($gastoOtro && $gastoOtro->monto > 0) {
                $gastosDesembolso[] = [
                    'monto' => $gastoOtro->monto,
                    'concepto_detalle' => 'Otros gastos'
                ];
            }

            $grupos[] = [
                'concepto' => 'Desembolso para compra de Empaque - DUCA #' . $retaceo->numero_duca,
                'gastos' => $gastosDesembolso,
                'proveedor' => [
                    'id_cuenta' => $configuracion->id_cuenta_cxp,
                    'concepto' => 'Banco Cuscatlan'
                ]
            ];
        }

        // PARTIDA 2: Servicios de transporte → Empresa de Carga
        // Siguiendo el patrón: "Pago de Flete por transporte Aereo Global Cargo"
        if ($gastoTransporte && $gastoTransporte->monto > 0) {
            $grupos[] = [
                'concepto' => 'Pago de Flete por transporte Aereo - DUCA #' . $retaceo->numero_duca,
                'gastos' => [
                    [
                        'monto' => $gastoTransporte->monto,
                        'concepto_detalle' => 'Pago de Flete por transporte Aereo Global Cargo'
                    ]
                ],
                // Sin IVA automático - solo si está en los datos reales
                'proveedor' => [
                    'id_cuenta' => $configuracion->id_cuenta_cxp,
                    'concepto' => 'Global Cargo de El Salvador, S.A. de C.V.'
                ]
            ];
        }

        // PARTIDA 3: Seguros e impuestos aduanales → Aduanas
        // Siguiendo el patrón: "Gastos de seguro DUCA" + impuestos
        $gastosAduanales = [];

        if ($gastoSeguro && $gastoSeguro->monto > 0) {
            $gastosAduanales[] = [
                'monto' => $gastoSeguro->monto,
                'concepto_detalle' => 'Gastos de seguro DUCA'
            ];
        }

        // Solo agregar DAI si la tasa es mayor a 0
        if ($retaceo->tasa_dai > 0 && $valorFobTotal > 0) {
            $montoDAI = $valorFobTotal * ($retaceo->tasa_dai / 100);
            $gastosAduanales[] = [
                'monto' => $montoDAI,
                'concepto_detalle' => 'Pago de Impuestos Arancel DUCA'
            ];
        }

        // Solo crear la partida si hay gastos aduanales
        if (!empty($gastosAduanales)) {
            $grupos[] = [
                'concepto' => 'Pago impuestos y seguros DUCA #' . $retaceo->numero_duca,
                'gastos' => $gastosAduanales,
                // Sin IVA automático - solo si está en los datos reales
                'proveedor' => [
                    'id_cuenta' => $configuracion->id_cuenta_cxp,
                    'concepto' => 'Direccion General de Aduanas'
                ]
            ];
        }

        // Debug: Log para ver los grupos generados
        \Log::info('Grupos generados para retaceo:', [
            'total_grupos' => count($grupos),
            'grupos' => $grupos
        ]);

        return $grupos;
    }
}

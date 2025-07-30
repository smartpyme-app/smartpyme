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
    /**
     * Crear partidas contables siguiendo el patrón específico del cliente
     * Usa cuenta "Pedido en Transito" y agrupa gastos por proveedor
     */
    public function crearPartida($id_retaceo)
    {
        // Validar que el ID del retaceo es válido
        if (!$id_retaceo) {
            throw new Exception('El ID del retaceo no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        $retaceo = Retaceo::with('distribucion', 'gastos', 'compra')->find($id_retaceo);
        if (!$retaceo) {
            throw new Exception('No se encontró el retaceo con ID: ' . $id_retaceo, 400);
        }

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

        if (!$configuracion->id_cuenta_pedidos_transito) {
            throw new Exception('Debe configurar la cuenta de Pedidos en Transito en la configuración contable.', 400);
        }

        DB::beginTransaction();

        try {
            $cuentaPedidosTransito = Cuenta::find($configuracion->id_cuenta_pedidos_transito);
            if (!$cuentaPedidosTransito) {
                throw new Exception('No se encontró la cuenta contable de Pedidos en Transito', 400);
            }

            $partidaNumero = 1;

            // Agrupar gastos por tipo/proveedor según el patrón del cliente
            $grupos = $this->agruparGastosEstiloCliente($retaceo);

            if (empty($grupos)) {
                throw new Exception('No se generaron grupos de gastos para procesar', 400);
            }

            foreach ($grupos as $grupo) {
                $partida = Partida::create([
                    'fecha'         => $retaceo->fecha,
                    'tipo'          => 'Egreso',
                    'concepto'      => $grupo['concepto'],
                    'estado'        => 'Pendiente',
                    'referencia'    => 'Retaceo',
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
                    $cuentaIva = Cuenta::find($grupo['iva']['id_cuenta']);
                    if (!$cuentaIva) {
                        throw new Exception('No se encontró la cuenta contable de IVA', 400);
                    }

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
                    $cuentaProveedor = Cuenta::find($grupo['proveedor']['id_cuenta']);
                    if (!$cuentaProveedor) {
                        throw new Exception('No se encontró la cuenta contable del proveedor', 400);
                    }

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
                'message' => 'Partidas contables de retaceo generadas correctamente',
                'partidas_creadas' => $partidaNumero - 1
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al generar partidas contables de retaceo: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al generar partidas contables de retaceo: ' . $e->getMessage(), 500);
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

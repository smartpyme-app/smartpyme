<?php

namespace App\Services\Contabilidad\Partidas;

use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Abono as AbonoCompra;
use App\Models\Admin\FormaDePago;
use App\Services\Contabilidad\Partidas\PartidaService;
use Illuminate\Support\Facades\Log;

class PartidaEgresosService
{
    protected $partidaService;

    public function __construct(PartidaService $partidaService)
    {
        $this->partidaService = $partidaService;
    }

    /**
     * Genera una partida de egresos basada en compras y abonos de una fecha
     *
     * @param string $fecha
     * @return array
     */
    public function generarPartidaEgresos(string $fecha): array
    {
        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pagada')
                            ->where('fecha', $fecha)->get();
        $abonos_compras = AbonoCompra::where('estado', 'Confirmado')
                            ->where('fecha', $fecha)->with('compra')->get();

        $compras->each->setAttribute('tipo', 'compra');
        $abonos_compras->each(function ($abono) {
            $abono->tipo = 'abono';
            $abono->tipo_documento = $abono->compra ? $abono->compra->tipo_documento : null;
            $abono->referencia = $abono->compra ? $abono->compra->referencia : null;
        });

        $egresos = $compras->merge($abonos_compras);

        // Partida
        $partida = [
            'fecha' => $fecha,
            'tipo' => 'Egreso',
            'concepto' => 'Compra de mercancía',
            'estado' => 'Pendiente',
        ];

        // Detalles
        $detalles = [];
        $cuenta_compras = Cuenta::where('id', $configuracion->id_cuenta_compras)->first();
        $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
        $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
        $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_compra)->first();
        $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();
        $cuenta_renta_retenida = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();
        $cuenta_cxp = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();

        foreach ($egresos as $egreso) {
            $formapago = FormaDePago::with('banco')->where('nombre', $egreso->forma_pago)->first();

            if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                throw new \Exception('La forma de pago ' . $egreso->forma_pago . ' no tiene cuenta contable.');
            }

            $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->first();

            $detalles[] = [
                'id_cuenta'         => $cuenta->id,
                'codigo'            => $cuenta->codigo,
                'nombre_cuenta'     => $cuenta->nombre,
                'concepto' => 'Egresos por ' . $egreso->tipo . ' ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                'debe'              => NULL,
                'haber'             => $egreso->total,
                'saldo'             => 0
            ];

            if($egreso->tipo == 'compra'){
                $productos_compra = DetalleCompra::with('producto')->where('id_compra', $egreso->id)->get();

                foreach ($productos_compra as $detalle) {
                    $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                    if($id_categoria){
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $egreso->id_sucursal)->first();

                        if(!$cuenta_categoria_sucursal){
                            throw new \Exception('La categoria no tiene cuenta contable. Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();

                        if(!$cuenta){
                            throw new \Exception('La categoria no tiene cuenta contable. Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                            'debe' => $detalle->total,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_compras->id,
                            'codigo' => $cuenta_compras->codigo,
                            'nombre_cuenta' => $cuenta_compras->nombre,
                            'concepto' => 'Inventarios compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                            'debe' => $egreso->sub_total,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                        break;
                    }
                }

                if ($egreso->iva > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva->id,
                        'codigo' => $cuenta_iva->codigo,
                        'nombre_cuenta' => $cuenta_iva->nombre,
                        'concepto' => 'Compra de mercadería ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                        'debe' => $egreso->iva,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($egreso->percepcion > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Compra de mercadería ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                        'debe' => $egreso->percepcion,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($egreso->renta_retenida > 0) {
                    $detalles[] = [
                        'id_cuenta'         => $cuenta_renta_retenida->id,
                        'codigo'            => $cuenta_renta_retenida->codigo,
                        'nombre_cuenta'     => $cuenta_renta_retenida->nombre,
                        'concepto' => 'Compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                        'debe'              => $egreso->renta_retenida,
                        'haber'             => NULL,
                        'saldo'             => 0,
                    ];
                }
            }
            else{
                $detalles[] = [
                    'id_cuenta' => $cuenta_cxp->id,
                    'codigo' => $cuenta_cxp->codigo,
                    'nombre_cuenta' => $cuenta_cxp->nombre,
                    'concepto' => 'Egreso por cxp ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                    'debe' => $egreso->total,
                    'haber' => NULL,
                    'saldo' => 0,
                ];
            }
        }

        return [
            'partida' => $partida,
            'detalles' => $detalles,
        ];
    }
}


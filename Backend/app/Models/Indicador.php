<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Abono;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Compras\Gastos\Gasto;
use Carbon\Carbon;

class Indicador extends Model
{
    use HasFactory;

    public $ventas;
    public $ventas_pagadas;
    public $cxc;
    public $cxp;
    public $recibos;
    public $compras;
    public $gastos;
    public $devoluciones_ventas;
    public $devoluciones_compras;
    public $ventas_anuladas;

    public $fillable = [
        'id_empresa',
        'inicio',
        'fin',
        'id_sucursal',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->ventas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', '!=', 'Pre-venta')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->ventas_pagadas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', 'Pagada')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->ventas_anuladas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', 'Anulada')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->devoluciones_ventas = DevolucionVenta::where('enable', '=', true)
                        ->whereHas('venta', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->when($this->sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            });
                        })
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->cxc = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', 'Pendiente')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->recibos = Abono::where('estado', 'Confirmado')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->whereHas('venta', function($q){
                            $q->when($this->sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            })->where('id_empresa', $this->id_empresa);
                        })->get();

        $this->compras = Compra::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', 'Pagada')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->devoluciones_compras = DevolucionCompra::where('enable', '=', true)
                        ->whereHas('compra', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->when($this->sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            });
                        })
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->cxp = Compra::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', 'Pendiente')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->gastos = Gasto::where('id_empresa', $this->id_empresa)
                        ->when($this->sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->where('estado', '!=', 'Cancelado')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();
    }

    public function getTotalVentasPagadas(){

        return $this->ventas_pagadas->sum('saldo');
    }

    public function getCantidadVentasPagadas(){

        return $this->ventas_pagadas->count();
    }


    public function getTotalRecibos(){

        return $this->recibos->sum('monto');
    }

    public function getCantidadRecibos(){

        return $this->recibos->count();
    }

    public function getTotalDevolucionesVenta(){

        return $this->devoluciones_ventas->sum('total');
    }

    public function getCantidadDevolucionesVenta(){

        return $this->devoluciones_ventas->count();
    }

    public function getTotalVentasPendientes(){

        return $this->cxc->sum('total_venta');
    }

    public function getCantidadVentasPendientes(){

        return $this->cxc->count();
    }

    public function getTotalComprasPagadas(){

        return $this->compras->sum('total_compra');
    }

    public function getTotalDevolucionesCompra(){

        return $this->devoluciones_compras->count();
    }

    public function getVentasAnuladas(){

        return $this->devoluciones_compras->count();
    }

    public function getCantidadDevolucionesCompra(){

        return $this->devoluciones_compras->sum('total');
    }

    public function getTotalComprasPendientes(){

        return $this->cxp->sum('total_compra');
    }

    public function getTotalGastosPagados(){

        return $this->gastos->where('estado', 'Confirmado')->sum('monto');
    }

    public function getCantidadGastosPagados(){

        return $this->gastos->where('estado', 'Confirmado')->count();
    }

    public function getTotalGastosPendientes(){

        return $this->gastos->where('estado', 'Pendiente')->sum('monto');
    }


    public function getVentasByCanal(){

        return $this->ventas_pagadas->groupBy('id_canal')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()->canal()->pluck('nombre')->first(),
                        'cantidad' => $group->count() - $this->devoluciones_ventas->where('id_canal', $group->first()['id_canal'])->count(),
                        'total' => $group->sum('saldo') - $this->devoluciones_ventas->where('id_canal', $group->first()['id_canal'])->sum('total'),
                    ];
                })->sortByDesc('total')->values()->all();
    }

    public function getVentasByFormaPago(){

        return $this->ventas_pagadas->groupBy('forma_pago')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['forma_pago'],
                        'cantidad' => $group->count() - $this->devoluciones_ventas->where('forma_pago', $group->first()['forma_pago'])->count(),
                        'total' => $group->sum('saldo') - $this->devoluciones_ventas->where('forma_pago', $group->first()['forma_pago'])->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();
    }

    public function getResumenCaja(){

        $caja = collect();

        $formasDePago = FormaDePago::where('id_empresa', $this->id_empresa)
                        ->orderBy('id', 'asc')->get();

        foreach ($formasDePago as $forma) {
            $forma->cantidad = $this->ventas_pagadas->where('forma_pago', $forma['nombre'])->count() 
                                + $this->gastos->where('forma_pago', $forma['nombre'])->count()
                                + $this->recibos->where('forma_pago', $forma['nombre'])->count()
                                - $this->devoluciones_ventas->where('forma_pago', $forma['nombre'])->count();
            $forma->total = $this->ventas_pagadas->where('forma_pago', $forma['nombre'])->sum('saldo') 
                                + $this->gastos->where('forma_pago', $forma['nombre'])->sum('monto')
                                + $this->recibos->where('forma_pago', $forma['nombre'])->sum('monto')
                                - $this->devoluciones_ventas->where('forma_pago', $forma['nombre'])->sum('total');
        }

        return $formasDePago;
    }

    public function getVentasByBanco(){

        return $this->ventas_pagadas->groupBy('detalle_banco')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['detalle_banco'],
                        'cantidad' => $group->count() - $this->devoluciones_ventas->where('detalle_banco', $group->first()['detalle_banco'])->count(),
                        'total' => $group->sum('saldo') - $this->devoluciones_ventas->where('detalle_banco', $group->first()['detalle_banco'])->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();
    }

    public function getDocumentoEmitidos(){

        return $this->ventas->groupBy('id_documento')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()->documento()->pluck('nombre')->first(),
                        'nombre_sucursal' => $group->first()->sucursal()->pluck('nombre')->first(),
                        'inicio' => $group->first()->correlativo,
                        'fin' => $group->last()->correlativo,
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total_venta'),
                    ];
                })->sortByDesc('id')->values()->all();
    }

    public function getDocumentoConDevolucion(){

        return $this->devoluciones_ventas;
    }

    public function getDocumentosAnulados(){

        return $this->ventas_anuladas;
    }

}

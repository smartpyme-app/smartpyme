<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Ventas\Venta;
use App\Models\Ventas\MetodoDePago;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Abono;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Admin\FormaDePago;
use Carbon\Carbon;

class Indicador extends Model
{
    use HasFactory;

    public $ventas;
    public $ventas_pagadas;
    public $cxc;
    public $cxp;
    public $abonos;
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
        'id_usuario',
        'id_canal',
    ];

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->detalles_metodos_de_pago = MetodoDePago::whereHas('venta', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->where('estado', 'Pagada')
                            ->whereDoesntHave('devoluciones')
                            ->when($this->id_sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            })
                            ->when($this->id_usuario, function($q){
                                $q->where('id_usuario', $this->id_usuario);
                            })
                            ->when($this->id_canal, function($q){
                                $q->where('id_canal', $this->id_canal);
                            })
                            ->where('cotizacion', 0)
                            ->whereBetween('fecha', [$this->inicio, $this->fin]);
                        })
                        ->get();

        $this->ventas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->when($this->id_canal, function($q){
                            $q->where('id_canal', $this->id_canal);
                        })
                        ->where('cotizacion', 0)
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->ventas_pagadas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->when($this->id_canal, function($q){
                            $q->where('id_canal', $this->id_canal);
                        })
                        ->where('cotizacion', 0)
                        ->where('estado', 'Pagada')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->ventas_anuladas = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->when($this->id_canal, function($q){
                            $q->where('id_canal', $this->id_canal);
                        })
                        ->where('cotizacion', 0)
                        ->where('estado', 'Anulada')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->devoluciones_ventas = DevolucionVenta::where('enable', '=', true)
                        ->whereHas('venta', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->when($this->id_sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            })
                            ->when($this->id_usuario, function($q){
                                $q->where('id_usuario', $this->id_usuario);
                            })
                            ->when($this->id_canal, function($q){
                                $q->where('id_canal', $this->id_canal);
                            });
                        })
                        ->with('venta')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->cxc = Venta::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->when($this->id_canal, function($q){
                            $q->where('id_canal', $this->id_canal);
                        })
                        ->where('estado', 'Pendiente')
                        ->where('cotizacion', 0)
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->abonos = Abono::where('estado', 'Confirmado')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->whereHas('venta', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->when($this->id_sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            })
                            ->when($this->id_usuario, function($q){
                                $q->where('id_usuario', $this->id_usuario);
                            })
                            ->when($this->id_canal, function($q){
                                $q->where('id_canal', $this->id_canal);
                            });
                        })->get();

        $this->compras = Compra::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->where('estado', 'Pagada')
                        ->where('cotizacion', 0)
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->devoluciones_compras = DevolucionCompra::where('enable', '=', true)
                        ->whereHas('compra', function($q){
                            $q->where('id_empresa', $this->id_empresa)
                            ->when($this->id_sucursal, function($q){
                                $q->where('id_sucursal', $this->id_sucursal);
                            })
                            ->when($this->id_usuario, function($q){
                                $q->where('id_usuario', $this->id_usuario);
                            });
                        })
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->cxp = Compra::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->where('estado', 'Pendiente')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();

        $this->gastos = Gasto::where('id_empresa', $this->id_empresa)
                        ->when($this->id_sucursal, function($q){
                            $q->where('id_sucursal', $this->id_sucursal);
                        })
                        ->when($this->id_usuario, function($q){
                            $q->where('id_usuario', $this->id_usuario);
                        })
                        ->where('estado', '!=', 'Cancelado')
                        ->whereBetween('fecha', [$this->inicio, $this->fin])
                        ->get();
    }

    public function getTotalVentasPagadas(){

        return $this->ventas_pagadas->sum('total');
    }

    public function getTotalPropina(){
        return $this->ventas_pagadas->sum('propina');
    }

    public function getCantidadPropina(){
        return $this->ventas_pagadas->where('propina', '>', 0)->count();
    }

    public function getCantidadVentasPagadas(){

        return $this->ventas_pagadas->count();
    }


    public function getTotalRecibos(){

        return $this->abonos->sum('total');
    }

    public function getCantidadRecibos(){

        return $this->abonos->count();
    }

    public function getTotalDevolucionesVenta(){

        return $this->devoluciones_ventas->sum('total');
    }

    public function getCantidadDevolucionesVenta(){

        return $this->devoluciones_ventas->count();
    }

    public function getTotalVentasPendientes(){

        return $this->cxc->sum('total');
    }

    public function getCantidadVentasPendientes(){

        return $this->cxc->count();
    }

    public function getCantidadComprasPagadas(){

        return $this->compras->count();
    }

    public function getTotalComprasPagadas(){

        return $this->compras->sum('total');
    }

    public function getTotalDevolucionesCompra(){

        return $this->devoluciones_compras->sum('total');
    }

    public function getVentasAnuladas(){

        return $this->devoluciones_compras->count();
    }

    public function getCantidadDevolucionesCompra(){

        return $this->devoluciones_compras->count();
    }

    public function getTotalComprasPendientes(){

        return $this->cxp->sum('total');
    }

    public function getCantidadComprasPendientes(){

        return $this->cxp->count();
    }

    public function getTotalGastosPagados(){

        return $this->gastos->whereIn('estado', ['Confirmado', 'Pagado'])->sum('total');
    }

    public function getCantidadGastosPagados(){

        return $this->gastos->whereIn('estado', ['Confirmado', 'Pagado'])->count();
    }

    public function getCantidadGastosPendientes(){

        return $this->gastos->where('estado', 'Pendiente')->count();
    }

    public function getTotalGastosPendientes(){

        return $this->gastos->where('estado', 'Pendiente')->sum('total');
    }

    public function getCantidadTransacciones(){
        return $this->getCantidadVentasPagadas()
                + $this->getCantidadVentasPendientes()
                + $this->getCantidadDevolucionesVenta();
    }

    public function getTotalVentas(){
        return $this->getTotalVentasPagadas()
                + $this->getTotalVentasPendientes();
                // - $this->getTotalDevolucionesVenta();
    }

    public function getCantidadGastos(){
        return $this->getCantidadComprasPagadas() 
                + $this->getCantidadGastosPagados()
                + $this->getCantidadComprasPendientes()
                + $this->getCantidadGastosPendientes()
                - $this->getCantidadDevolucionesCompra();
    }

    public function getTotalGastos(){
        return $this->getTotalComprasPagadas() 
                + $this->getTotalGastosPagados()
                + $this->getTotalComprasPendientes()
                + $this->getTotalGastosPendientes()
                - $this->getTotalDevolucionesCompra();
    }

    public function getTotalResultados(){

        return $this->getTotalVentas() - $this->getTotalGastos();
    }

    public function getVentasByCanal(){

        return $this->ventas->where('estado', '!=', 'Anulada')->groupBy('id_canal')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()->canal()->pluck('nombre')->first(),
                        'cantidad' => $group->count() - $this->devoluciones_ventas->where('id_canal', $group->first()['id_canal'])->count(),
                        'total' => $group->sum('total') - $this->devoluciones_ventas->where('id_canal', $group->first()['id_canal'])->sum('total'),
                    ];
                })->sortByDesc('total')->values()->all();
    }

    public function getVentasByFormaPago(){

        $formasDePago = [];

        $ventas = $this->ventas_pagadas->where('forma_pago', '!=', 'Multiple')->groupBy('forma_pago')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['forma_pago'],
                        'cantidad' => $group->count() - $this->devoluciones_ventas->where('forma_pago', $group->first()['forma_pago'])->count(),
                        'total' => $group->sum('total') - $this->devoluciones_ventas->where('forma_pago', $group->first()['forma_pago'])->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();

        $detalles = $this->detalles_metodos_de_pago->groupBy('nombre')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['nombre'],
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();

        $ventasYDetalles = collect(array_merge($ventas, $detalles));

        $resultado = $ventasYDetalles->groupBy('nombre')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['nombre'],
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();

        return $resultado;
    }

    public function getAbonosByFormaPago(){

        return $this->abonos->groupBy('forma_pago')->map(function ($group) {
                    return [
                        'id' => $group->first()['id'],
                        'nombre' => $group->first()['forma_pago'],
                        'cantidad' => $group->count(),
                        'total' => $group->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();
    }

    public function getResumenCaja(){

        $caja = collect();

        $formasDePago = FormaDePago::where('id_empresa', $this->id_empresa)
                        ->orderBy('id', 'asc')->get();

        foreach ($formasDePago as $forma) {
            $forma->cantidad = $this->ventas_pagadas->where('forma_pago', $forma['nombre'])->count() 
                                + $this->detalles_metodos_de_pago->where('nombre', $forma['nombre'])->count()
                                + $this->abonos->where('forma_pago', $forma['nombre'])->count()
                                - $this->devoluciones_ventas->where('forma_pago', $forma['nombre'])->count();
            
            $forma->total = $this->ventas_pagadas->where('forma_pago', $forma['nombre'])->sum('total') 
                                + $this->detalles_metodos_de_pago->where('nombre', $forma['nombre'])->sum('total')
                                + $this->abonos->where('forma_pago', $forma['nombre'])->sum('total')
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
                        'total' => $group->sum('total') - $this->devoluciones_ventas->where('detalle_banco', $group->first()['detalle_banco'])->sum('total'),
                    ];
                 })->sortByDesc('total')->values()->all();
    }

    public function getDocumentoEmitidos(){

        // Primero, ordenamos las ventas por correlativo antes de agruparlas
        $ventasOrdenadas = $this->ventas->where('estado', '!=', 'Anulada')->sortBy('correlativo');

        $documentos = $ventasOrdenadas->groupBy('id_documento')->map(function ($group) {
            return [
                'id' => $group->first()['id'],
                'nombre' => $group->first()->documento()->pluck('nombre')->first(),
                'nombre_sucursal' => $group->first()->sucursal()->pluck('nombre')->first(),
                'inicio' => $group->first()->correlativo,
                'fin' => $group->last()->correlativo,
                'cantidad' => $group->count(),
                'total' => $group->sum('total'),
                'documentos' => $group,
                'correlativo' => $group->first()->correlativo // Usamos el correlativo de inicio para ordenar después
            ];
        });

        // Ahora sí, ordenamos los grupos por el correlativo de inicio (de mayor a menor)
        return $documentos->sortByDesc('correlativo')->values()->all();
    }

    public function getDocumentoConDevolucion(){

        return $this->devoluciones_ventas;
    }

    public function getDocumentosAnulados(){

        return $this->ventas_anuladas;
    }

    public function getTotalesSalidas($tiempo = 'DAY', $fecha = null){
        $queryCompra = Compra::selectRaw($tiempo . '(fecha) as time')
            ->selectRaw('sum(total) as total')
            ->where('id_empresa', $this->id_empresa)
            ->when($this->id_sucursal, function ($q) {
                $q->where('id_sucursal', $this->id_sucursal);
            })
            ->where('created_at', '>=', $fecha)
            ->where('estado', 'Pagada')
            ->groupBy('time')
            ->orderBy('time')
            ->get()
            ->keyBy('time');

        $queryGasto = Gasto::selectRaw($tiempo . '(fecha) as time')
            ->selectRaw('sum(total) as total')
            ->where('id_empresa', $this->id_empresa)
            ->when($this->id_sucursal, function ($q) {
                $q->where('id_sucursal', $this->id_sucursal);
            })
            ->where('created_at', '>=', $fecha)
            ->whereIn('estado', ['Confirmado', 'Pagado'])
            ->groupBy('time')
            ->orderBy('time')
            ->get()
            ->keyBy('time');

        $times = $queryCompra->keys()->merge($queryGasto->keys())->unique()->sort()->values();
        $salidas = $times->map(function ($time) use ($queryCompra, $queryGasto) {
            $totalCompra = $queryCompra->get($time)->total ?? 0;
            $totalGasto = $queryGasto->get($time)->total ?? 0;
            return (object) [
                'time' => $time,
                'total' => $totalCompra + $totalGasto,
            ];
        })->values();

        if (count($salidas) == 0) {
            $salidas->push((object) ['time' => null, 'total' => 1]);
            $salidas->push((object) ['time' => null, 'total' => 1]);
        }

        if (count($salidas) == 1) {
            $salidas->prepend((object) ['time' => null, 'total' => 1]);
        }
        return $salidas;
    }

    public function getTotalesVentas($tiempo = 'DAY', $fecha = null){
        $ventas = Venta::selectRaw($tiempo . '(fecha) as time')
                                        ->selectRaw('sum(total) as total')
                                        ->groupBy('time')
                                        ->where('created_at', '>=', $fecha)
                                        ->orderBy('time')
                                        ->get();
        if (count($ventas) == 0) {
            $ventas->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
            $ventas->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }

        if (count($ventas) == 1) {
            $ventas->prepend(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }
        return $ventas;
    }

    public function getTotalesTransacciones($tiempo = 'DAY', $fecha = null){
        $transacciones = Venta::selectRaw($tiempo . '(fecha) as time')
                                        ->selectRaw('count(*) as total')
                                        ->groupBy('time')
                                        ->where('created_at', '>=', $fecha)
                                        ->orderBy('time')
                                        ->get();
        if (count($transacciones) == 0) {
            $transacciones->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
            $transacciones->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }

        if (count($transacciones) == 1) {
            $transacciones->prepend(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }
        return $transacciones;
    }

    public function getTotalesBalances($tiempo = 'DAY', $fecha = null){
        $balance = Venta::selectRaw($tiempo . '(fecha) as time')
                                        ->selectRaw('count(*) as total')
                                        ->groupBy('time')
                                        ->where('created_at', '>=', $fecha)
                                        ->orderBy('time')
                                        ->get();

        if (count($balance) == 0) {
            $balance->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
            $balance->push(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }

        if (count($balance) == 1) {
            $balance->prepend(['cantidad' => 1, 'id' => null, 'nombre' => '', 'total' => 1 ]);
        }
        return $balance;
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

}

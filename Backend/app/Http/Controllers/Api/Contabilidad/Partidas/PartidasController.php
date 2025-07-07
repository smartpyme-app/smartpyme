<?php

namespace App\Http\Controllers\Api\Contabilidad\Partidas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Abono as AbonoVenta;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Abono as AbonoCompra;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use App\Services\Contabilidad\CierreMesService;
use App\Services\Contabilidad\SimulacionCierreService;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade as PDF;

class PartidasController extends Controller
{


    public function index(Request $request) {

        $partidas = Partida::with('detalles')->when($request->buscador, function($query) use ($request){
                                    return $query->where('concepto', 'like' ,'%' . $request->buscador . '%')
                                                ->orwhere('tipo', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

        $partidas = $partidas->toArray();
        $partidas['total_pendientes'] = Partida::where('estado', 'Pendiente')->count();

        return Response()->json($partidas, 200);

    }

    public function list() {

        $partidas = Partida::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($partidas, 200);

    }

    public function read($id) {

        $partida = Partida::with('detalles')->where('id', $id)->firstOrFail();
        return Response()->json($partida, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'tipo'          => 'required|max:255',
            'concepto'      => 'required|max:255',
            'estado'        => 'required|max:255',
            'detalles'      => 'required',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            if($request->id)
                $partida = Partida::findOrFail($request->id);
            else
                $partida = new Partida;


        $partida->fill($request->all());
        $partida->save();

            foreach ($request->detalles as $det) {
                if(isset($det['id'])) {
                    $detalle = Detalle::findOrFail($det['id']);
                    $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                }else {
                    $detalle = new Detalle;
                    $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                }

                $detalle['id_partida'] = $partida->id;
                $detalle->fill($det);
                $detalle['codigo'] = $cuenta->codigo;
                $detalle['nombre_cuenta'] = $cuenta->nombre;
                $detalle->save();

                $debe = $detalle->debe ? $detalle->debe : 0;
                $haber = $detalle->haber ? $detalle->haber : 0;

                // Aplicar partida
                if(($request['estado'] == 'Aplicada') && ($partida->estado != 'Aplicada')){
                    $detalle->cuenta->increment('cargo', $debe);
                    $detalle->cuenta->increment('abono', $haber);

                    if($detalle->cuenta->naturaleza == 'Deudor'){
                        $detalle->cuenta->increment('saldo', $debe - $haber);
                    }else{
                        $detalle->cuenta->increment('saldo', $haber - $debe);
                    }

                }

                // Anular aplicacion
                if(($request['estado'] != 'Aplicada') && ($partida->estado == 'Aplicada')){
                    $detalle->cuenta->decrement('cargo', $debe);
                    $detalle->cuenta->decrement('abono', $haber);
                    if($detalle->cuenta->naturaleza == 'Deudor'){
                        $detalle->cuenta->decrement('saldo', $debe - $haber);
                    }else{
                        $detalle->cuenta->decrement('saldo', $haber - $debe);
                    }
                }
            }


            DB::commit();
            return Response()->json($partida, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function generarIngresos(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $ventas = Venta::where('estado','!=', 'Anulada')
                        ->where('fecha', $request->fecha)->get();
        $abonos_ventas = AbonoVenta::where('estado', 'Confirmado')
                        ->where('fecha', $request->fecha)->with('venta')->get();

        $ventas->each->setAttribute('tipo', 'venta');
        $abonos_ventas->each(function ($abono) {
            $abono->tipo = 'abono';
            $abono->nombre_documento = $abono->venta ? $abono->venta->nombre_documento : null;
            $abono->correlativo = $abono->venta ? $abono->venta->correlativo : null;
        });

        $ingresos = $ventas->merge($abonos_ventas);

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas',
                'estado' => 'Pendiente',
            ];

        // Detalles

            $detalles = [];
            $cuenta_ventas = Cuenta::where('id', $configuracion->id_cuenta_ventas)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();
            $cuenta_cxc = Cuenta::where('id', $configuracion->id_cuenta_cxc)->first();

            foreach ($ingresos as $ingreso) {

                $formapago = FormaDePago::with('banco')->where('nombre', $ingreso->forma_pago)->first();

                if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                    return  Response()->json(['titulo' => 'La forma de pago ' . $ingreso->forma_pago . ' no tiene cuenta contable.', 'error' => 'Venta: ' . $ingreso->nombre_documento . ' #' . $ingreso->correlativo, 'code' => 400], 400);
                }

                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->first();

                $detalles[] = [
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ' . $ingreso->tipo . ' ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                    'debe' => $ingreso->total,
                    'haber' => NULL,
                    'saldo' => 0,
                ];

                if($ingreso->tipo == 'venta'){

                    $productos_venta = DetalleVenta::with('producto')->where('id_venta', $ingreso->id)->get();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $ingreso->id_sucursal)->first();

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();

                            if(!$cuenta){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta->id,
                                'codigo' => $cuenta->codigo,
                                'nombre_cuenta' => $cuenta->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $detalle->total,
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_ventas->id,
                                'codigo' => $cuenta_ventas->codigo,
                                'nombre_cuenta' => $cuenta_ventas->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $ingreso->sub_total,
                                'saldo' => 0,
                                'productos' => $productos_venta,
                            ];
                            break;
                        }
                    }

                    if ($ingreso->iva > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva->id,
                            'codigo' => $cuenta_iva->codigo,
                            'nombre_cuenta' => $cuenta_iva->nombre,
                            'concepto' => '  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                            'debe' => NULL,
                            'haber' => $ingreso->iva,
                            'saldo' => 0,
                        ];
                    }

                    if ($ingreso->iva_retenido > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva_retenido->id,
                            'codigo' => $cuenta_iva_retenido->codigo,
                            'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                            'concepto' => '  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                            'debe' => $ingreso->iva_retenido,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }
                }
                else{
                    $detalles[] = [
                        'id_cuenta' => $cuenta_cxc->id,
                        'codigo' => $cuenta_cxc->codigo,
                        'nombre_cuenta' => $cuenta_cxc->nombre,
                        'concepto' => 'Ingreso por abono ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                        'debe' => NULL,
                        'haber' => $ingreso->total,
                        'saldo' => 0,
                        'productos' => $productos_venta,
                    ];
                }


                // Costo de venta
                if ($ingreso->tipo == 'venta') {
                    $productos_venta = DetalleVenta::with('producto')->where('id_venta', $ingreso->id)->get();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $ingreso->id_sucursal)->first();

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->first();

                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => number_format($detalle->costo * $detalle->cantidad,2),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];

                            $cuenta_inventarios = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => number_format($detalle->costo * $detalle->cantidad,2),
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => $ingreso->total_costo,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $ingreso->total_costo,
                                'saldo' => 0,
                            ];
                        }
                    }
                }

            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarCxC(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $ventas = Venta::where('estado', 'Pendiente')
                        ->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'CxC',
                'concepto' => 'Registro de cxc',
                'estado' => 'Pendiente',
            ];

        // Detalles

            $detalles = [];
            $cuenta_cxc = Cuenta::where('id', $configuracion->id_cuenta_cxc)->first();
            if(!$cuenta_cxc){
                return  Response()->json(['titulo' => 'No hay cuenta contable.', 'error' => 'No esta configurada la cuenta contable para cuentas por cobrar.', 'code' => 400], 400);
            }
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();

            foreach ($ventas as $venta) {

                $detalles[] = [
                    'id_cuenta' => $cuenta_cxc->id,
                    'codigo' => $cuenta_cxc->codigo,
                    'nombre_cuenta' => $cuenta_cxc->nombre,
                    'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                    'debe' => $venta->total,
                    'haber' => NULL,
                    'saldo' => 0,
                ];

                $productos_venta = DetalleVenta::with('producto')->where('id_venta', $venta->id)->get();

                foreach ($productos_venta as $detalle) {
                    $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                    if($id_categoria){
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->first();

                        if(!$cuenta_categoria_sucursal){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();

                        if(!$cuenta){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => $detalle->total,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_cxc->id,
                            'codigo' => $cuenta_cxc->codigo,
                            'nombre_cuenta' => $cuenta_cxc->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => $venta->sub_total,
                            'saldo' => 0,
                            'productos' => $productos_venta,
                        ];
                        break;
                    }
                }

                if ($venta->iva > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva->id,
                        'codigo' => $cuenta_iva->codigo,
                        'nombre_cuenta' => $cuenta_iva->nombre,
                        'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                        'debe' => NULL,
                        'haber' => $venta->iva,
                        'saldo' => 0,
                    ];
                }

                if ($venta->iva_retenido > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                        'debe' => $venta->iva_retenido,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }


                // Costo de venta

                    $productos_venta = DetalleVenta::with('producto')->where('id_venta', $venta->id)->get();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->first();

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->first();

                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => number_format($detalle->costo * $detalle->cantidad,2),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];

                            $cuenta_inventarios = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios  ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => NULL,
                                'haber' => number_format($detalle->costo * $detalle->cantidad,2),
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => $venta->total_costo,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => NULL,
                                'haber' => $venta->total_costo,
                                'saldo' => 0,
                            ];
                        }
                    }


            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarEgresos(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pagada')
                            ->where('fecha', $request->fecha)->get();
        $abonos_compras = AbonoCompra::where('estado', 'Confirmado')
                            ->where('fecha', $request->fecha)->with('compra')->get();

        $compras->each->setAttribute('tipo', 'compra');
        // $abonos_compras->each->setAttribute('tipo', 'abono');

        $abonos_compras->each(function ($abono) {
            $abono->tipo = 'abono';
            $abono->tipo_documento = $abono->compra ? $abono->compra->tipo_documento : null;
            $abono->referencia = $abono->compra ? $abono->compra->referencia : null;
        });

        $egresos = $compras->merge($abonos_compras);

        // Partida
            $partida = [
                'fecha' => $request->fecha,
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
                    return  Response()->json(['titulo' => 'La forma de pago ' . $venta->forma_pago . ' no tiene cuenta contable.', 'error' => 'Venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo, 'code' => 400], 400);
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
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo, 'code' => 400], 400);
                            }

                            $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();

                            if(!$cuenta){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo, 'code' => 400], 400);
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
                                'productos' => $productos_compra,
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

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarCxP(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pendiente')->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'CxP',
                'concepto' => 'Compra de mercancía al crédito',
                'estado' => 'Pendiente',
            ];

        // Detalles


            $detalles = [];
            $cuenta_cxp = Cuenta::where('id', $configuracion->id_cuenta_cxp)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_compra)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();
            $cuenta_renta_retenida = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();

            foreach ($compras as $compra) {

                $detalles[] = [
                    'id_cuenta'         => $cuenta_cxp->id,
                    'codigo'            => $cuenta_cxp->codigo,
                    'nombre_cuenta'     => $cuenta_cxp->nombre,
                    'concepto'          => 'Compra de mercancía al crédito',
                    'debe'              => NULL,
                    'haber'             => $compra->total,
                    'saldo'             => 0
                ];

                $productos_compra = DetalleCompra::with('producto')->where('id_compra', $compra->id)->get();

                foreach ($productos_compra as $detalle) {
                    $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                    if($id_categoria){
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $compra->id_sucursal)->first();

                        if(!$cuenta_categoria_sucursal){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->correlativo, 'code' => 400], 400);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();

                        if(!$cuenta){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->correlativo, 'code' => 400], 400);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                            'debe' => $detalle->total,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_cxp->id,
                            'codigo' => $cuenta_cxp->codigo,
                            'nombre_cuenta' => $cuenta_cxp->nombre,
                            'concepto' => 'Inventarios compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                            'debe' => $compra->sub_total,
                            'haber' => NULL,
                            'saldo' => 0,
                            'productos' => $productos_compra,
                        ];
                        break;
                    }
                }

                if ($compra->iva > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva->id,
                        'codigo' => $cuenta_iva->codigo,
                        'nombre_cuenta' => $cuenta_iva->nombre,
                        'concepto' => 'Compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                        'debe' => $compra->iva,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($compra->percepcion > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                        'debe' => $compra->percepcion,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($compra->renta_retenida > 0) {
                    $detalles[] = [
                        'id_cuenta'         => $cuenta_renta_retenida->id,
                        'codigo'            => $cuenta_renta_retenida->codigo,
                        'nombre_cuenta'     => $cuenta_renta_retenida->nombre,
                        'concepto' => 'Compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                        'debe'              => $compra->renta_retenida,
                        'haber'             => NULL,
                        'saldo'             => 0,
                    ];
                }


            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
            // 'ventas' => $ventas,
            // 'abonos_ventas' => $abonos_ventas,
            // 'compras' => $compras,
            // 'abonos_compras' => $abonos_compras,
        ];



        return Response()->json($data, 200);

    }

    public function generarPDF($id)
    {
        $partida = Partida::with('detalles')->where('id', $id)->firstOrFail();

        $pdf = PDF::loadView('contabilidad.partidas.detalle-partida', [
            'partida' => $partida
        ]);

        return $pdf->stream('partida-' . $partida->id . '.pdf');
    }

    public function delete($id)
    {
        $partida = Partida::findOrFail($id);
        $partida->delete();

        return Response()->json($partida, 201);

    }

    public function cerrarPartidas(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            // Validar que el mes y año sean válidos
            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

            $cierreMesService = new CierreMesService();

            // Realizar cierre completo del mes
            $resultado = $cierreMesService->cerrarMes(
                $year,
                $month,
                auth()->user()->id,
                auth()->user()->id_empresa
            );

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cerrar el período: ' . $e->getMessage()
            ], 500);
        }
    }

    public function abrirPartida(Request $request)
    {
        try {
            $user = auth()->user();
            if ($user->tipo !== 'Administrador') {
                return response()->json([
                    'error' => 'No tiene permisos para realizar esta acción.'
                ], 403);
            }

            $id = $request->input('id');
            if (!$id) {
                return response()->json([
                    'error' => 'ID de partida no proporcionado.'
                ], 400);
            }

            $partida = Partida::find($id);
            if (!$partida) {
                return response()->json([
                    'error' => 'Partida no encontrada.'
                ], 404);
            }

            if ($partida->estado !== 'Cerrada') {
                return response()->json([
                    'error' => 'Solo se pueden abrir partidas cerradas.'
                ], 400);
            }

            $partida->estado = 'Pendiente';
            $partida->save();

            return response()->json([
                'message' => 'Partida abierta exitosamente',
                'partida' => $partida
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al abrir la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reabrirPeriodo(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            // Validar que el mes y año sean válidos
            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

            $cierreMesService = new CierreMesService();

            // Reabrir período
            $resultado = $cierreMesService->reabrirPeriodo(
                $year,
                $month,
                auth()->user()->id_empresa
            );

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al reabrir el período: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verificarEstadoPeriodo(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

            $cierreMesService = new CierreMesService();

            $cerrado = $cierreMesService->estaPeriodoCerrado(
                $year,
                $month,
                auth()->user()->id_empresa
            );

            return response()->json([
                'periodo' => "{$month}/{$year}",
                'cerrado' => $cerrado,
                'estado' => $cerrado ? 'Cerrado' : 'Abierto'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al verificar el estado del período: ' . $e->getMessage()
            ], 500);
        }
    }

      public function obtenerBalanceComprobacion(Request $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          if (!$month || !$year || $month < 1 || $month > 12) {
              return response()->json([
                  'error' => 'Mes y año inválidos'
              ], 400);
          }

          $cierreMesService = new CierreMesService();

          $balance = $cierreMesService->obtenerBalanceComprobacion(
              $year,
              $month,
              auth()->user()->id_empresa
          );

          return response()->json($balance);

      } catch (\Exception $e) {
          return response()->json([
              'error' => 'Error al obtener el balance de comprobación: ' . $e->getMessage()
          ], 500);
      }
  }

  public function simularCierreMes(Request $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          if (!$month || !$year || $month < 1 || $month > 12) {
              return response()->json([
                  'error' => 'Mes y año inválidos'
              ], 400);
          }

          $simulacionService = new SimulacionCierreService();

          $resultadoSimulacion = $simulacionService->simularCierreMes(
              $year,
              $month,
              auth()->user()->id_empresa
          );

          return response()->json($resultadoSimulacion);

      } catch (\Exception $e) {
          return response()->json([
              'error' => 'Error al simular el cierre: ' . $e->getMessage()
          ], 500);
      }
  }

}

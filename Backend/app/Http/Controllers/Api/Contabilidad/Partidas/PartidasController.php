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
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $detalle['id_partida'] = $partida->id;
                $detalle->fill($det);
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
        $ventas = Venta::where('estado', 'Pagada')
                        ->where('fecha', $request->fecha)->get();
        // $abonos_ventas = AbonoVenta::where('estado', '!=', 'Anulada')->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas: ' . $request->fecha,
                'estado' => 'Pendiente',
            ];

        // Detalles

            //  if ($venta->estado == 'Pendiente') {
            //     $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxc)->first();
            // } 

            $detalles = [];
            $cuenta_ventas = Cuenta::where('id', $configuracion->id_cuenta_ventas)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();

            foreach ($ventas as $venta) {
                
                $formapago = FormaDePago::with('banco')->where('nombre', $venta->forma_pago)->first();
                
                if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                    return  Response()->json(['titulo' => 'La forma de pago no tiene cuenta contable.', 'error' => 'Venta: ' . $venta->nombre_documento . '#' . $venta->correlativo, 'code' => 400], 400);
                }

                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->first();

                $detalles[] = [
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
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
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $venta->correlativo, 'code' => 400], 400);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();
                        
                        if(!$cuenta){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $venta->correlativo, 'code' => 400], 400);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . '#' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => round($detalle->total,2),
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_ventas->id,
                            'codigo' => $cuenta_ventas->codigo,
                            'nombre_cuenta' => $cuenta_ventas->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . '#' . $venta->correlativo,
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
                        'concepto' => 'Ingresos por ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                        'debe' => NULL,
                        'haber' => round($venta->iva,2),
                        'saldo' => 0,
                    ];
                }

                if ($venta->iva_retenido > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Ingresos por ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
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
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $venta->correlativo, 'code' => 400], 400);
                            }
                            
                            $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->first();
                            
                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $venta->correlativo, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => round(($detalle->costo * $detalle->cantidad),2),
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
                                'haber' => round(($detalle->costo * $detalle->cantidad),2),
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
            // 'ventas' => $ventas,
            // 'abonos_ventas' => $abonos_ventas,
            // 'compras' => $compras,
            // 'abonos_compras' => $abonos_compras,
        ];



        return Response()->json($data, 200);

    }

    public function generarEgresos(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pagada')->where('fecha', $request->fecha)->get();
        // $abonos_compras = AbonoCompra::where('estado', '!=', 'Anulada')->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'Egreso',
                'concepto' => 'Compra de mercancía: ' . $request->fecha,
                'estado' => 'Pendiente',
            ];

        // Detalles

            //  if ($venta->estado == 'Pendiente') {
            //     $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxc)->first();
            // } 

            $detalles = [];
            $cuenta_ventas = Cuenta::where('id', $configuracion->id_cuenta_compras)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_compra)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();

            foreach ($compras as $compra) {
                
                $formapago = FormaDePago::with('banco')->where('nombre', $compra->forma_pago)->first();
                
                if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                    return  Response()->json(['titulo' => 'La forma de pago no tiene cuenta contable.', 'error' => 'Venta: ' . $venta->nombre_documento . '#' . $venta->correlativo, 'code' => 400], 400);
                }

                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->first();

                $detalles[] = [
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
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
                            'debe' => round($detalle->total,2),
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_ventas->id,
                            'codigo' => $cuenta_ventas->codigo,
                            'nombre_cuenta' => $cuenta_ventas->nombre,
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
                        'debe' => round($compra->iva,2),
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


                // Costo de compra

                    $productos_compra = DetalleCompra::with('producto')->where('id_compra', $compra->id)->get();

                    foreach ($productos_compra as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $compra->id_sucursal)->first();
                            
                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->referencia, 'code' => 400], 400);
                            }
                            
                            $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->first();
                            
                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->referencia, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Costo por compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                                'debe' => NULL,
                                'haber' => round(($detalle->costo * $detalle->cantidad),2),
                                'saldo' => 0,
                            ];

                            $cuenta_inventarios = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $compra->nombre_documento . '#' . $compra->referencia,
                                'debe' => round(($detalle->costo * $detalle->cantidad),2),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Costo por compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                                'debe' => NULL,
                                'haber' => $compra->total_costo,
                                'saldo' => 0,
                            ];
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $compra->tipo_documento . '#' . $compra->referencia,
                                'debe' => $compra->total_costo,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                        }
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

    public function delete($id)
    {
        $partida = Partida::findOrFail($id);
        $partida->delete();

        return Response()->json($partida, 201);

    }

}

<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCombo;
use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Eventos\Evento;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;

use App\Exports\VentasExport;
use App\Exports\VentasDetallesExport;
use Maatwebsite\Excel\Facades\Excel;
use Auth;

class VentasController extends Controller
{

    public function index(Request $request) {
       
        $ventas = Venta::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->recurrente !== null, function($q) use ($request){
                            $q->where('recurrente', !!$request->recurrente);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->id_canal, function($query) use ($request){
                            return $query->where('id_canal', $request->id_canal);
                        })
                        ->when($request->id_documento, function($query) use ($request){
                            return $query->where('id_documento', $request->id_documento);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->where('cotizacion', 0)
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        foreach ($ventas as $venta) {
            $venta->saldo = $venta->saldo;
        }

        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Venta::where('id', $id)->with('detalles.producto.composiciones', 'detalles.producto','abonos', 'cliente', 'impuestos.impuesto')->first();
        $venta->saldo = $venta->saldo;
        return Response()->json($venta, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'id_usuario'        => 'required',
        ]);


        $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();

            // Ajustar stocks
            foreach ($venta->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_sucursal', $venta->id_sucursal)->first();
                
                // Anular venta y regresar stock
                if(($venta->estado != 'Anulada') && ($request['estado'] == 'Anulada')){

                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad * -1);
                    }

                    // Inventario compuestos
                    foreach ($producto->composiciones as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_compuesto)
                                    ->where('id_sucursal', $venta->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock += $det['cantidad'] * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($det['cantidad'] * $comp->cantidad) * -1);
                        }
                    }

                }
                // Cancelar anulación de venta y descargar stock
                if(($venta->estado == 'Anulada') && ($request['estado'] != 'Anulada')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad);
                    }

                    // Inventario compuestos
                    foreach ($producto->composiciones as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_compuesto)
                                    ->where('id_sucursal', $venta->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock -= $det['cantidad'] * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($det['cantidad'] * $comp->cantidad));
                        }
                    }

                }
            }
        
        $venta->fill($request->all());
        $venta->save();

        return Response()->json($venta, 200);

    }

    public function delete($id)
    {
        $venta = Venta::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);

    }



    // Facturacion

    public function corte() {

        $usuario = JWTAuth::parseToken()->authenticate();
       
        $caja   = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte  = $caja->corte;
        $ventas = $corte->ventas()->orderBy('id', 'desc')
                            ->paginate(30);

        return Response()->json($ventas, 200);

    }

    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            'id_documento'      => 'required|max:255',
            'id_canal'          => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            // 'fecha_pago'        => 'required',
            'fecha_expiracion'  => 'required_if:cotizacion,1',
            'credito'           => 'required_if:condicion,"Crédito"',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
            'fecha_expiracion.required_if' => 'La fecha de expiracion es obligatorio cuando es cotización.',
        ]);

        DB::beginTransaction();
         
        try {
        
        // Guardamos la venta
            if($request->id)
                $venta = Venta::findOrFail($request->id);
            else
                $venta = new Venta;
            $venta->fill($request->all());
            $venta->save();

        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['id_venta'] = $venta->id;
                

                $detalle->fill($det);
                $detalle->save();

                // Actualizar inventario
                if ($request->cotizacion == 0) {
                    
                    $producto = Producto::where('id', $det['id_producto'])
                                        ->with('composiciones')->firstOrFail();

                    $inventario = Inventario::where('id_producto', $producto->id)
                                        ->where('id_sucursal', $venta->id_sucursal)->first();
                    if ($inventario) {
                        $inventario->stock -= $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($venta, $det['cantidad']);
                    }

                    // Inventario compuestos
                    foreach ($producto->composiciones as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_compuesto)
                                    ->where('id_sucursal', $venta->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock -= $det['cantidad'] * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($det['cantidad'] * $comp->cantidad));
                        }
                    }


                }
                
            }

        // Evento
            if($request->id_evento){
                $evento = Evento::findOrfail($request->id_evento);
                if ($venta->estado == 'Pagada') {
                    $evento->estado = 'Pagado';
                    $evento->estadoVerificarFrecuencia('Pagado');
                }else{
                    $evento->estado = 'Pendiente';
                    $evento->save();
                }
            }
        // Impuestos
            if($request->impuestos){
                foreach ($request->impuestos as $impuesto) {
                    $venta_impuesto = new Impuesto();
                    $venta_impuesto->id_impuesto = $impuesto['id'];
                    $venta_impuesto->monto = $impuesto['monto'];
                    $venta_impuesto->id_venta = $venta->id;
                    $venta_impuesto->save();
                }
            }
            

        // Incrementar el correlarivo
            $documento = Documento::findOrfail($venta->id_documento);
            $documento->increment('correlativo');
        
        DB::commit();
        return Response()->json($venta, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        

    }

    public function facturacionConsigna(Request $request){
        $request->validate([
            'id'                => 'required',
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            'id_documento'      => 'required|max:255',
            'id_canal'          => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            'fecha_pago'        => 'required',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos a la venta',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
        ]);

        DB::beginTransaction();
      
        try {
            $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();
            if ($venta->total != $request->total) {
                // Crear consigna
                $consigna = new Venta();
                $consigna->fill($request->all());
                $consigna->estado = 'Consigna';
                $consigna->sub_total = $venta->sub_total - $request->sub_total;
                $consigna->total_costo  = $venta->total_costo  - $request->total_costo ;
                $consigna->total = $venta->total - $request->total;
                $consigna->iva = $venta->iva - $request->iva;
                $consigna->save();

                foreach($request->detalles as $detalle){
                    
                    $detalle_venta = $venta->detalles()->where('id', $detalle['id'])->first();
                    if ($detalle_venta) {
                        if ($detalle_venta->cantidad > $detalle['cantidad']) {
                            $detalle_consigna = new Detalle();
                            $detalle_consigna->id_producto = $detalle['id_producto'];
                            $detalle_consigna->precio = $detalle['precio'];
                            $detalle_consigna->cantidad = $detalle_venta->cantidad - $detalle['cantidad'];
                            $detalle_consigna->total = $detalle_consigna->precio * $detalle_consigna->cantidad;
                            $detalle_consigna->id_venta = $consigna->id;
                            $detalle_consigna->save();
                        }
                    }
                  
                }
                
                //Guardar nuevos detalles
                $venta->detalles()->delete();

                foreach ($request->detalles as $detalle) {
                    if ($detalle['cantidad'] > 0) {
                        $det = new Detalle();
                        $det->id_producto = $detalle['id_producto'];
                        $det->cantidad = $detalle['cantidad'];
                        $det->precio = $detalle['precio'];
                        $det->total = $detalle['cantidad'] * $detalle['precio'];
                        $det->descuento = 0;
                        $det->id_venta = $venta->id;
                        $det->save();
                    }
                }
                
                $venta->total = $request->total;
                $venta->iva = $request->iva;
                $venta->sub_total = $request->sub_total;
            }


            $venta->fecha = $request->fecha;
            $venta->estado = 'Pagada';
            $venta->save();

            DB::commit();
            return Response()->json($venta, 200);

            } catch (\Exception $e) {
                DB::rollback();
                return Response()->json(['error' => $e->getMessage()], 400);
            } catch (\Throwable $e) {
                DB::rollback();
                return Response()->json(['error' => $e->getMessage()], 400);
            }
    }

    public function pendientes() {

        $usuario = JWTAuth::parseToken()->authenticate();
       
        $caja    = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte   = $caja->corte;
        
        if ($corte) {
            if (!$corte->cierre)
                $corte->cierre = Carbon::now()->toDateTimeString(); ;

            $ventas  = $corte->ventas()->where('estado', 'En Proceso')
                                ->orderBy('id', 'desc')
                                ->paginate(5000);
        }else{
            $ventas  = Venta::where('estado', 'En Proceso')
                                ->orderBy('id', 'desc')
                                ->paginate(5000);
        }
        

        return Response()->json($ventas, 200);


    }

    public function vendedor() {

        $usuario = JWTAuth::parseToken()->authenticate();

        $ventas  = Venta::where('estado', 'En Proceso')
                                ->where('id_usuario', $usuario->id)
                                ->orderBy('id', 'desc')
                                ->paginate(5000);

        return Response()->json($ventas, 200);


    }



    // public function generarDoc($id){
    //     $venta = Venta::where('id', $id)->with('detalles')->firstOrFail();
    //     $documento = Documento::findOrfail($venta->id_documento);
    //     $empresa = Empresa::findOrfail(JWTAuth::parseToken()->authenticate()->id_empresa);

    //     $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
    //     $formatter = new NumeroALetras();
    //     $n = explode(".", number_format($venta->total,2));        
    //     $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
    //     $centavos = $formatter->toWords($n[1]);

    //     if ($documento->nombre == 'Factura') {
            
    //         if($empresa->id == 38){
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 62){ //62
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 84){ //84
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 75){ //84
    //             // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 104){ //104
    //             // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura-Coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 11){ //11
    //             // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 365.669, 566.929133858]);
    //         }
    //         elseif($empresa->id == 12){ //12
    //             // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 365.669, 566.929133858]);
    //         }
    //         elseif($empresa->id == 128){ //128
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 283.46, 765.35]);  
    //         }
    //         elseif($empresa->id == 135){ //135
    //             // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 609.45, 467.72]);
    //         }
    //         elseif($empresa->id == 136){ //12
    //             return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 365.669, 609.4488]);
    //         }
    //         elseif($empresa->id == 149){ //12
    //             return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 24){ //24
    //             // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Via-del-Mar', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             // $pdf->setPaper([0, 0, 306, 396]);
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }else{
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 365.669, 566.929133858]);
    //         }

    //         return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');

    //     }
        
    //     if ($documento->nombre == 'Crédito fiscal') {

    //         if($empresa->id == 24){
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.vetvia-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 38){
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 62){ //62
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait'); 
    //         }
    //         elseif($empresa->id == 128){ //128
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 283, 765]);  
    //         }
    //         elseif($empresa->id == 135){ //135
    //             // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 609.45, 467.72]);  
    //         }
    //         elseif($empresa->id == 136){ //136
    //             // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper([0, 0, 297.64, 382.68]); 
    //         }
    //         elseif($empresa->id == 158){//158
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Guaca-Mix-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }
    //         elseif($empresa->id == 84){ //84
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa-cff', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
    //             $pdf->setPaper('US Letter', 'portrait');
    //         }else{
    //             $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.credito', compact('venta', 'empresa', 'cliente'));
    //             $pdf->setPaper([0, 0, 365.669, 566.929133858]);  
    //         }     

    //         return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');


    //     }

    //     if ($documento->nombre == 'Ticket') {
    //         return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
    //     }

    //     return "Sin documento para generar";

    // }

    public function generarDoc($id){

        $venta = Venta::where('id', $id)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        if ($documento->nombre == 'Ticket') {
            $documento = Documento::findOrfail($venta->id_documento);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            return view('reportes.ticket', compact('venta', 'empresa', 'documento'));
        }

        if ($documento->nombre == 'Factura') {
            $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total_venta,2));

            
            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n)));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if(Auth::user()->id_empresa == 38){ //38
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 84){ //84
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 75){ //84
                // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 104){ //104
                // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura-Coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 11){ //11
                // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 12){ //12
                // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283.46, 765.35]);  
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            }
            elseif(Auth::user()->id_empresa == 136){ //136 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 609.4488]);
            }
            elseif(Auth::user()->id_empresa == 149){ //149 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 177){//177  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 24 ){ //24  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Via-del-Mar', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }else{
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            

            return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
        }

        if ($documento->nombre == 'Crédito fiscal') {
            $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total_venta,2));

            
            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n)));
            $centavos = $formatter->toWords($n[1]);

            if(Auth::user()->id_empresa == 24){ //24
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.vetvia-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 38){
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait'); 
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283, 765]);  
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);  
            }
            elseif(Auth::user()->id_empresa == 136){ //136
                // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 297.64, 382.68]); 
            }
            elseif(Auth::user()->id_empresa == 158){//158
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Guaca-Mix-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 177){//177  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 13){ //84
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa-cff', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }else{
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.credito', compact('venta', 'empresa', 'cliente'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);  
            }     

            return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
        }
    }

    public function anularDoc(){

        return view('reportes.anulacion');

    }

    public function sinDevolucion(){

        $ventas = Venta::where('estado', '!=', 'Anulada')
                        ->whereMonth('fecha', '>=' , date('m') - 1)
                        ->whereYear('fecha', date('Y'))
                        ->whereDoesntHave('devoluciones')
                        ->orderBy('fecha', 'DESC')
                        ->get();

        return Response()->json($ventas, 200);
    }

    public function libroIva(Request $request) {
        $star = $request->inicio;
        $end = $request->fin;

        $ventas = Venta::where('tipo_documento', 'Credito Fiscal')
                            ->where('estado', '!=', 'Pendiente')
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->orderBy('fecha','desc')->get();

        $ivas = collect();

        foreach ($ventas as $venta) {
                $ivas->push([
                    'fecha'         => $venta->fecha,
                    'correlativo'   => $venta->correlativo,
                    'cliente'       => $venta->estado == 'Anulada' ?  'ANULADA': $venta->cliente_nombre,
                    'registro'      => $venta->registro,
                    'interno'       => $venta->subtotal,
                    'iva'           => $venta->iva,
                    'fovial'        => $venta->fovial,
                    'cotrans'       => $venta->cotrans,
                    'total'         => $venta->total
                ]);
        }

        $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);

    }

    public function cxc() {
       
        $cobros = Venta::where('estado', 'Pendiente')->orderBy('fecha','desc')->paginate(10);

        return Response()->json($cobros, 200);

    }

    public function cxcBuscar($txt) {
       
        $cobros = Venta::where('estado', 'Pendiente')
                        ->whereHas('cliente', function($query) use ($txt) {
                            $query->where('nombre', 'like' ,'%' . $txt . '%');
                        })
                        ->orderBy('fecha','desc')->paginate(10);

        return Response()->json($cobros, 200);

    }

    public function historial(Request $request) {

        $ventas = Venta::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->get()
                        ->groupBy(function($date) {
                            return Carbon::parse($date->fecha)->format('d-m-Y');
                        });
        
        $movimientos = collect();

        foreach ($ventas as $venta) {
            $ventaTotal = $venta->sum('total');
            $costoTotal = $venta->sum('subcosto');
            $movimientos->push([
                'cantidad'      => $venta->count(),
                'fecha'         => $venta[0]->fecha,
                'total'         => $ventaTotal,
                'costo'         => $costoTotal,
                'utilidad'      => $ventaTotal - $costoTotal,
                'detalles'      => $venta
            ]);
        }

        return Response()->json($movimientos, 200);

    }

    public function export(Request $request){
        $ventas = new VentasExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas.xlsx');
    }

    public function exportDetalles(Request $request){
        $ventas = new VentasDetallesExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas-detalles.xlsx');
    }


}

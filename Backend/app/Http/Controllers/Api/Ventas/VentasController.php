<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCombo;
use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Mesa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Empleados\Empleados\Comision;

use App\Models\Creditos\Credito;
use Illuminate\Support\Facades\DB;

class VentasController extends Controller
{
    

    public function index() {
       
        $ventas = Venta::orderBy('id','desc')
                            // ->where('estado', '!=', 'Pendiente')
                            ->with('credito')->paginate(10);
       
        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'credito')->first();

        return Response()->json($venta, 200);

    }

    public function search($txt) {

        $ventas = Venta::whereHas('cliente', function($query) use ($txt) {
                                    $query->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('correlativo', 'like', '%'.$txt.'%')
                                ->orwhere('estado', 'like', '%'.$txt.'%')
                                ->orwhere('nota', 'like', '%'.$txt.'%')
                                ->orwhere('metodo_pago', 'like', '%'.$txt.'%')
                                ->orwhere('referencia', 'like', '%'.$txt.'%')
                                ->paginate(10);

        return Response()->json($ventas, 200);

    }

    public function filter(Request $request) {


        $ventas = Venta::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
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
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($ventas, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'usuario_id'        => 'required',
        ]);

        if($request->id)
            $venta = Venta::findOrFail($request->id);
        else
            $venta = new Venta;
        
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
       
        $caja   = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();
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
            'tipo_documento'    => 'required|max:255',
            'canal_id'          => 'required|max:255',
            'metodo_pago'       => 'required|max:255',
            'cliente_id'        => 'required_if:tipo_documento,"Credito Fiscal"|required_if:condicion,"Crédito"',
            'detalles'          => 'required',
            'fecha_pago'        => 'required',
            'credito'           => 'required_if:condicion,"Crédito"',
            'iva'               => 'required|numeric',
            'subcosto'          => 'required|numeric',
            'interes_anual'     => 'required_if:metodo_pago,"Crédito"',
            'tipo_cuota'        => 'required_if:metodo_pago,"Crédito"',
            'numero_de_cuotas'  => 'required_if:metodo_pago,"Crédito"',
            'forma_de_pago'     => 'required_if:metodo_pago,"Crédito"',
            'subtotal'          => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            // 'caja_id'           => 'required|numeric',
            // 'corte_id'          => 'required|numeric',
            'usuario_id'        => 'required|numeric',
            'sucursal_id'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos a la venta',
            'cliente_id.required_if' => 'El cliente es requerido para los creditos y la facturación.',
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

        // Guardamos crédito
        if ($request->condicion == 'Crédito') {
            $credito = new Credito;
            $credito->fecha         = $venta->fecha;
            $credito->venta_id      = $venta->id;
            $credito->total         = $venta->total;
            $credito->interes_anual = $request['credito']['interes_anual'];
            $credito->tipo_cuota    = $request['credito']['tipo_cuota'];
            $credito->periodo_de_gracia = $request['credito']['periodo_de_gracia'];
            $credito->numero_de_cuotas  = $request['credito']['numero_de_cuotas'];
            $credito->forma_de_pago    = $request['credito']['forma_de_pago'];
            $credito->prima         = $request['credito']['prima'];
            $credito->nota         = $request['credito']['nota'];
            $credito->usuario_id    = $venta->usuario_id;
            $credito->cliente_id    = $venta->cliente_id;
            $credito->empresa_id    = $venta->sucursal()->first()->empresa_id;
            $credito->save();
        }

        // Si el vendedor tiene comisión
        if ($venta->vendedor && $venta->vendedor->comision > 0) {
            $total = $venta->vendedor->comision;
            if ($venta->vendedor->tipo_comision == 'Porcentaje') {
                $total = $venta->subtotal * ($venta->vendedor->comision / 100);
            }

            Comision::Create([
                'fecha'     => date('Y-m-d'),
                'concepto'  => 'Comisión por venta',
                'estado'    => 'Pendiente',
                'tipo'      => 'Por venta',
                'total'     => $total,
                'venta_id'  => $venta->id,
                'empleado_id' => $venta->vendedor->id,
                'usuario_id' => $venta->usuario_id
            ]);
        }

        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['venta_id'] = $venta->id;
                

                $detalle->fill($det);
                $detalle->save();

                // Actualizar inventario
                if ($request->estado != 'En Proceso') {

                    // Actualizar inventario
                    $producto = Producto::where('id', $det['producto_id'])->with('composiciones')->firstOrFail();

                    // Inventario compuestos
                    foreach ($producto->composiciones as $comp) {
                        $productoCompuesto = $comp->compuesto()->first();
                        $inventario = Inventario::where('producto_id', $comp->compuesto_id)->where('bodega_id', $venta->bodega_id)->first();
                        if ($inventario) {
                            $inventario->stock -= $det['cantidad'] * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($det['cantidad'] * $comp->cantidad));
                        }
                    }
                    // Inventario individual
                    $inventario = Inventario::where('producto_id', $producto->id)->where('bodega_id', $venta->bodega_id)->first();
                    if ($inventario) {
                        $inventario->stock -= $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($venta, $det['cantidad']);
                    }

                    // Si el producto tiene comisión
                    if ($producto->comision > 0) {
                        $total = $producto->comision * $detalle->cantidad;
                        if ($producto->tipo_comision == 'Porcentaje') {
                            $total = $detalle->subtotal * ($producto->comision / 100);
                        }

                        Comision::Create([
                            'fecha'     => date('Y-m-d'),
                            'concepto'  => 'Comisión por venta de ' . $det['cantidad'] . ' ' . $producto->nombre,
                            'estado'    => 'Pendiente',
                            'tipo'      => 'Por venta',
                            'total'     => $total,
                            'venta_id'  => $venta->id,
                            'empleado_id' => $venta->vendedor ? $venta->vendedor->id : $venta->usuario_id,
                            'usuario_id' => $venta->usuario_id
                        ]);
                    }

                }
                
            }
            

        // Incrementar el correlarivo
            $documento = Documento::where('caja_id', JWTAuth::parseToken()->authenticate()->caja_id)
                                ->where('nombre', $request->tipo_documento)
                                ->first();
            $documento->actual = $venta->correlativo + 1;
            $documento->save();
        
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
       
        $caja    = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();
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
                                ->where('usuario_id', $usuario->id)
                                ->orderBy('id', 'desc')
                                ->paginate(5000);

        return Response()->json($ventas, 200);


    }

    public function propinas(Request $request) {

        $ventas = Venta::where('propina', '>', 0)->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($ventas, 200);

    }

    public function generarDoc($id){
        $venta = Venta::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        $empresa = Empresa::find(1);

        $partes = explode('.', strval( number_format($venta->total, 2) ));

        $venta->total_letras = \NumeroALetras::convertir($partes[0], 'Dolares con ') . $partes[1].'/100';

        if ($venta->tipo_documento == 'Factura') {
            $venta->width = 10.5;
            $venta->height = 15.2;

            $venta->pos_fecha_x = 7;
            $venta->pos_fecha_y = 2.5;
            $venta->pos_cliente_x = 2;
            $venta->pos_cliente_y = 3.2;
            $venta->pos_direccion_x = 2.3;
            $venta->pos_direccion_y = 3.7;
            $venta->pos_dui_x = 7;
            $venta->pos_dui_y = 4.2;
            $venta->pos_detalles_x = 0.6;
            $venta->pos_detalles_y = 5.6;
            $venta->pos_detalles_linea_alto = 0.6;

            $venta->pos_detalles_cantidad = 1;
            $venta->pos_detalles_producto = 4;
            $venta->pos_detalles_precio = 1;
            $venta->pos_detalles_sujetas = 1;
            $venta->pos_detalles_exentas = 1;
            $venta->pos_detalles_gravadas = 1.2;

            $venta->pos_letras_y = 11;
            $venta->pos_letras_x = 1;

            $venta->pos_correlativo_y = 11.5;
            $venta->pos_correlativo_x = 1;

            $venta->pos_sumas_y = 11;
            $venta->pos_sumas_x = 8.5;

            return view('reportes.facturacion.factura', compact('venta', 'empresa'));
        }
        elseif ($venta->tipo_documento == 'Crédito Fiscal') {
            $venta->width = 16.5;
            $venta->height = 20.5;

            $venta->pos_fecha_x = 11;
            $venta->pos_fecha_y = 3.6;
            $venta->pos_cliente_x = 3;
            $venta->pos_cliente_y = 4.2;
            $venta->pos_direccion_x = 3.2;
            $venta->pos_direccion_y = 4.8;
            $venta->pos_dui_x = 10.5;
            $venta->pos_dui_y = 5.7;

            $venta->pos_giro_x = 2.5;
            $venta->pos_giro_y = 6.3;

            $venta->pos_ncr_x = 11.5;
            $venta->pos_ncr_y = 6.8;

            $venta->pos_detalles_x = 1.3;
            $venta->pos_detalles_y = 8;
            $venta->pos_detalles_linea_alto = 0.6;

            $venta->pos_detalles_cantidad = 1.3;
            $venta->pos_detalles_producto = 7.3;
            $venta->pos_detalles_precio = 1.2;
            $venta->pos_detalles_sujetas = 1.2;
            $venta->pos_detalles_exentas = 1.2;
            $venta->pos_detalles_gravadas = 1.7;

            $venta->pos_letras_y = 15;
            $venta->pos_letras_x = 1.8;

            $venta->pos_correlativo_y = 15.5;
            $venta->pos_correlativo_x = 2;

            $venta->pos_sumas_y = 14.3;
            $venta->pos_sumas_x = 13.6;

            return view('reportes.facturacion.credito', compact('venta', 'empresa'));

        }elseif ($venta->tipo_documento == 'Exportación') {
            $venta->width = 13.5;
            $venta->height = 20;

            $venta->pos_fecha_x = 10;
            $venta->pos_fecha_y = 3;
            $venta->pos_cliente_x = 3;
            $venta->pos_cliente_y = 3.9;
            $venta->pos_direccion_x = 3.5;
            $venta->pos_direccion_y = 4.5;
            $venta->pos_dui_x = 9.5;
            $venta->pos_dui_y = 6;

            $venta->pos_giro_x = 2;
            $venta->pos_giro_y = 6.5;

            $venta->pos_ncr_x = 10;
            $venta->pos_ncr_y = 5.5;

            $venta->pos_detalles_x = 1;
            $venta->pos_detalles_y = 7.3;
            $venta->pos_detalles_linea_alto = 0.6;

            $venta->pos_detalles_cantidad = 1.5;
            $venta->pos_detalles_producto = 7;
            $venta->pos_detalles_precio = 1.6;
            $venta->pos_detalles_sujetas = 1;
            $venta->pos_detalles_exentas = 1;
            $venta->pos_detalles_gravadas = 2;

            $venta->pos_letras_y = 18;
            $venta->pos_letras_x = 1.5;

            $venta->pos_correlativo_y = 18.6;
            $venta->pos_correlativo_x = 2;

            $venta->pos_total_y = 18;
            $venta->pos_total_x = 10.7;

            return view('reportes.facturacion.exportacion', compact('venta', 'empresa'));
        }
        elseif ($venta->tipo_documento == 'Ticket') {

            return view('reportes.facturacion.ticket', compact('venta', 'empresa'));
        }
        else{
            return "Sin documento para generar";
        }

    }

    public function anularDoc(){

        return view('reportes.anulacion');

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


}

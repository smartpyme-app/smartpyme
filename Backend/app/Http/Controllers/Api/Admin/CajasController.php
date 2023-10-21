<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use stdClass;
use JWTAuth;
use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Corte;

use App\Models\Ventas\Venta;
use App\Models\Ventas\DevolucionVenta;
use App\Models\Ventas\Detalle;

class CajasController extends Controller
{
    

    public function index() {
       
        $cajas = Caja::with('cortesDia')->get();

        return Response()->json($cajas, 200);

    }


    public function read($id) {

        $caja = Caja::where('id', $id)->with('documentos', 'usuarios')->firstOrFail();
        return Response()->json($caja, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'    => 'required|max:255',
            'tipo'    => 'required|max:255',
            'sucursal_id'    => 'required|numeric',
        ]);

        if($request->id)
            $caja = Caja::findOrFail($request->id);
        else
            $caja = new Caja;
        
        $caja->fill($request->all());
        $caja->save();

        return Response()->json($caja, 200);

    }

    public function cortes($id){
        $cortes = Corte::where('caja_id', $id)
                    ->orderby('id', 'desc')
                    ->paginate(7);

        // foreach ($cortes as $corte) {
        //     $corte->total_ventas = $corte->ventas()->sum('total');
        // }
        return Response()->json($cortes, 200);
    }


    public function caja(){
        $usuario = JWTAuth::parseToken()->authenticate();
        $caja     = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();

        return Response()->json($caja, 200);
    }

    public function estadisticas(Request $request) {

        $caja = new stdClass();

        $caja = Caja::find($request->caja_id);
        $corte = $caja->corte;

        $ventas     = $corte->ventas()->where('estado', 'Cobrada')->get();

        // Por Forma de Pago
        $caja->venta               = $ventas->sum('total');
        $caja->num_ventas          = $ventas->count();
        $caja->ventas_efectivo     = $ventas->where('metodo_pago','Efectivo')->sum('total');
        $caja->ventas_tarjeta      = $ventas->where('metodo_pago','Tarjeta')->sum('total');
        $caja->ventas_vale         = $ventas->where('metodo_pago','Vale')->sum('total');

        $caja->factura             = $ventas->where('tipo_documento','Credito Fiscal')->count();
        $caja->consumidor_final    = $ventas->where('tipo_documento','Factura')->count();
        $caja->ticket             = $ventas->where('tipo_documento','Ticket')->count();
        $caja->ninguno             = $ventas->where('tipo_documento','Ninguno')->count();

        // Galonaje
        // $galones_dia                = Detalle::whereIn('producto_id', [1,2,3])
        //                             ->whereHas('venta', function($q) use ($request){
        //                                 $q->whereBetween('fecha', [$request->inicio, $request->fin]);
        //                             })->get();
        // $caja->galones_dia_super   = $galones_dia->where('producto_id', 1)->sum('cantidad');
        // $caja->galones_dia_regular = $galones_dia->where('producto_id', 2)->sum('cantidad');
        // $caja->galones_dia_diesel  = $galones_dia->where('producto_id', 3)->sum('cantidad');
        // $caja->galones_dia         = $galones_dia->sum('cantidad');


        return Response()->json($caja, 200);

    }

    public function reporteDia($id) {
        $corte = new stdClass();

        $empresa = Empresa::find(1);
        $caja   = Caja::where('id', $id)->firstOrFail();
        $ventas  = $caja->ventasDia()->get();

        // Por Forma de Pago
        $usuarios = collect();
        foreach ($caja->cortesDia()->distinct()->get('usuario_id') as $corte) {
            $usuarios->push($corte->usuario()->first());
        }

        $corte->usuarios = $usuarios;
        $corte->ventas_total        = $ventas->sum('total');
        $corte->id       = $caja->corte->id;
        $corte->saldo_inicial       = $caja->corte->saldo_inicial;
        // return $corte;
        

        $corte->subtotal            = $ventas->sum('subtotal');
        $corte->iva                 = $ventas->sum('iva');
        $corte->iva_retenido        = $ventas->sum('iva_retenido');
        $corte->exenta              = $ventas->sum('exenta');
        $corte->gravada             = $ventas->sum('gravada');
        $corte->no_sujeta           = $ventas->sum('no_sujeta');
        
        $corte->ventas_efectivo     = $ventas->where('metodo_pago','Efectivo')->sum('total');
        $corte->ventas_tarjeta      = $ventas->where('metodo_pago','Tarjeta')->sum('total');
        $corte->ventas_vale         = $ventas->where('metodo_pago','Vale')->sum('total');
        $corte->ventas_cheque       = $ventas->where('metodo_pago','Cheque')->sum('total');

        $corte->creditos_fiscales    = $ventas->where('tipo_documento','Credito Fiscal')->sum('total');
        $corte->facturas             = $ventas->where('tipo_documento','Factura')->sum('total');
        $corte->tickets              = $ventas->where('tipo_documento','Ticket')->sum('total');
        $corte->notas_creditos       = $ventas->where('tipo_documento','Nota Credito')->sum('total');

        $corte->creditos_fiscales_rango   = $corte->creditos_fiscales > 0 ? 'CF ' . $ventas->where('tipo_documento','Credito Fiscal')->pluck('correlativo')->first() . ' - CF ' . $ventas->where('tipo_documento','Credito Fiscal')->pluck('correlativo')->last() : 0;
        $corte->facturas_rango             = $corte->facturas > 0 ? 'F ' . $ventas->where('tipo_documento','Factura')->pluck('correlativo')->first() . ' - F' . $ventas->where('tipo_documento','Factura')->pluck('correlativo')->last() : 0;
        $corte->tickets_rango              = $corte->tickets > 0 ? 'T ' . $ventas->where('tipo_documento','Ticket')->pluck('correlativo')->first() . ' - T ' . $ventas->where('tipo_documento','Ticket')->pluck('correlativo')->last() : 0;
        $corte->notas_credito_rango       = $corte->notas_creditos > 0 ? 'NC ' . $ventas->where('tipo_documento','Nota Credito')->pluck('correlativo')->first() . ' - NC' . $ventas->where('tipo_documento','Nota Credito')->pluck('correlativo')->last() : 0;

        // return $corte;
        // $reportes = \PDF::loadView('reportes.corte', compact('corte', 'empresa'));
         return view('reportes.corte-z', compact('corte', 'empresa'));
        // return $reportes->stream();

    }


}

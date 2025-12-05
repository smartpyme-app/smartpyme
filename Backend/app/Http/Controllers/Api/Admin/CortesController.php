<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Empresa;
use App\Models\Admin\Corte;
use App\Models\Ventas\Detalle;
use App\Models\User;
use App\Http\Requests\Admin\Cortes\StoreCorteRequest;

class CortesController extends Controller
{
    

    public function index() {
       
        $cortes = Corte::orderby('created_at', 'desc')->paginate(8);
        return Response()->json($cortes, 200);

    }


    public function read($id) {

        $corte = Corte::findOrFail($id);
        return Response()->json($corte, 200);

    }

    public function store(StoreCorteRequest $request)
    {

        if($request->id)
            $corte = Corte::findOrFail($request->id);
        else
            $corte = new Corte;

        if($request->cierre)
            $request['saldo_final'] = $corte->ventas_suma;
        
        $corte->fill($request->all());
        $corte->save();

        return Response()->json($corte, 200);

    }

    public function filter(Request $request) {

        $cortes = Corte::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->caja_id, function($query) use ($request){
                            return $query->where('caja_id', $request->caja_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->orderBy('id','desc')->paginate(14);

        foreach ($cortes as $corte) {
            $corte->total_ventas = $corte->ventas()->sum('total');
        }

        return Response()->json($cortes, 200);
    }


    public function reporte($id) {

        $empresa = Empresa::find(1);
        $corte   = Corte::where('id', $id)->firstOrFail();
        $ventas  = $corte->ventas()->where('estado', '!=', 'Anulada')->get();

        // Por Forma de Pago

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
        $corte->facturas_rango             = $corte->facturas > 0 ? 'F ' . $ventas->where('tipo_documento','Factura')->pluck('correlativo')->first() . ' - F ' . $ventas->where('tipo_documento','Factura')->pluck('correlativo')->last() : 0;
        $corte->tickets_rango              = $corte->tickets > 0 ? 'T ' . $ventas->where('tipo_documento','Ticket')->pluck('correlativo')->first() . ' - T ' . $ventas->where('tipo_documento','Ticket')->pluck('correlativo')->last() : 0;
        $corte->notas_credito_rango       = $corte->notas_creditos > 0 ? 'NC ' . $ventas->where('tipo_documento','Nota Credito')->pluck('correlativo')->first() . ' - NC ' . $ventas->where('tipo_documento','Nota Credito')->pluck('correlativo')->last() : 0;

        // $reportes = \PDF::loadView('reportes.corte-x', compact('corte', 'empresa'));
         return view('reportes.corte-x', compact('corte', 'empresa'));
        // return $reportes->stream();

    }

    public function ventas($id) {

        $corte = Corte::where('id', $id)->firstOrFail();

        $ventas = $corte->ventas()->with('credito')->orderBy('fecha', 'desc')->orderBy('id', 'desc')->get();

        return Response()->json($ventas, 200);

    }

    public function devoluciones($id) {

        $corte = Corte::where('id', $id)->firstOrFail();

        $ventas = $corte->devoluciones()->orderBy('fecha', 'desc')->orderBy('id', 'desc')->get();

        return Response()->json($ventas, 200);

    }



}

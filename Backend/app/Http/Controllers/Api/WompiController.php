<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Venta;
use App\Models\Wompi;
use App\Http\Requests\Wompi\PagoWompiRequest;

class WompiController extends Controller
{ 
    
    public function wompiLink($id){

        $venta = Venta::where('id', $id)
            ->with('empresa')
            ->withDetalleTextRelations()
            ->firstOrFail();
        $empresa = $venta->empresa;
        $wompi = new Wompi($empresa);
        $autenticate = $wompi->autenticate();

        if (isset($autenticate['error'])) {
            if (isset($autenticate['error'])) {
                return Response()->json(['error' => 'No se pudo realizar la conexión con Wompi, verifique los datos.', 'code' => 500], 500);
            }
        }

        if ($autenticate['access_token']) {
            if ($venta->id_wompi_link) {
                $transaccion = $wompi->getLink($venta->id_wompi_link);
                if ($transaccion['idEnlace']) {
                    return view('wompi.link-wompi', compact('venta', 'transaccion'));
                }
            }else{

                $request = new \stdClass;
                $request->nombre = $empresa->nombre . ' #' . $venta->id;
                $request->descripcion = $venta->detalleText();
                $request->monto = $venta->total;
                $request->img = 'https://api.smartpyme.site/img/logotipo.jpg';
                // $request->img = asset('/img'.$empresa->logo);
                $transaccion = $wompi->link($request);
                // return $transaccion;
                if (isset($transaccion['idEnlace'])) {
                    $venta->id_wompi_link = $transaccion['idEnlace'];
                    $venta->save();
                    return view('wompi.link-wompi', compact('venta', 'transaccion'));
                }
                return $transaccion .': Lo sentimos pero no se pudo generar el enlace a través de Wompi. Verifique si su aplicativo esta activo o consulte a soporte.';
            }
        }

        return Response()->json(['error' => 'No se pudo generar el enlace, verifique los datos de Wompi.', 'code' => 500], 500);

    }


    public function pagoWompi(PagoWompiRequest $request){
        $venta = Venta::where('id_wompi_link', $request->idEnlace)
            ->withDetalleTextRelations()
            ->firstOrFail();
        $venta->id_wompi_transaccion = $request->idTransaccion;
        $venta->estado = 'Pagada';
        $venta->save();
    
        return view('wompi.pago-wompi', compact('venta'));
    }

    
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Venta;
use App\Models\Wompi;

class WompiController extends Controller
{ 
    
    public function wompiLink($id){

        $venta = Venta::where('id', $id)->with('empresa')->firstOrFail();
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
                $request->img = 'https://login.smartpyme.site/img/88564184a70b76f56ad43ca6f64b05f7.jpg';
                // $request->img = asset('/img'.$empresa->logo);
                // return $request;
                $transaccion = $wompi->link($request);
                if (isset($transaccion['idEnlace'])) {
                    $venta->id_wompi_link = $transaccion['idEnlace'];
                    $venta->save();
                    return view('wompi.link-wompi', compact('venta', 'transaccion'));
                }else{
                    return Response()->json(['error' => $transaccion['mensajes'][0], 'code' => 422], 422);
                }
            }
        }

        return Response()->json(['error' => 'No se pudo generar el enlace, verifique los datos de Wompi.', 'code' => 500], 500);

    }


    public function pagoWompi(Request $request){
        $venta = Venta::where('id_wompi_link', $request->idEnlace)->firstOrFail();
        $venta->id_wompi_transaccion = $request->idTransaccion;
        $venta->estado = 'Pagada';
        $venta->save();
    
        return view('wompi.pago-wompi', compact('venta'));
    }

    
}

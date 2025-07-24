<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Configuracion;
use JWTAuth;

class ConfiguracionController extends Controller
{
    

    public function read($id) {
        
        $configuracion = Configuracion::where('id_empresa', $id)->first();
        return Response()->json($configuracion, 200);

    }


    public function store(Request $request)
    {

        $request->validate([
            'id_cuenta_ventas'   => 'required|numeric',
            'id_cuenta_devoluciones_ventas'   => 'required|numeric',
            'id_cuenta_iva_ventas'   => 'required|numeric',
            'id_cuenta_iva_retenido_ventas'   => 'required|numeric',
            'id_cuenta_renta_retenida_ventas'   => 'required|numeric',
            'id_cuenta_cxc'   => 'required|numeric',
            
            'id_cuenta_costo_venta'   => 'required|numeric',

            'id_cuenta_inventario'   => 'required|numeric',
            'id_cuenta_cxp'   => 'required|numeric',
            'id_cuenta_devoluciones_proveedores'   => 'required|numeric',
            'id_cuenta_iva_compras'   => 'required|numeric',
            'id_cuenta_iva_retenido_compras'   => 'required|numeric',
            'id_cuenta_renta_retenida_compras'   => 'required|numeric',
            'generar_partidas'   => 'required',
            'id_empresa'   => 'required|numeric',
        ],[
            'id_cuenta_ventas.required' => 'La cuenta para ingresos es requerida',
            'id_cuenta_devoluciones_ventas.required' => 'La cuenta para devoluciones ventas es requerida',
            'id_cuenta_iva_ventas.required' => 'La cuenta para IVA de ventas es requerida',
            'id_cuenta_iva_retenido_ventas.required' => 'La cuenta para IVA retenida de ventas es requerida',
            'id_cuenta_renta_retenida_ventas.required' => 'La cuenta para Renta retenida de ventas es requerida',
            'id_cuenta_cxc.required' => 'La cuenta para cxc es requerida',
            
            'id_cuenta_costo_venta.required' => 'La cuenta para costo de venta es requerida',

            'id_cuenta_inventario.required' => 'La cuenta para inventario es requerida',
            'id_cuenta_cxp.required' => 'La cuenta para cxp es requerida',
            'id_cuenta_devoluciones_proveedores.required' => 'La cuenta para devoluciones proveedores es requerida',
            'id_cuenta_iva_compras.required' => 'La cuenta para iIVA de ompras es requerida',
            'id_cuenta_iva_retenido_compras.required' => 'La cuenta para IVA retenida de compras es requerida',
            'id_cuenta_renta_retenida_compras.required' => 'La cuenta para Renta retenida de compras es requerida',
        ]);

        if($request->id)
            $configuracion = Configuracion::findOrFail($request->id);
        else
            $configuracion = new Configuracion;
        
        $configuracion->fill($request->all());
        $configuracion->save();

        return Response()->json($configuracion, 200);

    }

    public function delete($id)
    {
       
        $configuracion = Configuracion::findOrFail($id);
        $configuracion->delete();

        return Response()->json($configuracion, 201);

    }


}

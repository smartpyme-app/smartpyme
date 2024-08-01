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
            'id_cuenta_ingresos'   => 'required|numeric',
            'id_cuenta_devoluciones_ventas'   => 'required|numeric',
            'id_cuenta_inventario'   => 'required|numeric',
            'id_cuenta_ajustes_inventario'   => 'required|numeric',
            'id_cuenta_cxc'   => 'required|numeric',
            'id_cuenta_devoluciones_clientes'   => 'required|numeric',
            'id_cuenta_cxp'   => 'required|numeric',
            'id_cuenta_devoluciones_proveedores'   => 'required|numeric',
            'id_empresa'   => 'required|numeric',
        ],[
            'id_cuenta_ingresos.required' => 'La cuenta para ingresos es requerida',
            'id_cuenta_devoluciones_ventas.required' => 'La cuenta para devoluciones ventas es requerida',
            'id_cuenta_inventario.required' => 'La cuenta para inventario es requerida',
            'id_cuenta_ajustes_inventario.required' => 'La cuenta para ajustes inventario es requerida',
            'id_cuenta_cxc.required' => 'La cuenta para cxc es requerida',
            'id_cuenta_devoluciones_clientes.required' => 'La cuenta para devoluciones clientes es requerida',
            'id_cuenta_cxp.required' => 'La cuenta para cxp es requerida',
            'id_cuenta_devoluciones_proveedores.required' => 'La cuenta para devoluciones proveedores es requerida',
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

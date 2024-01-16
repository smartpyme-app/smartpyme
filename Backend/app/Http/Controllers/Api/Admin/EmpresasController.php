<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Transaccion;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade as PDF;
use Intervention\Image\ImageManagerStatic as Image;
use JWTAuth;

class EmpresasController extends Controller
{
    

    public function index(Request $request) {
       
        $empresas = Empresa::when($request->activo !== null, function($q) use ($request){
                                    $q->where('activo', !!$request->activo);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('correo', 'like' ,"%" . $request->buscador . "%");
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($empresas, 200);

    }

    public function list() {
       
        $empresas = Empresa::orderby('nombre')
                                ->where('activo', true)
                                ->get();

        return Response()->json($empresas, 200);

    }


    public function read($id) {

        $empresa = Empresa::findOrFail($id);
        return Response()->json($empresa, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'iva'       => 'required|numeric',
        ]);

        if($request->id)
            $empresa = Empresa::findOrFail($request->id);
        else
            $empresa = new Empresa;
        
        $empresa->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $empresa->logo && $empresa->logo != 'empresas/default.jpg') {
                Storage::delete($empresa->logo);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(350,350)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "empresas/{$hash}.jpg";
            $resize->save(public_path('img/'.$path), 50);
            $empresa->logo = "/" . $path;
        }

        $empresa->save();

        return Response()->json($empresa, 200);

    }

    public function delete($id)
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return Response()->json($empresa, 201);

    }

    public function suscripcion()
    {
        $empresa = Empresa::with('pagos')->where('id', JWTAuth::parseToken()->authenticate()->id_empresa)->firstOrFail();
        $empresa->next_pay  = $empresa->getNextPayAttribute();
        $empresa->total  = $empresa->total;

        if ($empresa->next_pay >= date('Y-m-d')) {
            $empresa->estado  = 'Activo';
        }else{
            $empresa->estado  = 'Vencido';
        }

        return Response()->json($empresa, 201);

    }

    public function printRecibo($id){

        $recibo = Transaccion::where('id', $id)->firstOrFail();
        // return $recibo;
        $pdf = PDF::loadView('reportes.recibo-suscripcion', compact('recibo'));
        $pdf->setPaper('US Letter', 'portrait');  

        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    }

    public function eliminarDatos(Request $request){
        $empresa = Empresa::findOrfail($request->id);

        if ($request->m_inventario) {
            $productos = $empresa->productos;
            foreach ($productos as $producto) {
                $producto->kardex()->delete();
                $producto->precios()->delete();
                $producto->traslados()->delete();
                $producto->ajustes()->delete();
                $producto->inventarios()->delete();
                $producto->delete();
            }
        }

        if ($request->m_promociones) {
            $empresa->promociones()->delete();
        }

        if ($request->m_categorias) {
            $empresa->categorias()->delete();
        }

        if ($request->m_clientes) {
            $empresa->clientes()->delete();
        }

        if ($request->m_proveedores) {
            $empresa->proveedores()->delete();
        }

        if ($request->m_ventas) {
            $ventas = $empresa->ventas;
            foreach ($ventas as $venta) {
                $venta->detalles()->delete();
                $venta->delete();
            }
            $deventas = $empresa->deventas;
            foreach ($deventas as $deventa) {
                $deventa->detalles()->delete();
                $deventa->delete();
            }
        }
        if ($request->m_compras) {
            $compras = $empresa->compras;
            foreach ($compras as $compra) {
                $compra->detalles()->delete();
                $compra->delete();
            }
            $decompras = $empresa->decompras;
            foreach ($decompras as $decompra) {
                $decompra->detalles()->delete();
                $decompra->delete();
            }
        }
        if ($request->m_gastos) {
            $gastos = $empresa->gastos;
            foreach ($gastos as $gasto) {
                $gasto->delete();
            }
        }
        if ($request->m_presupuestos) {
            $presupuestos = $empresa->presupuestos;
            foreach ($presupuestos as $presupuesto) {
                $presupuesto->delete();
            }
        }

        return Response()->json($empresa, 201);
    }

}

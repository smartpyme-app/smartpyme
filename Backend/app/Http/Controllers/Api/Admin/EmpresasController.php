<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Transaccion;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\DB;
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
        $empresa = Empresa::where('id', $request->id)->firstOrFail();
        $sucursales = $empresa->sucursales()->pluck('id')->toArray();

        if ($request->m_inventario) {
            DB::table('productos')->where('id_empresa', $empresa->id)->update(['deleted_at' => Carbon::now()]);
            DB::table('inventario')->whereIn('id_sucursal', $sucursales)->update(['deleted_at' => Carbon::now()]);
            DB::table('ajustes')->where('id_empresa', $empresa->id)->delete();
            DB::table('traslados')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_categorias) {
            DB::table('categorias')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_clientes) {
            DB::table('clientes')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_proveedores) {
            DB::table('proveedores')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_ventas) {
            DB::table('ventas')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_venta')->where('id_empresa', $empresa->id)->delete();
        }
        if ($request->m_compras) {
            DB::table('compras')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_compra')->where('id_empresa', $empresa->id)->delete();
        }
        if ($request->m_gastos) {
            DB::table('egresos')->where('id_empresa', $empresa->id)->delete();
        }
        if ($request->m_presupuestos) {
            DB::table('presupuestos')->where('id_empresa', $empresa->id)->delete();
        }


        return Response()->json($empresa, 200);
    }

}

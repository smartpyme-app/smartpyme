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
        $empresa = Empresa::with('productos', 'categorias', 'clientes', 'proveedores', 'ventas', 'compras', 'presupuestos', 'gastos', 'deventas', 'decompras')->where('id', $request->id)->firstOrFail();
        
        if ($request->m_inventario) {
            return $producto->ajustes()->count();
            $empresa->productos->each(function ($producto) {
                $producto->ajustes()->chunk(200, function ($ajustes) {
                    $ajustes->each->delete();
                });
                $producto->kardex()->chunk(200, function ($kardex) {
                    $kardex->each->delete();
                });
                $producto->precios()->chunk(200, function ($precios) {
                    $precios->each->delete();
                });
                $producto->traslados()->chunk(200, function ($traslados) {
                    $traslados->each->delete();
                });
                $producto->ajustes()->chunk(200, function ($ajustes) {
                    $ajustes->each->delete();
                });
                $producto->inventarios()->chunk(200, function ($inventarios) {
                    $inventarios->each->delete();
                });
            });
            
            $empresa->productos()->chunk(200, function ($productos) {
                $productos->each->delete();
            });
        }

        if ($request->m_categorias) {
            $empresa->categorias()->delete();
        }

        if ($request->m_clientes) {
            $empresa->clientes()->chunk(200, function ($clientes) {
                $clientes->delete();
            });
        }

        if ($request->m_proveedores) {
            $empresa->proveedores()->chunk(200, function ($proveedores) {
                $proveedores->delete();
            });
        }

        if ($request->m_ventas) {
            $empresa->ventas->each(function ($venta) {
                $venta->detalles()->chunk(200, function ($detalles) {
                    $detalles->delete();
                });
            });
            $empresa->ventas()->chunk(200, function ($ventas) {
                $ventas->delete();
            });

            $empresa->deventas->each(function ($deventa) {
                $deventa->detalles()->chunk(200, function ($detalles) {
                    $detalles->delete();
                });
            });
            $empresa->deventas()->chunk(200, function ($deventas) {
                $deventas->delete();
            });
        }
        if ($request->m_compras) {
            $empresa->compras->each(function ($compra) {
                $compra->detalles()->chunk(200, function ($detalles) {
                    $detalles->delete();
                });
            });
            $empresa->compras()->chunk(200, function ($compras) {
                $compras->delete();
            });

            $empresa->decompras->each(function ($decompra) {
                $decompra->detalles()->chunk(200, function ($detalles) {
                    $detalles->delete();
                });
            });
            $empresa->decompras()->chunk(200, function ($decompras) {
                $decompras->delete();
            });
        }
        if ($request->m_gastos) {
            $empresa->gastos->each(function ($gasto) {
                $gasto->detalles()->chunk(200, function ($detalles) {
                    $detalles->delete();
                });

            });
            $empresa->gastos()->chunk(200, function ($gastos) {
                $gastos->delete();
            });
        }
        if ($request->m_presupuestos) {
            $empresa->presupuestos->each(function ($presupuesto) {
                $presupuesto->detalles()->delete();
            });
            $empresa->presupuestos()->delete();
        }


        return Response()->json($empresa, 200);
    }

}

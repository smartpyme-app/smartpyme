<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Empleados\Empleados\Empleado;

class EmpleadosController extends Controller
{
    

    public function index() {
       
        $empleados = Empleado::orderBy('id','desc')->paginate(200);

        return Response()->json($empleados, 200);

    }

    public function motoristas() {
       
        $motoristas = Empleado::motoristas()->get();

        return Response()->json($motoristas, 200);

    }

    public function list() {
       
        $empleados = Empleado::orderBy('id','desc')->get();

        return Response()->json($empleados, 200);

    }


    public function read($id) {
        
        $empleado = Empleado::where('id', $id)->with('documentos', 'cuenta')->firstOrFail();

        return Response()->json($empleado, 200);
    }

    public function filter(Request $request) {
        
        $empleado = Empleado::when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->cargo_id, function($query) use ($request){
                                    return $query->where('cargo_id', $request->cargo_id);
                                })
                                ->orderBy('id','desc')->paginate(100000);

        return Response()->json($empleado, 200);
    }

    public function search($txt) {

        $empleados = Empleado::where('name', 'like' ,'%' . $txt . '%')->paginate(200);
        return Response()->json($empleados, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|max:255',
            // 'cargo_id' => 'required|numeric',
        ],[
            'cargo_id.required' => 'Debe agregarle un cargo al empleado.'
        ]);

        if($request->id)
            $empleado = Empleado::findOrFail($request->id);
        else
            $empleado = new Empleado;
        
        $empleado->fill($request->all());
        $empleado->save();

        return Response()->json($empleado, 200);


    }

    public function delete($id)
    {
       
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();

        return Response()->json($empleado, 201);

    }

    public function carnet($id)
    {
        
        $empleado = Empleado::where('id', $id)->firstOrFail();

        $reportes = \PDF::loadView('reportes.empleados.carnet', compact('empleado'))
                    ->setPaper([0, 0, 155.906, 240.945]);
                    // ->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

    public function comisiones($id)
    {
        
        $empleado = Empleado::where('id', $id)->with('comisiones')->firstOrFail();

        return Response()->json($empleado, 200); 

    }

    public function ventas(Request $request)
    {
        
        $empleados = Empleado::orderBy('nombre', 'ASC')->get();

        foreach ($empleados as $empleado) {
            $ventas = $empleado->ventas()->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->ano);
            $empleado->ventas_brutas_mes = $ventas->sum('total');
            $empleado->ventas_netas_mes = $ventas->sum('subtotal');
            $empleado->comisiones_mes = $empleado->comisiones()->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->ano)->sum('total');
            $empleado->meta_mes = $empleado->metas()->where('mes', $request->mes)->where('ano', $request->ano)->pluck('meta')->first();
        }

        return Response()->json($empleados, 200); 

    }



}

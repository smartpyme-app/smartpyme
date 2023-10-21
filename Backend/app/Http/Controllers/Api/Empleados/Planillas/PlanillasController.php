<?php

namespace App\Http\Controllers\Api\Empleados\Planillas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Planillas\Planilla;
use App\Models\Empleados\Planillas\Detalle;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\Empresa;
use Carbon\Carbon;
use JWTAuth;

class PlanillasController extends Controller
{
    

    public function index() {
       
        $planillas = Planilla::orderBy('created_at', 'desc')->paginate(12);
        return Response()->json($planillas, 200);

    }


    public function read($id) {
        
        $planilla = Planilla::where('id', $id)->with('detalles')->firstOrFail();
        return Response()->json($planilla, 200);

    }

    public function filter(Request $request) {


        $planillas = Planilla::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->orderBy('created_at','desc')->paginate(100000);

        return Response()->json($planillas, 200);

    }

    public function store(Request $request) {
        
        $request->validate([
            'fecha'         => 'required|date',
            'tipo'  => 'required',
            'estado'          => 'required|max:255',
            'total'         => 'required|numeric',
            'nota'          => 'sometimes|max:255',
            'usuario_id'    => 'required|numeric',
            'empresa_id'    => 'required|numeric',
        ]);

        if($request->id)
            $planilla = Planilla::findOrFail($request->id);
        else
            $planilla = new Planilla;
        
        $planilla->fill($request->all());
        $planilla->save();

        return Response()->json($planilla, 200);


    }

    public function proceso(Request $request){

        $request->validate([
            'fecha'         => 'required|date',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'required|date',
            'total'         => 'required|numeric',
            'nota'         => 'sometimes|max:255',
            'usuario_id'    => 'required|numeric',
            'empresa_id'    => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar empleados a la planilla',
        ]);

        DB::beginTransaction();
         
        try {
        
        // Guardamos la venta
            if($request->id)
                $planilla = Planilla::findOrFail($request->id);
            else
                $planilla = new Planilla;
            $planilla->fill($request->all());
            $planilla->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $det['planilla_id'] = $planilla->id;
                $detalle->fill($det);
                $detalle->save();
                
            }
        
        DB::commit();
        return Response()->json($planilla, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        

    }


    public function search($txt) {

        $planillas = Planilla::where('nombre', 'like' ,'%' . $txt . '%')->get();
        return Response()->json($planillas, 200);

    }


    public function delete($id)
    {
       
        $planilla = Planilla::findOrFail($id);
        $planilla->detalles()->delete();
        $planilla->delete();

        return Response()->json($planilla, 201);

    }

    public function planilla($id) {

        $planilla = Planilla::where('id', $id)->with('detalles', 'empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.empleados.planilla', compact('planilla'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

    public function boletas($id) {

        $planilla = Planilla::where('id', $id)->with('detalles', 'empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.empleados.boletas', compact('planilla'))->setPaper('letter');
        return $reportes->stream();

    }



}

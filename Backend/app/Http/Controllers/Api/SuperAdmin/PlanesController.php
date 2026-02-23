<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanesController extends Controller
{

    public function index(Request $request) {
       
        $planes = Plan::paginate();

        return Response()->json($planes, 200);

    }

    public function read($id) {
        
        $plan = Plan::where('id', $id)->firstOrFail();
        return Response()->json($plan, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|max:255',
            'precio'          => 'required',
            'id_producto'     => 'required',
        ]);

        if($request->id)
            $plan = Plan::findOrFail($request->id);
        else
            $plan = new Plan;

        $plan->fill($request->all());
        $plan->save();

        return Response()->json($plan, 200);

    }

    public function delete($id)
    {
       
        $plan = Plan::findOrFail($id);
        $plan->delete();

        return Response()->json($plan, 201);

    }

    public function getPlanesforSelect()
    {
        $planes = Plan::select('id', 'nombre', 'precio', 'duracion_dias')
            ->orderBy('precio', 'asc')
            ->get();
        
        return response()->json($planes);
    }

    /** Se obtienen los planes activos para el registro público y se agrupan por tipo (Mensual, Trimestral, Anual) */
    public function getPlanesPublicos()
    {
        $planes = Plan::where('activo', true)
            ->select('id', 'nombre', 'precio', 'duracion_dias', 'slug')
            ->orderBy('precio', 'asc')
            ->get();
        
        // se agrupan los planes por tipo de plan según duracion_dias
        $planesAgrupados = [
            'Mensual' => [],
            'Trimestral' => [],
            'Anual' => []
        ];
        
        foreach ($planes as $plan) {
            $tipoPlan = $this->getTipoPlan($plan->duracion_dias);
            if ($tipoPlan && isset($planesAgrupados[$tipoPlan])) {
                $planesAgrupados[$tipoPlan][] = $plan;
            }
        }
        
        return response()->json([
            'planes' => $planes,
            'planes_agrupados' => $planesAgrupados
        ]);
    }
    
    /** se determina el tipo de plan según la duración en días */
    private function getTipoPlan($duracionDias)
    {
        if ($duracionDias == 30) {
            return 'Mensual';
        }
        if ($duracionDias == 90) {
            return 'Trimestral';
        }
        if ($duracionDias == 365 || $duracionDias == 360) {
            return 'Anual';
        }
        return null;
    }

}

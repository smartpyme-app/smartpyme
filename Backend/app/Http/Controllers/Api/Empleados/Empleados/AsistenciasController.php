<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Empleados\Asistencia;
use App\Models\Empleados\Empleados\Empleado;
use App\Models\Admin\Empresa;
use Carbon\Carbon;
use JWTAuth;

class AsistenciasController extends Controller
{
    

    public function index() {
       
        $asistencias = Asistencia::orderBy('id', 'desc')->paginate(12);
        return Response()->json($asistencias, 200);

    }


    public function read($id) {
        
        $asistencia = Asistencia::findOrFail($id);
        return Response()->json($asistencia, 200);

    }

    public function search($txt) {

        $asistencias = Asistencia::whereHas('empleado', function($query) use ($txt){
                            return $query->where('nombre', 'like', '%'.$txt.'%');
                        })
                        ->orderBy('entrada','desc')->paginate(100000);

        return Response()->json($asistencias, 200);

    }

    public function filter(Request $request) {


        $asistencias = Asistencia::
                        when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->empleado_id, function($query) use ($request){
                            return $query->where('empleado_id', $request->empleado_id);
                        })
                        ->orderBy('entrada','desc')->paginate(100000);

        return Response()->json($asistencias, 200);

    }

    public function store(Request $request) {
        
        $request->validate([
            'fecha'         => 'required|date',
            'estado'        => 'required|max:255',
            'empleado_id'   => 'required|numeric',
            'nota'          => 'sometimes|max:255',
        ]);

        if($request->id)
            $asistencia = Asistencia::findOrFail($request->id);
        else
            $asistencia = new Asistencia;
        
        $asistencia->fill($request->all());
        $asistencia->save();

        return Response()->json($asistencia, 200);


    }

    public function marcar(Request $request) {
        
        $request->validate([
            'fecha'         => 'required|date',
            'estado'        => 'required|max:255',
            'empleado_id'   => 'required|numeric',
            'nota'          => 'sometimes|max:255',
        ]);

        $empleado = Empleado::findOrFail($request->empleado_id);

        $asistenciaDiaria = $empleado->asistenciaDiaria()->first();

        if($asistenciaDiaria){
            $asistencia = Asistencia::findOrFail($asistenciaDiaria->id);

            if ($asistencia->entrada && !$asistencia->salida) {
                $asistencia->salida = date('H:i:s');
            }else{
                return Response()->json(['error' => ['Ya se han resgitrado la asistencia del día'], 'code' => 422], 422);
            }
        }
        else{
            $asistencia = new Asistencia;
            $asistencia->entrada = date('H:i:s');
            $asistencia->fill($request->all());
        }
        
        $asistencia->save();

        return Response()->json($asistencia, 200);


    }


    public function delete($id)
    {
       
        $asistencia = Asistencia::findOrFail($id);
        $asistencia->delete();

        return Response()->json($asistencia, 201);

    }

    public function empleados(){
        $fech = '2021-11-17';
        $fin = '2021-11-17';

        $empleados = Empleado::all();

        foreach ($empleados as $empleado) {
            $asistencia = $empleado->asistenciaDiaria()->first();

            $empleado->entrada = $asistencia ? $asistencia->entrada : null;
            $empleado->salida = $asistencia ? $asistencia->salida : null;
            $empleado->horas = $asistencia ? $asistencia->horas_laborales - $asistencia->horas_extras : null;
            $empleado->horas_laborales = $asistencia ? $asistencia->horas_laborales : null;
            $empleado->horas_extras = $asistencia ? $asistencia->horas_extras : null;
        }

        return Response()->json($empleados, 201);

    }

    public function asistenciaDiaria() {
        
        $empleados = Empleado::orderBy('nombre', 'asc')->get();

        foreach ($empleados as $empleado) {
            $asistencia = $empleado->asistenciaDiaria()->first();
            if ($asistencia) {
                $empleado->estado_asistencia = $asistencia->estado ? $asistencia->estado : null;
                $empleado->entrada = $asistencia->entrada ? ($asistencia->fecha . ' ' . $asistencia->entrada) : null;
                $empleado->salida = $asistencia->salida ? ($asistencia->fecha . ' ' . $asistencia->salida)  : null;
                $empleado->horas = $asistencia->horas ? $asistencia->horas : null;
                $empleado->nota = $asistencia->nota ? $asistencia->nota : null;
            }
            else{
                $empleado->estado_asistencia = 'Falta';
            }
        }
        
        $empleados->total_asistencias = Empleado::whereHas('asistenciaDiaria', function($q){$q->where('estado', 'Asistencia'); })->count();
        $empleados->total_permisos = Empleado::whereHas('asistenciaDiaria', function($q){$q->where('estado', 'Permiso'); })->count();
        $empleados->total_faltas = Empleado::doesnthave('asistenciaDiaria')->count();

        $empleados->empresa = Empresa::where('id', JWTAuth::parseToken()->authenticate()->sucursal()->first()->empresa_id)->first();


        if ($empleados) {
            $reportes = \PDF::loadView('reportes.empleados.asistencia-diaria', compact('empleados'))->setPaper('letter', 'landscape');
            return $reportes->stream();
        }else{
            return "No hay asistencias entontradas";
        }

    }

    public function asistenciaMensual() {
        
        $empleados = Empleado::all();

        foreach ($empleados as $empleado) {
            if ($empleado->asistenciaMensual()->count() > 0) {
                $asistencias = $empleado->asistenciaMensual()->get();
                $empleado->dias_asistencias = $asistencias->where('estado', 'Asistencia')->count();
                $empleado->dias_permisos = $asistencias->where('estado', 'Permiso')->count();
            }
        }

        $empleados->total_asistencias = Empleado::whereHas('asistenciaMensual', function($q){$q->where('estado', 'Asistencia'); })->count();
        $empleados->total_permisos = Empleado::whereHas('asistenciaMensual', function($q){$q->where('estado', 'Permiso'); })->count();
        $empleados->total_faltas = Empleado::doesnthave('asistenciaMensual')->count();
        $empleados->fecha = Carbon::today();
        $empleados->empresa = Empresa::where('id', JWTAuth::parseToken()->authenticate()->sucursal()->first()->empresa_id)->first();


        if ($empleados) {
            $reportes = \PDF::loadView('reportes.empleados.asistencia-mensual', compact('empleados'))->setPaper('letter', 'landscape');
            return $reportes->stream();
        }else{
            return "No hay asistencias entontradas";
        }

    }



}

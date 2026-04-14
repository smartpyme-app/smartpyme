<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use App\Models\Planilla\DepartamentoEmpresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Planilla\StoreDepartamentoEmpresaRequest;
use App\Http\Requests\Planilla\UpdateDepartamentoEmpresaRequest;

class DepartamentosEmpresaController extends Controller
{

    public function index(Request $request)
    {
        //$query = DepartamentoEmpresa::where('id_sucursal', $request->user()->id_sucursal)
        $query = DepartamentoEmpresa::where('id_empresa', $request->user()->id_empresa)
            ->where('activo', true);

        if ($request->filled('buscador')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'LIKE', "%{$request->buscador}%")
                    ->orWhere('descripcion', 'LIKE', "%{$request->buscador}%");
            });
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

//        if ($request->has('id_sucursal')) {
//            $query->where('id_sucursal', $request->id_sucursal);
//        }

        // Ordenamiento
        $orden = $request->orden ?? 'nombre';
        $direccion = $request->direccion ?? 'asc';
        $query->orderBy($orden, $direccion);

        return $query->paginate($request->get('paginate', 10));
    }

    public function store(StoreDepartamentoEmpresaRequest $request)
    {

        $departamento = DepartamentoEmpresa::updateOrCreate(
            ['id' => $request->id],
            $request->all() + [
                'id_sucursal' => auth()->user()->id_sucursal,
                'id_empresa' => auth()->user()->id_empresa,
                'estado' => PlanillaConstants::ESTADO_ACTIVO,
                'activo' => 1
            ]
        );

        return $departamento;
    }

    public function update(UpdateDepartamentoEmpresaRequest $request)
    {

        $departamento = DepartamentoEmpresa::updateOrCreate(
            ['id' => $request->id],
            [
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'activo' => $request->activo,
                'estado' => $request->estado,
                'id_sucursal' => auth()->user()->id_sucursal,
                'id_empresa' => auth()->user()->id_empresa,
            ]
        );

        return $departamento;
    }

    public function show($id)
    {
        return DepartamentoEmpresa::findOrFail($id);
    }

    public function list()
    {
        return DepartamentoEmpresa::where('id_sucursal', auth()->user()->id_sucursal)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    public function changeState($id)
    {
        $departamento = DepartamentoEmpresa::findOrFail($id);
        $departamento->estado = $departamento->estado == PlanillaConstants::ESTADO_ACTIVO ? PlanillaConstants::ESTADO_INACTIVO : PlanillaConstants::ESTADO_ACTIVO;
        $departamento->save();
        return $departamento;
    }
}

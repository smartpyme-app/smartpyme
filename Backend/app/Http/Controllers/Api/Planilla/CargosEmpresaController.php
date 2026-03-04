<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use App\Models\Planilla\CargoEmpresa;
use Illuminate\Http\Request;

class CargosEmpresaController extends Controller
{
    public function index(Request $request)
    {
        $query = CargoEmpresa::where('id_sucursal', $request->user()->id_sucursal)
            ->where('activo', true);

        if ($request->has('buscador')) {
            $query->where('nombre', 'LIKE', "%{$request->buscador}%")
                  ->orWhere('descripcion', 'LIKE', "%{$request->buscador}%");
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('id_departamento')) {
            $query->where('id_departamento', $request->id_departamento);
        }

        // Ordenamiento
        $orden = $request->orden ?? 'nombre';
        $direccion = $request->direccion ?? 'asc';
        $query->orderBy($orden, $direccion);

        return $query->paginate($request->get('paginate', 10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'salario_base' => 'nullable|numeric|min:0',
            'activo' => 'required|boolean'
        ]);

        $cargo = CargoEmpresa::updateOrCreate(
            ['id' => $request->id],
            $request->all() + [
                'id_sucursal' => $request->user()->id_sucursal,
                'id_empresa' => $request->user()->id_empresa,
                'id_departamento' => $request->id_departamento,
                'salario_base' => $request->salario_base ?? 0,
                'estado' => PlanillaConstants::ESTADO_ACTIVO
            ]
        );

        return $cargo;
    }

    public function show($id)
    {
        return CargoEmpresa::findOrFail($id);
    }

    public function list()
    {
        return CargoEmpresa::where('id_sucursal', auth()->user()->id_sucursal)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }
}

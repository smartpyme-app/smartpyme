<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use App\Models\Planilla\DepartamentoEmpresa;
use Illuminate\Http\Request;

class DepartamentosEmpresaController extends Controller
{
  
    public function index(Request $request)
    {
        $query = DepartamentoEmpresa::where('id_sucursal', $request->user()->id_sucursal)
            ->where('activo', true);

        if ($request->has('buscador')) {
            $busqueda = $request->buscador;
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'LIKE', "%{$busqueda}%")
                  ->orWhere('descripcion', 'LIKE', "%{$busqueda}%");
            });
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
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
            'activo' => 'required|boolean'
        ]);

        $departamento = DepartamentoEmpresa::updateOrCreate(
            ['id' => $request->id],
            $request->all() + [
                'id_sucursal' => auth()->user()->id_sucursal,
                'id_empresa' => auth()->user()->id_empresa,
                'estado' => PlanillaConstants::ESTADO_ACTIVO
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
}

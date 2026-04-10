<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Http\Controllers\Controller;
use App\Models\Admin\Sucursal;
use App\Models\Compras\Gastos\AreaEmpresa;
use App\Models\Planilla\DepartamentoEmpresa;
use Illuminate\Http\Request;

class DepartamentosEmpresaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DepartamentoEmpresa::where('id_empresa', $user->id_empresa);

        if ($request->filled('id_sucursal')) {
            $query->where('id_sucursal', $request->id_sucursal);
        } elseif ($user->tipo !== 'Administrador') {
            $query->where('id_sucursal', $user->id_sucursal);
        }

        if ($request->filled('buscador')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'LIKE', "%{$request->buscador}%")
                    ->orWhere('descripcion', 'LIKE', "%{$request->buscador}%");
            });
        }

        if ($request->has('estado') && $request->estado !== '') {
            $query->where('estado', $request->estado);
        }

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
            'activo' => 'sometimes',
            'id_sucursal' => 'nullable|integer',
            'estado' => 'sometimes',
        ]);

        $user = $request->user();
        $idSucursal = (int) ($request->input('id_sucursal') ?: $user->id_sucursal);

        $sucursalOk = Sucursal::where('id', $idSucursal)->where('id_empresa', $user->id_empresa)->exists();
        if (!$sucursalOk) {
            return response()->json(['message' => 'Sucursal inválida'], 422);
        }

        $activo = true;
        if ($request->has('estado')) {
            $activo = in_array($request->estado, [1, '1', true, 'true'], true);
        } elseif ($request->has('activo')) {
            $activo = $request->boolean('activo');
        }

        $data = [
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'id_sucursal' => $idSucursal,
            'id_empresa' => $user->id_empresa,
            'activo' => $activo,
            'estado' => $activo ? PlanillaConstants::ESTADO_ACTIVO : PlanillaConstants::ESTADO_INACTIVO,
        ];

        if ($request->filled('id')) {
            $dep = DepartamentoEmpresa::where('id_empresa', $user->id_empresa)->findOrFail($request->id);
            $dep->update($data);

            return $dep;
        }

        return DepartamentoEmpresa::create($data);
    }

    public function show(Request $request, $id)
    {
        return DepartamentoEmpresa::where('id_empresa', $request->user()->id_empresa)->findOrFail($id);
    }

    public function list(Request $request)
    {
        $user = $request->user();
        $idSucursal = $request->get('id_sucursal', $user->id_sucursal);

        return DepartamentoEmpresa::where('id_sucursal', $idSucursal)
            ->where('id_empresa', $user->id_empresa)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    public function changeState(Request $request, $id)
    {
        $dep = DepartamentoEmpresa::where('id_empresa', $request->user()->id_empresa)->findOrFail($id);

        $activo = $dep->activo;
        if ($request->has('estado')) {
            $activo = in_array($request->estado, [1, '1', true, 'true'], true);
        } else {
            $activo = !$dep->activo;
        }

        $dep->activo = $activo;
        $dep->estado = $activo ? PlanillaConstants::ESTADO_ACTIVO : PlanillaConstants::ESTADO_INACTIVO;
        $dep->save();

        return $dep;
    }

    public function destroy(Request $request, $id)
    {
        $dep = DepartamentoEmpresa::where('id_empresa', $request->user()->id_empresa)->findOrFail($id);
        $dep->delete();

        return response()->json(['message' => 'Departamento eliminado', 'id' => $id]);
    }

    public function areas(Request $request, $id)
    {
        DepartamentoEmpresa::where('id_empresa', $request->user()->id_empresa)->findOrFail($id);

        return AreaEmpresa::where('id_departamento', $id)->orderBy('nombre')->get();
    }
}

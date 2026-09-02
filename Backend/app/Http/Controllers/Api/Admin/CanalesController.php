<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Canal;
use App\Http\Requests\Admin\Canales\StoreCanalRequest;

class CanalesController extends Controller
{
    public function index()
    {
        $canales = Canal::orderBy('nombre', 'asc')->get();

        return Response()->json($canales, 200);
    }

    public function list()
    {
        $canales = Canal::where('enable', true)->orderBy('nombre', 'asc')->get();

        return Response()->json($canales, 200);
    }

    public function read($id)
    {
        $canal = Canal::findOrFail($id);

        return Response()->json($canal, 200);
    }

    public function store(StoreCanalRequest $request)
    {
        if ($request->id) {
            $canal = Canal::findOrFail($request->id);
        } else {
            $canal = new Canal;
        }

        if ($request->change && $request->predeterminado) {
            $this->updatePredeterminado(
                (int) ($canal->id_empresa ?: $request->id_empresa),
                $request->id ? (int) $request->id : null
            );
        }

        $canal->fill($request->all());
        $canal->save();

        return Response()->json($canal, 200);
    }

    public function delete($id)
    {
        $canal = Canal::findOrFail($id);
        $canal->delete();

        return Response()->json($canal, 201);
    }

    private function updatePredeterminado(int $idEmpresa, ?int $excludeId = null): void
    {
        $query = Canal::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where('predeterminado', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update(['predeterminado' => false]);
    }
}

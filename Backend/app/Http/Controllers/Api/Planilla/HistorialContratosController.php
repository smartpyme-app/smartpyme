<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Planilla\HistorialContrato;
use Illuminate\Http\Request;
use App\Http\Requests\Planilla\StoreHistorialContratoRequest;

class HistorialContratosController extends Controller
{
    public function porEmpleado($id)
    {
        return HistorialContrato::with(['cargo'])
            ->where('id_empleado', $id)
            ->orderBy('fecha_inicio', 'desc')
            ->get();
    }

    public function store(StoreHistorialContratoRequest $request)
    {

        // Cerrar contrato anterior si existe
        HistorialContrato::where('id_empleado', $request->id_empleado)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $request->fecha_inicio]);

        // Crear nuevo contrato
        return HistorialContrato::create($request->all());
    }
}
<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Planilla\HistorialContrato;
use Illuminate\Http\Request;

class HistorialContratosController extends Controller
{
    public function porEmpleado($id)
    {
        return HistorialContrato::with(['cargo'])
            ->where('id_empleado', $id)
            ->orderBy('fecha_inicio', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_empleado' => 'required|exists:empleados,id',
            'fecha_inicio' => 'required|date',
            'tipo_contrato' => 'required|string',
            'salario' => 'required|numeric|min:0',
            'id_cargo' => 'required|exists:cargos_de_empresa,id',
            'motivo_cambio' => 'required|string'
        ]);

        // Cerrar contrato anterior si existe
        HistorialContrato::where('id_empleado', $request->id_empleado)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $request->fecha_inicio]);

        // Crear nuevo contrato
        return HistorialContrato::create($request->all());
    }
}
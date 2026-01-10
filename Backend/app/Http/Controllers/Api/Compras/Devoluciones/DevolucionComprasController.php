<?php

namespace App\Http\Controllers\Api\Compras\Devoluciones;

use App\Http\Controllers\Controller;
use App\Services\Compras\DevolucionCompraService;
use Illuminate\Http\Request;
use App\Exports\DevolucionesComprasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Compras\Devoluciones\StoreDevolucionCompraRequest;
use App\Http\Requests\Compras\Devoluciones\FacturacionDevolucionCompraRequest;

class DevolucionComprasController extends Controller
{
    protected $devolucionService;

    public function __construct(DevolucionCompraService $devolucionService)
    {
        $this->devolucionService = $devolucionService;
    }

    public function index(Request $request)
    {
        $devoluciones = $this->devolucionService->listarDevoluciones($request->all());
        return Response()->json($devoluciones, 200);
    }

    public function read($id)
    {
        $devolucion = $this->devolucionService->obtenerDevolucion($id);
        return Response()->json($devolucion, 200);
    }

    public function store(StoreDevolucionCompraRequest $request)
    {
        $devolucion = $this->devolucionService->crearOActualizarDevolucion($request->all());
        return Response()->json($devolucion, 200);
    }

    public function delete($id)
    {
        $devolucion = $this->devolucionService->eliminarDevolucion($id);
        return Response()->json($devolucion, 201);
    }

    public function facturacion(FacturacionDevolucionCompraRequest $request)
    {
        try {
            $devolucion = $this->devolucionService->procesarDevolucion($request->all());
            return Response()->json($devolucion, 200);
        } catch (\Exception $e) {
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request)
    {
        $compras = new DevolucionesComprasExport();
        $compras->filter($request);
        return Excel::download($compras, 'compras.xlsx');
    }
}

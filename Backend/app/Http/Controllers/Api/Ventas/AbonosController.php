<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Services\Ventas\AbonoVentaService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Paquete;
use Illuminate\Support\Facades\DB;
use App\Exports\AbonosVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Ventas\Abonos\StoreAbonoRequest;
use App\Http\Requests\Ventas\Abonos\UpdateAbonoRequest;

class AbonosController extends Controller
{
    protected $abonoService;

    public function __construct(AbonoVentaService $abonoService)
    {
        $this->abonoService = $abonoService;
    }

    public function index(Request $request)
    {
        $abonos = $this->abonoService->listarAbonos($request->all());
        return Response()->json($abonos, 200);
    }

    public function read($id)
    {
        $abono = $this->abonoService->obtenerAbono($id);
        return Response()->json($abono, 200);
    }

    public function store(StoreAbonoRequest $request)
    {
        try {
            $abono = $this->abonoService->crearOActualizarAbono($request->all());
            return Response()->json($abono, 200);
        } catch (\Exception $e) {
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(UpdateAbonoRequest $request)
    {
        try {
            $abono = $this->abonoService->actualizarAbono($request->all());
            return response()->json($abono, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete($id)
    {
        $abono = $this->abonoService->eliminarAbono($id);
        return Response()->json($abono, 201);
    }

    public function print($id)
    {
        $datos = $this->abonoService->obtenerDatosRecibo($id);
        $venta = $datos['venta'];
        $recibo = $datos['recibo'];

        $pdf = app('dompdf.wrapper')->loadView('reportes.recibos.recibo', compact('venta', 'recibo'));
        $pdf->setPaper('US Letter', 'portrait');

        $nombreArchivo = ($recibo->nombre_documento ?? 'recibo') . '-' . ($recibo->correlativo ?? $recibo->id) . '.pdf';
        return $pdf->stream($nombreArchivo);
    }

    public function export(Request $request)
    {
        $abonos = new AbonosVentasExport();
        $abonos->filter($request);
        return Excel::download($abonos, 'abonos.xlsx');
    }
}

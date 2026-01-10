<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use App\Services\Compras\AbonoCompraService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Exports\AbonosComprasExport;
use Maatwebsite\Excel\Facades\Excel;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\Compras\Abonos\StoreAbonoCompraRequest;

class AbonosController extends Controller
{
    protected $abonoService;

    public function __construct(AbonoCompraService $abonoService)
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

    public function store(StoreAbonoCompraRequest $request)
    {
        try {
            $abono = $this->abonoService->crearOActualizarAbono($request->all());
            return Response()->json($abono, 200);
        } catch (\Exception $e) {
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function changeEstado(Request $request)
    {
        try {
            $abono = $this->abonoService->cambiarEstado($request->id, $request->estado);
            return Response()->json($abono, 200);
        } catch (\Exception $e) {
            return Response()->json(['error' => $e->getMessage()], 400);
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
        $compra = $datos['compra'];
        $recibo = $datos['recibo'];

        /** @var \App\Models\User|null $user */
        $user = JWTAuth::parseToken()->authenticate();
        $usarPlantillaEspecial = $user && $this->abonoService->usarPlantillaEspecial($user->id_empresa);

        if ($usarPlantillaEspecial) {
            $pdf = PDF::loadView('reportes.recibos.velo-recibo', compact('compra', 'recibo'));
            $pdf->setPaper('US Letter', 'portrait');
        } else {
            $pdf = PDF::loadView('reportes.recibos.recibo', compact('compra', 'recibo'));
            $pdf->setPaper('US Letter', 'portrait');
        }

        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    }

    public function export(Request $request)
    {
        $abonos = new AbonosComprasExport();
        $abonos->filter($request);
        return Excel::download($abonos, 'abonos.xlsx');
    }
}

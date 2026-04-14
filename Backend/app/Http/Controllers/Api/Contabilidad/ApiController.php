<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\Contabilidad\VentasService;
use App\Services\Contabilidad\CXCService;
use App\Services\Contabilidad\ComprasService;
use App\Services\Contabilidad\CXPService;
use App\Services\Contabilidad\GastosService;
use App\Services\Contabilidad\TransaccionesService;
use App\Services\Contabilidad\RetaceoService;
use App\Services\Contabilidad\AjustesService;
use App\Services\Contabilidad\TrasladosService;
use App\Services\Contabilidad\OtrasEntradasService;
use App\Services\Contabilidad\OtrasSalidasService;

use App\Http\Requests\Contabilidad\RetaceoRequest;

class ApiController extends Controller
{

    protected $ventasService;
    protected $cxcService;
    protected $comprasService;
    protected $cxpService;
    protected $gastosService;
    protected $transaccionesService;
    protected $retaceoService;
    protected $ajustesService;
    protected $trasladosService;
    protected $otrasEntradasService;
    protected $otrasSalidasService;

    public function __construct(
        VentasService $ventasService,
        CXCService $cxcService,
        ComprasService $comprasService,
        CXPService $cxpService,
        GastosService $gastosService,
        TransaccionesService $transaccionesService,
        RetaceoService $retaceoService,
        AjustesService $ajustesService,
        TrasladosService $trasladosService,
        OtrasEntradasService $otrasEntradasService,
        OtrasSalidasService $otrasSalidasService
    ) {
        $this->ventasService = $ventasService;
        $this->cxcService = $cxcService;
        $this->gastosService = $gastosService;
        $this->comprasService = $comprasService;
        $this->cxpService = $cxpService;
        $this->transaccionesService = $transaccionesService;
        $this->retaceoService = $retaceoService;
        $this->ajustesService = $ajustesService;
        $this->trasladosService = $trasladosService;
        $this->otrasEntradasService = $otrasEntradasService;
        $this->otrasSalidasService = $otrasSalidasService;
    }

    public function venta(Request $venta)
    {
        $this->ventasService->crearPartida($venta);

        return Response()->json($venta, 200);
    }

    public function compra(Request $compra)
    {
        $this->comprasService->crearPartida($compra);

        return Response()->json($compra, 200);
    }

    public function gasto(Request $gasto)
    {
        $this->gastosService->crearPartida($gasto);

        return Response()->json($gasto, 200);
    }

    public function cxp(Request $cxp)
    {
        $this->cxpService->crearPartida($cxp);

        return Response()->json($cxp, 200);
    }

    public function cxc(Request $cxc)
    {
        $this->cxcService->crearPartida($cxc);

        return Response()->json($cxc, 200);
    }

    public function transaccion(Request $transaccion)
    {
        $this->transaccionesService->crearPartida($transaccion);

        return Response()->json($transaccion, 200);
    }


    public function ajuste(Request $ajuste)
    {
        $this->ajustesService->crearPartida($ajuste);

        return Response()->json($ajuste, 200);
    }


    public function traslado(Request $traslado)
    {
        $this->trasladosService->crearPartida($traslado);

        return Response()->json($traslado, 200);
    }

    public function otraEntrada(Request $entrada)
    {
        $this->otrasEntradasService->crearPartida($entrada);

        return Response()->json($entrada, 200);
    }

    public function otraSalida(Request $salida)
    {
        $this->otrasSalidasService->crearPartida($salida);

        return Response()->json($salida, 200);
    }


    public function retaceo(RetaceoRequest $request)
    {
        try {
            $retaceoService = new RetaceoService();
            $resultado = $retaceoService->crearPartida($request->id_retaceo);

            return response()->json([
                'message' => $resultado['mensaje'],
                'partidas_creadas' => $resultado['partidas_creadas'],
                'success' => $resultado['success']
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use Illuminate\Support\Facades\DB;

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

use App\Models\Bancos\Cuenta;
use App\Models\Compras\Retaceo\Retaceo;
use Illuminate\Support\Facades\Validator;

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

        $partida = Partida::where('referencia', 'Venta')->where('id_referencia', $venta->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la venta.', 'code' => 400], 400);
        // }

        $this->ventasService->crearPartida($venta);

        return Response()->json($venta, 200);
    }

    public function compra(Request $compra)
    {

        $partida = Partida::where('referencia', 'Compra')->where('id_referencia', $compra->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        // }

        $this->comprasService->crearPartida($compra);

        return Response()->json($compra, 200);
    }

    public function gasto(Request $gasto)
    {

        $partida = Partida::where('referencia', 'Gasto')->where('id_referencia', $gasto->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para el gasto.', 'code' => 400], 400);
        // }

        $this->gastosService->crearPartida($gasto);

        return Response()->json($gasto, 200);
    }

    public function cxp(Request $cxp)
    {

        $partida = Partida::where('referencia', 'Gasto')->where('id_referencia', $cxp->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para el cxp.', 'code' => 400], 400);
        // }

        $this->cxpService->crearPartida($cxp);

        return Response()->json($cxp, 200);
    }

    public function cxc(Request $cxc)
    {

        $partida = Partida::where('referencia', 'Gasto')->where('id_referencia', $cxc->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para el cxc.', 'code' => 400], 400);
        // }

        $this->cxcService->crearPartida($cxc);

        return Response()->json($cxc, 200);
    }

    public function transaccion(Request $transaccion)
    {

        $partida = Partida::where('referencia', 'Transacción')->where('id_referencia', $transaccion->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        // }

        $this->transaccionesService->crearPartida($transaccion);

        return Response()->json($transaccion, 200);
    }


    public function ajuste(Request $ajuste)
    {

        $partida = Partida::where('referencia', 'Ajuste')->where('id_referencia', $ajuste->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        // }

        $this->ajustesService->crearPartida($ajuste);

        return Response()->json($ajuste, 200);
    }


    public function traslado(Request $traslado)
    {

        $partida = Partida::where('referencia', 'Ajuste')->where('id_referencia', $traslado->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        // }

        $this->trasladosService->crearPartida($traslado);

        return Response()->json($traslado, 200);
    }

    public function otraEntrada(Request $entrada)
    {
        $partida = Partida::where('referencia', 'Otra Entrada')->where('id_referencia', $entrada->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la entrada.', 'code' => 400], 400);
        // }

        $this->otrasEntradasService->crearPartida($entrada);

        return Response()->json($entrada, 200);
    }

    public function otraSalida(Request $salida)
    {
        $partida = Partida::where('referencia', 'Otra Salida')->where('id_referencia', $salida->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la salida.', 'code' => 400], 400);
        // }

        $this->otrasSalidasService->crearPartida($salida);

        return Response()->json($salida, 200);
    }


    public function retaceo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_retaceo' => 'required|exists:retaceos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

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

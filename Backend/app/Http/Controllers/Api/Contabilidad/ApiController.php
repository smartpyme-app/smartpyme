<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use Illuminate\Support\Facades\DB;

use App\Services\Contabilidad\ComprasService;
use App\Services\Contabilidad\GastosService;
use App\Services\Contabilidad\VentasService;
use App\Services\Contabilidad\TransaccionesService;

use App\Models\Bancos\Cuenta;

class ApiController extends Controller
{

    protected $comprasService;
    protected $gastosService;
    protected $ventasService;
    protected $transaccionesService;

    public function __construct(VentasService $ventasService,
                                ComprasService $comprasService, 
                                GastosService $gastosService, 
                                TransaccionesService $transaccionesService)
    {
        $this->ventasService = $ventasService;
        $this->gastosService = $gastosService;
        $this->comprasService = $comprasService;
        $this->transaccionesService = $transaccionesService;
    }

    public function venta(Request $venta) {

        $partida = Partida::where('referencia', 'Venta')->where('id_referencia', $venta->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la venta.', 'code' => 400], 400);
        // }

        $this->ventasService->crearPartida($venta);

        return Response()->json($venta, 200);
    }

    public function compra(Request $compra) {

        $partida = Partida::where('referencia', 'Compra')->where('id_referencia', $compra->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        // }

        $this->comprasService->crearPartida($compra);

        return Response()->json($compra, 200);
    }

    public function gasto(Request $gasto) {

        $partida = Partida::where('referencia', 'Gasto')->where('id_referencia', $gasto->id)->first();

        // if ($partida) {
        //     return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para el gasto.', 'code' => 400], 400);
        // }

        $this->gastosService->crearPartida($gasto);

        return Response()->json($gasto, 200);
    }

    public function transaccion(Request $request) {

        $partida = Partida::where('referencia', 'Transacción')->where('id_referencia', $request->id)->first();

        if ($partida) {
            return  Response()->json(['titulo' => 'Verificar registro de partidas.', 'error' => 'Ya hay una partida creada para la compra.', 'code' => 400], 400);
        }

        $transaccion = Transaccion::with('cuenta')->where('id', $request->id)->firstOrFail();

        $this->transaccionesService->crearPartida($transaccion);
        
        return Response()->json($transaccion, 200);
    }


}
